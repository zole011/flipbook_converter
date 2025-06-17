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
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Resource\FileRepository;


use TYPO3\CMS\Core\Resource\FileReference as CoreFileReference;
use TYPO3\CMS\Extbase\Domain\Model\FileReference as ExtbaseFileReference;


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
        FlipbookDocumentRepository $documentRepository,
        LoggerInterface $logger,
    ) {
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $this->resourceFactory = $resourceFactory;
        $this->storageRepository = $storageRepository;
        $this->persistenceManager = $persistenceManager;
        $this->documentRepository = $documentRepository;
        $this->logger = $logger;
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

        // Dobiti File objekat preko TYPO3 FileReference
        $originalFile = null;
        
        if ($pdfFile instanceof \TYPO3\CMS\Extbase\Domain\Model\FileReference) {
            // Dobiti Core FileReference
            $coreFileReference = $pdfFile->getOriginalResource();
            
            if ($coreFileReference instanceof \TYPO3\CMS\Core\Resource\FileReference) {
                // Dobiti File objekat
                $originalFile = $coreFileReference->getOriginalFile();
            }
        }
        
        if (!$originalFile instanceof \TYPO3\CMS\Core\Resource\File) {
            // Debug informacije
            $this->logger->error('Failed to get File object', [
                'pdfFileClass' => get_class($pdfFile),
                'hasOriginalResource' => method_exists($pdfFile, 'getOriginalResource'),
                'coreFileReferenceClass' => isset($coreFileReference) ? get_class($coreFileReference) : 'not set'
            ]);
            throw new \Exception('Invalid PDF file reference - could not load file object');
        }

        // Proveri da li fajl stvarno postoji
        if (!$originalFile->exists()) {
            throw new \Exception('PDF file does not exist on disk');
        }

        $this->logger->info('Successfully loaded PDF file', [
            'name' => $originalFile->getName(),
            'size' => $originalFile->getSize(),
            'path' => $originalFile->getForLocalProcessing(false)
        ]);

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


// Na kraju processDocument metode
$document->setTotalPages(count($images));




// Konvertuj PDF u slike
$images = $this->convertPdfToImages($originalFile);
$document->setTotalPages(count($images));

// Prosledi array, NE JSON string
$document->setProcessedImages($images); // Ovde prosledi array

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

    private function debugFileReference($pdfFile): void
    {
        $this->logger->info('FileReference debug', [
            'class' => get_class($pdfFile),
            'uid' => method_exists($pdfFile, 'getUid') ? $pdfFile->getUid() : 'no uid',
            'uidLocal' => method_exists($pdfFile, 'getUidLocal') ? $pdfFile->getUidLocal() : 'no uidLocal',
            'methods' => get_class_methods($pdfFile)
        ]);
    }

    /**
     * Konvertovati PDF u niz slika
     *
     * @param File $pdfFile
     * @return array
     * @throws \Exception
     */
private function convertPdfToImages(File $pdfFile): array
{
    $images = [];
    $localFile = $pdfFile->getForLocalProcessing(false);
    
    $this->logger->info('Starting PDF to images conversion', [
        'file' => $localFile,
        'exists' => file_exists($localFile)
    ]);
    
    // Kreirati privremeni folder za slike
    $tempPath = Environment::getVarPath() . '/transient/flipbook_' . uniqid();
    GeneralUtility::mkdir_deep($tempPath);
    
    try {
        $converted = false;
        
        // Pokušaj 1: ImageMagick
        try {
            $converted = $this->convertWithImageMagick($localFile, $tempPath);
        } catch (\Exception $e) {
            $this->logger->warning('ImageMagick conversion failed, trying GhostScript', [
                'error' => $e->getMessage()
            ]);
        }
        
        // Pokušaj 2: Direktno sa GhostScript
        if (!$converted) {
            $converted = $this->convertWithGhostScript($localFile, $tempPath);
        }
        
        if (!$converted) {
            throw new \Exception('Failed to convert PDF with both ImageMagick and GhostScript');
        }
        
        // Pronađi sve generirane slike
        $generatedFiles = glob($tempPath . DIRECTORY_SEPARATOR . 'page-*.png');
        
        if (empty($generatedFiles)) {
            throw new \Exception('No images generated from PDF');
        }
        
        $this->logger->info('PDF converted to images', ['count' => count($generatedFiles)]);
        
        // Premesti slike u fileadmin
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        $storage = $resourceFactory->getDefaultStorage();
        
        // Kreiraj folder za processed images
        try {
            $targetFolder = $storage->getFolder('flipbook_processed');
        } catch (\Exception $e) {
            $targetFolder = $storage->createFolder('flipbook_processed');
        }
        
        // Kreiraj podfolder za ovaj dokument
        $documentFolder = $targetFolder->createFolder('document_' . time() . '_' . uniqid());
        
        $imageData = []; // Array za čuvanje podataka o slikama
        
        foreach ($generatedFiles as $index => $imagePath) {
            $fileName = sprintf('page_%04d.png', $index + 1);
            $newFile = $storage->addFile($imagePath, $documentFolder, $fileName);
            
            // Sačuvaj podatke o slici kao array, ne kao File objekat
            $imageData[] = [
                'uid' => $newFile->getUid(),
                'path' => $newFile->getPublicUrl(),
                'identifier' => $newFile->getIdentifier(),
                'name' => $newFile->getName(),
                'page' => $index + 1
            ];
        }
        
        // Obriši temp folder
        GeneralUtility::rmdir($tempPath, true);
        
        return $imageData;
        
    } catch (\Exception $e) {
        // Cleanup on error
        if (is_dir($tempPath)) {
            GeneralUtility::rmdir($tempPath, true);
        }
        
        throw $e;
    }
}

private function convertWithGhostScript(string $pdfPath, string $outputPath): bool
{
    // Pokušaj pronaći GhostScript
    $gsPaths = [
        'C:\\Program Files\\gs\\gs9.56.1\\bin\\gswin64c.exe',
        'C:\\Program Files\\gs\\gs9.55.0\\bin\\gswin64c.exe',
        'C:\\Program Files (x86)\\gs\\gs9.56.1\\bin\\gswin32c.exe',
        'C:\\Program Files (x86)\\gs\\gs9.55.0\\bin\\gswin32c.exe',
        'gswin64c.exe',
        'gswin32c.exe'
    ];
    
    $gsExecutable = null;
    foreach ($gsPaths as $gsPath) {
        if (file_exists($gsPath) || $this->isExecutable($gsPath)) {
            $gsExecutable = $gsPath;
            break;
        }
    }
    
    if (!$gsExecutable) {
        throw new \Exception('GhostScript not found');
    }
    
    $command = sprintf(
        '"%s" -dNOPAUSE -dBATCH -sDEVICE=png16m -r150 -sOutputFile="%s\\page-%%04d.png" "%s"',
        $gsExecutable,
        $outputPath,
        $pdfPath
    );
    
    $this->logger->info('Executing GhostScript command', ['command' => $command]);
    
    $output = [];
    $returnVar = 0;
    exec($command . ' 2>&1', $output, $returnVar);
    
    if ($returnVar !== 0) {
        $this->logger->error('GhostScript conversion failed', [
            'command' => $command,
            'output' => implode("\n", $output),
            'returnVar' => $returnVar
        ]);
        return false;
    }
    
    return true;
}

private function convertWithImageMagick(string $pdfPath, string $outputPath): bool
{
    $imageMagickPath = $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_path'] ?? '';
    
if (PHP_OS_FAMILY === 'Windows') {
        $convert = rtrim($imageMagickPath, '/\\') . '\\magick.exe';
        
        if (!file_exists($convert)) {
            throw new \Exception('ImageMagick not found at: ' . $convert);
        }
        
        $command = sprintf(
            '"%s" convert -density 150 -quality 90 "%s" "%s\\page-%%04d.png"',
            $convert,
            $pdfPath,
            $outputPath
        );
    } else {
        $convert = rtrim($imageMagickPath, '/') . '/convert';
        
        if (!is_executable($convert)) {
            throw new \Exception('ImageMagick convert not found at: ' . $convert);
        }
        
        $command = sprintf(
            '%s -density 150 -quality 90 "%s" "%s/page-%%04d.png"',
            $convert,
            $pdfPath,
            $outputPath
        );
    }
    
    $output = [];
    $returnVar = 0;
    exec($command . ' 2>&1', $output, $returnVar);
    
    if ($returnVar !== 0) {
        throw new \Exception('ImageMagick failed: ' . implode("\n", $output));
    }
    
    return true;
}

private function isExecutable(string $path): bool
{
if (PHP_OS_FAMILY === 'Windows') {
        // Na Windows-u, proveri pomoću 'where' komande
        $output = [];
        $returnVar = 0;
        exec('where ' . escapeshellarg($path) . ' 2>NUL', $output, $returnVar);
        return $returnVar === 0;
    }
    return is_executable($path);
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
        
        // Windows compatibility
        $cmd = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'where' : 'which';
        exec($cmd . ' ' . escapeshellarg($command), $output, $returnCode);
        
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