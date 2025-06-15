<?php

declare(strict_types=1);

namespace Gmbit\FlipbookConverter\Service;

use Gmbit\FlipbookConverter\Domain\Model\FlipbookDocument;
use Gmbit\FlipbookConverter\Domain\Repository\FlipbookDocumentRepository;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

/**
 * Servis za processing PDF dokumenata u flipbook format
 */
class PdfProcessorService
{
    protected Logger $logger;
    protected ResourceFactory $resourceFactory;
    protected StorageRepository $storageRepository;
    protected PersistenceManager $persistenceManager;
    protected FlipbookDocumentRepository $documentRepository;

    public function __construct(
        ResourceFactory $resourceFactory,
        StorageRepository $storageRepository,
        PersistenceManager $persistenceManager,
        FlipbookDocumentRepository $documentRepository
    ) {
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $this->resourceFactory = $resourceFactory;
        $this->storageRepository = $storageRepository;
        $this->persistenceManager = $persistenceManager;
        $this->documentRepository = $documentRepository;
    }

    /**
     * Procesirati PDF dokument
     *
     * @param FlipbookDocument $document
     * @return bool
     */
    public function processDocument(FlipbookDocument $document): bool
    {
        $startTime = microtime(true);
        
        try {
            $document->setStatus(FlipbookDocument::STATUS_PROCESSING);
            $document->addToProcessingLog('Started processing PDF document');
            $this->persistDocument($document);

            // Dobiti PDF file
            $pdfFile = $document->getPdfFile();
            if (!$pdfFile) {
                throw new \Exception('No PDF file attached to document');
            }

            $originalFile = $pdfFile->getOriginalResource();
            if (!$originalFile instanceof File) {
                throw new \Exception('Invalid PDF file reference');
            }

            // Validacija PDF fajla
            $this->validatePdfFile($originalFile);

            // Generisanje file hash-a
            $fileHash = $this->generateFileHash($originalFile);
            $document->setFileHash($fileHash);
            $document->setFileSize($originalFile->getSize());

            // Proveriti da li već postoji procesovan dokument sa istim hash-om
            $existingDocument = $this->documentRepository->findByFileHash($fileHash);
            if ($existingDocument && $existingDocument->getUid() !== $document->getUid() && $existingDocument->isCompleted()) {
                $this->logger->info('Found existing processed document with same hash', ['hash' => $fileHash]);
                $document->addToProcessingLog('Found existing processed document - copying data');
                
                // Kopirati podatke iz postojećeg dokumenta
                $this->copyProcessedData($existingDocument, $document);
                
                $processingTime = (int)((microtime(true) - $startTime) * 1000);
                $document->setProcessingTime($processingTime);
                $document->setStatus(FlipbookDocument::STATUS_COMPLETED);
                $document->setLastProcessed(new \DateTime());
                $document->addToProcessingLog('Processing completed using existing data');
                
                $this->persistDocument($document);
                return true;
            }

            // Konvertovati PDF u slike
            $document->addToProcessingLog('Starting PDF to images conversion');
            $images = $this->convertPdfToImages($originalFile);
            
            if (empty($images)) {
                throw new \Exception('Failed to convert PDF to images');
            }

            $document->setTotalPages(count($images));
            $document->setProcessedImages($images);
            $document->addToProcessingLog('Converted PDF to ' . count($images) . ' images');

            // Kreirati thumbnails
            $document->addToProcessingLog('Generating thumbnails');
            $this->generateThumbnails($images);

            // Optimizovati slike
            $document->addToProcessingLog('Optimizing images');
            $this->optimizeImages($images);

            // Završiti processing
            $processingTime = (int)((microtime(true) - $startTime) * 1000);
            $document->setProcessingTime($processingTime);
            $document->setStatus(FlipbookDocument::STATUS_COMPLETED);
            $document->setLastProcessed(new \DateTime());
            $document->addToProcessingLog('Processing completed successfully in ' . $processingTime . 'ms');

            $this->persistDocument($document);
            
            $this->logger->info('PDF processing completed', [
                'documentUid' => $document->getUid(),
                'processingTime' => $processingTime,
                'totalPages' => count($images)
            ]);

            return true;

        } catch (\Exception $e) {
            $processingTime = (int)((microtime(true) - $startTime) * 1000);
            $document->setProcessingTime($processingTime);
            $document->setStatus(FlipbookDocument::STATUS_ERROR);
            $document->addToProcessingLog('Error: ' . $e->getMessage());
            
            $this->persistDocument($document);
            
            $this->logger->error('PDF processing failed', [
                'documentUid' => $document->getUid(),
                'error' => $e->getMessage(),
                'processingTime' => $processingTime
            ]);

            return false;
        }
    }

    /**
     * Konvertovati PDF u niz slika
     *
     * @param File $pdfFile
     * @return array
     * @throws \Exception
     */
    protected function convertPdfToImages(File $pdfFile): array
    {
        $pdfPath = $pdfFile->getForLocalProcessing(false);
        
        if (!file_exists($pdfPath)) {
            throw new \Exception('PDF file not found: ' . $pdfPath);
        }

        $images = [];
        
        // Pokušati sa ImageMagick
        if (extension_loaded('imagick')) {
            $images = $this->convertWithImageMagick($pdfPath);
        }
        
        // Fallback na GhostScript ako ImageMagick nije uspeo
        if (empty($images) && $this->isGhostScriptAvailable()) {
            $images = $this->convertWithGhostScript($pdfPath);
        }
        
        if (empty($images)) {
            throw new \Exception('Failed to convert PDF with both ImageMagick and GhostScript');
        }

        return $images;
    }

    /**
     * Konvertovati PDF pomoću ImageMagick
     *
     * @param string $pdfPath
     * @return array
     */
    protected function convertWithImageMagick(string $pdfPath): array
    {
        try {
            $imagick = new \Imagick();
            $imagick->setResolution(150, 150);
            $imagick->readImage($pdfPath);
            
            $images = [];
            $storage = $this->getFlipbookStorage();
            $outputFolder = $this->createOutputFolder($storage);
            
            foreach ($imagick as $pageIndex => $page) {
                $page->setImageFormat('png');
                $page->setImageCompressionQuality(90);
                
                $filename = 'page_' . ($pageIndex + 1) . '.png';
                $tempPath = GeneralUtility::tempnam('flipbook_page_', '.png');
                
                $page->writeImage($tempPath);
                
                // Preneti u storage
                $file = $storage->addFile($tempPath, $outputFolder, $filename, 'replace');
                $images[] = [
                    'page' => $pageIndex + 1,
                    'file' => $file->getUid(),
                    'publicUrl' => $file->getPublicUrl(),
                    'width' => $page->getImageWidth(),
                    'height' => $page->getImageHeight()
                ];
                
                unlink($tempPath);
                $page->clear();
            }
            
            $imagick->clear();
            return $images;
            
        } catch (\Exception $e) {
            $this->logger->warning('ImageMagick conversion failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Konvertovati PDF pomoću GhostScript
     *
     * @param string $pdfPath
     * @return array
     */
    protected function convertWithGhostScript(string $pdfPath): array
    {
        try {
            $storage = $this->getFlipbookStorage();
            $outputFolder = $this->createOutputFolder($storage);
            $tempDir = GeneralUtility::tempnam('flipbook_gs_', '');
            mkdir($tempDir);
            
            $outputPattern = $tempDir . '/page_%d.png';
            
            $command = sprintf(
                'gs -dNOPAUSE -dBATCH -sDEVICE=png16m -r150 -sOutputFile=%s %s',
                escapeshellarg($outputPattern),
                escapeshellarg($pdfPath)
            );
            
            exec($command, $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new \Exception('GhostScript command failed with code: ' . $returnCode);
            }
            
            $images = [];
            $pageFiles = glob($tempDir . '/page_*.png');
            sort($pageFiles, SORT_NATURAL);
            
            foreach ($pageFiles as $index => $pageFile) {
                $filename = 'page_' . ($index + 1) . '.png';
                $file = $storage->addFile($pageFile, $outputFolder, $filename, 'replace');
                
                $imageSize = getimagesize($pageFile);
                $images[] = [
                    'page' => $index + 1,
                    'file' => $file->getUid(),
                    'publicUrl' => $file->getPublicUrl(),
                    'width' => $imageSize[0] ?? 0,
                    'height' => $imageSize[1] ?? 0
                ];
            }
            
            // Cleanup
            array_map('unlink', $pageFiles);
            rmdir($tempDir);
            
            return $images;
            
        } catch (\Exception $e) {
            $this->logger->warning('GhostScript conversion failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Generisati thumbnails za slike
     *
     * @param array $images
     * @return void
     */
    protected function generateThumbnails(array &$images): void
    {
        $storage = $this->getFlipbookStorage();
        
        foreach ($images as &$imageData) {
            try {
                $file = $this->resourceFactory->getFileObject($imageData['file']);
                if (!$file) {
                    continue;
                }
                
                // Kreirati thumbnail
                $thumbnailConfig = [
                    'width' => 200,
                    'height' => 200,
                    'crop' => false
                ];
                
                $processedFile = $file->process('Image.Preview', $thumbnailConfig);
                $imageData['thumbnail'] = [
                    'file' => $processedFile->getUid(),
                    'publicUrl' => $processedFile->getPublicUrl(),
                    'width' => $processedFile->getProperty('width'),
                    'height' => $processedFile->getProperty('height')
                ];
                
            } catch (\Exception $e) {
                $this->logger->warning('Thumbnail generation failed', [
                    'page' => $imageData['page'],
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Optimizovati slike
     *
     * @param array $images
     * @return void
     */
    protected function optimizeImages(array $images): void
    {
        foreach ($images as $imageData) {
            try {
                $file = $this->resourceFactory->getFileObject($imageData['file']);
                if (!$file) {
                    continue;
                }
                
                $filePath = $file->getForLocalProcessing(false);
                
                // PNG optimizacija
                if ($file->getExtension() === 'png') {
                    $this->optimizePng($filePath);
                }
                
            } catch (\Exception $e) {
                $this->logger->warning('Image optimization failed', [
                    'page' => $imageData['page'],
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Optimizovati PNG fajl
     *
     * @param string $filePath
     * @return void
     */
    protected function optimizePng(string $filePath): void
    {
        // Pokušati sa optipng ako je dostupan
        if ($this->isCommandAvailable('optipng')) {
            exec('optipng -quiet -o2 ' . escapeshellarg($filePath));
            return;
        }
        
        // Fallback na PHP GD optimizaciju
        if (extension_loaded('gd')) {
            $image = imagecreatefrompng($filePath);
            if ($image) {
                imagepng($image, $filePath, 6); // Compression level 6
                imagedestroy($image);
            }
        }
    }

    /**
     * Validirati PDF fajl
     *
     * @param File $file
     * @return void
     * @throws \Exception
     */
    protected function validatePdfFile(File $file): void
    {
        if ($file->getMimeType() !== 'application/pdf') {
            throw new \Exception('File is not a valid PDF: ' . $file->getMimeType());
        }
        
        if ($file->getSize() === 0) {
            throw new \Exception('PDF file is empty');
        }
        
        if ($file->getSize() > 100 * 1024 * 1024) { // 100MB limit
            throw new \Exception('PDF file too large: ' . $file->getSize() . ' bytes');
        }
    }

    /**
     * Generisati hash za fajl
     *
     * @param File $file
     * @return string
     */
    protected function generateFileHash(File $file): string
    {
        $filePath = $file->getForLocalProcessing(false);
        return hash_file('sha256', $filePath);
    }

    /**
     * Kopirati obrađene podatke između dokumenata
     *
     * @param FlipbookDocument $source
     * @param FlipbookDocument $target
     * @return void
     */
    protected function copyProcessedData(FlipbookDocument $source, FlipbookDocument $target): void
    {
        $target->setTotalPages($source->getTotalPages());
        $target->setProcessedImages($source->getProcessedImages());
        $target->setFlipbookConfig($source->getFlipbookConfig());
    }

    /**
     * Dobiti storage za flipbook fajlove
     *
     * @return \TYPO3\CMS\Core\Resource\ResourceStorage
     */
    protected function getFlipbookStorage(): \TYPO3\CMS\Core\Resource\ResourceStorage
    {
        return $this->storageRepository->getDefaultStorage();
    }

    /**
     * Kreirati output folder za processed images
     *
     * @param \TYPO3\CMS\Core\Resource\ResourceStorage $storage
     * @return \TYPO3\CMS\Core\Resource\Folder
     */
    protected function createOutputFolder(\TYPO3\CMS\Core\Resource\ResourceStorage $storage): \TYPO3\CMS\Core\Resource\Folder
    {
        $baseFolderName = 'flipbook_documents';
        $documentFolderName = date('Y/m');
        
        try {
            $baseFolder = $storage->getFolder($baseFolderName);
        } catch (\Exception $e) {
            $baseFolder = $storage->createFolder($baseFolderName);
        }
        
        try {
            return $storage->getFolder($baseFolderName . '/' . $documentFolderName);
        } catch (\Exception $e) {
            return $storage->createFolder($documentFolderName, $baseFolder);
        }
    }

    /**
     * Proveriti da li je GhostScript dostupan
     *
     * @return bool
     */
    protected function isGhostScriptAvailable(): bool
    {
        return $this->isCommandAvailable('gs');
    }

    /**
     * Proveriti da li je komanda dostupna
     *
     * @param string $command
     * @return bool
     */
    protected function isCommandAvailable(string $command): bool
    {
        $output = [];
        $returnCode = 0;
        exec('which ' . escapeshellarg($command), $output, $returnCode);
        return $returnCode === 0;
    }

    /**
     * Persist document u bazu
     *
     * @param FlipbookDocument $document
     * @return void
     */
    protected function persistDocument(FlipbookDocument $document): void
    {
        $this->documentRepository->update($document);
        $this->persistenceManager->persistAll();
    }

    /**
     * Obrisati processed images za dokument
     *
     * @param FlipbookDocument $document
     * @return bool
     */
    public function deleteProcessedImages(FlipbookDocument $document): bool
    {
        try {
            $images = $document->getProcessedImages();
            
            foreach ($images as $imageData) {
                try {
                    $file = $this->resourceFactory->getFileObject($imageData['file']);
                    if ($file) {
                        $file->delete();
                    }
                } catch (\Exception $e) {
                    $this->logger->warning('Failed to delete processed image', [
                        'fileUid' => $imageData['file'],
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            $document->setProcessedImages([]);
            $document->setStatus(FlipbookDocument::STATUS_PENDING);
            $this->persistDocument($document);
            
            return true;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete processed images', [
                'documentUid' => $document->getUid(),
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Reprocessirati dokument
     *
     * @param FlipbookDocument $document
     * @return bool
     */
    public function reprocessDocument(FlipbookDocument $document): bool
    {
        // Obrisati postojeće processed images
        $this->deleteProcessedImages($document);
        
        // Resetovati processing log
        $document->setProcessingLog('');
        $document->addToProcessingLog('Reprocessing started');
        
        // Pokrenuti novo processing
        return $this->processDocument($document);
    }
}