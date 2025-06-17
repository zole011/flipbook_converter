<?php

declare(strict_types=1);

namespace Gmbit\FlipbookConverter\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Pagination\QueryResultPaginator;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use Gmbit\FlipbookConverter\Domain\Model\FlipbookDocument;
use Gmbit\FlipbookConverter\Domain\Repository\FlipbookDocumentRepository;
use Gmbit\FlipbookConverter\Service\PdfProcessorService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Resource\FileReference as CoreFileReference;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Log\Logger;

/**
 * Backend Controller za upravljanje flipbook dokumentima
 */
class BackendController extends ActionController
{
    protected StorageRepository $storageRepository;
    protected FlipbookDocumentRepository $documentRepository;
    protected PdfProcessorService $pdfProcessorService;
    protected ModuleTemplateFactory $moduleTemplateFactory;
    protected PersistenceManager $persistenceManager;
    protected PageRenderer $pageRenderer;
    protected Logger $logger;

    public function __construct(
        StorageRepository $storageRepository,
        FlipbookDocumentRepository $documentRepository,
        PdfProcessorService $pdfProcessorService,
        ModuleTemplateFactory $moduleTemplateFactory,
        PersistenceManager $persistenceManager,
        PageRenderer $pageRenderer,
    ) {
        $this->storageRepository = $storageRepository;
        $this->documentRepository = $documentRepository;
        $this->pdfProcessorService = $pdfProcessorService;
        $this->moduleTemplateFactory = $moduleTemplateFactory;
        $this->persistenceManager = $persistenceManager;
        $this->pageRenderer = $pageRenderer;
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }

    /**
     * Inicijalizacija akcija
     */
    protected function initializeAction(): void
    {
        parent::initializeAction();
        
        // Remove inline scripts that cause CSP issues
        $this->request = $this->request->withAttribute('noindex', true);

        // Dodaj backend CSS/JS
        $this->pageRenderer->addCssFile(
            'EXT:flipbook_converter/Resources/Public/CSS/Backend/module.css'
        );
        $this->pageRenderer->addJsFile(
            'EXT:flipbook_converter/Resources/Public/JavaScript/Backend/FlipbookModule.js'
        );
    }

    /**
     * Lista svih dokumenata
     *
     * @return ResponseInterface
     */
    public function listAction(): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        // Debug
        $this->addFlashMessage(
            'List action called successfully',
            'Debug',
            ContextualFeedbackSeverity::INFO
        );
        // Get documents
        $documents = $this->documentRepository->findAll();
        
        // Setup pagination
        $currentPage = $this->request->hasArgument('currentPage') 
            ? (int)$this->request->getArgument('currentPage') 
            : 1;
        $itemsPerPage = 20;
        
        $paginator = new QueryResultPaginator($documents, $currentPage, $itemsPerPage);
        $pagination = new SimplePagination($paginator);
        
        // Assign variables to view
        $moduleTemplate->assign('documents', $paginator->getPaginatedItems());
        $moduleTemplate->assign('paginator', $paginator);
        $moduleTemplate->assign('pagination', $pagination);
        $moduleTemplate->assign('returnUrl', $this->request->getAttribute('normalizedParams')->getRequestUri());
        
        // Return response
        return $moduleTemplate->renderResponse('Backend/List');
    }

    /**
     * Prikaz pojedinačnog dokumenta
     *
     * @param FlipbookDocument|null $document
     * @return ResponseInterface
     */
    public function showAction(?FlipbookDocument $document = null): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        if (!$document) {
            $this->addFlashMessage(
                'Document not found.',
                'Error',
                AbstractMessage::ERROR
            );
            return new ForwardResponse('list');
        }

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setTitle('Flipbook Document: ' . $document->getTitle());
        
        $this->view->assignMultiple([
            'document' => $document,
            'images' => $document->getProcessedImages(),
            'config' => $document->getFlipbookConfig(),
            'processingLog' => explode("\n", trim($document->getProcessingLog())),
            'canReprocess' => $document->isCompleted() || $document->hasError(),
            'canDelete' => true
        ]);

        $moduleTemplate->assign('document', $document);
        $moduleTemplate->assign('returnUrl', $this->getReturnUrl());
        
        // Return response with template name
        return $moduleTemplate->renderResponse('Backend/Edit');
    }

    /**
     * Forma za kreiranje novog dokumenta
     *
     * @param FlipbookDocument|null $document
     * @return ResponseInterface
     */
    public function newAction(?FlipbookDocument $document = null): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        if (!$document) {
            $document = GeneralUtility::makeInstance(FlipbookDocument::class);
        }

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setTitle('New Flipbook Document');
        
        $this->view->assign('document', $document);

        // Assign variables
        $moduleTemplate->assign('document', $document);
        $moduleTemplate->assign('returnUrl', $this->getReturnUrl());
        
        // Return response with template name
        return $moduleTemplate->renderResponse('Backend/Edit');
    }

    /**
     * Kreiranje novog dokumenta
     *
     * @param FlipbookDocument $document
     * @return ResponseInterface
     */
    public function createAction(FlipbookDocument $document): ResponseInterface
    {
        try {
            // Validacija
            if (empty($document->getTitle())) {
                throw new \InvalidArgumentException('Title is required');
            }

            if (!$document->getPdfFile()) {
                throw new \InvalidArgumentException('PDF file is required');
            }

            // Postaviti default status
            $document->setStatus(FlipbookDocument::STATUS_PENDING);
            
            // Sačuvati dokument
            $this->documentRepository->add($document);
            $this->persistenceManager->persistAll();

            $this->addFlashMessage(
                'Document "' . $document->getTitle() . '" has been created successfully.',
                'Success',
                AbstractMessage::OK
            );

            // Pokušati automatsko procesiranje
            if ($this->pdfProcessorService->processDocument($document)) {
                $this->addFlashMessage(
                    'Document has been processed successfully.',
                    'Processing Complete',
                    AbstractMessage::OK
                );
            } else {
                $this->addFlashMessage(
                    'Document was created but processing failed. You can retry processing from the document details.',
                    'Processing Failed',
                    AbstractMessage::WARNING
                );
            }

            return $this->redirect('show', null, null, ['document' => $document]);

        } catch (\Exception $e) {
            $this->addFlashMessage(
                'Error creating document: ' . $e->getMessage(),
                'Error',
                AbstractMessage::ERROR
            );
            
            return new ForwardResponse('new', null, null, ['document' => $document]);
        }
    }

    /**
     * Forma za editovanje dokumenta
     *
     * @param FlipbookDocument $document
     * @return ResponseInterface
     */
    public function editAction(FlipbookDocument $document): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setTitle('Edit Flipbook Document: ' . $document->getTitle());
        
        $this->view->assign('document', $document);

        // Assign variables
        $moduleTemplate->assign('document', $document);
        $moduleTemplate->assign('returnUrl', $this->getReturnUrl());
        
        // Return response with template name
        return $moduleTemplate->renderResponse('Backend/Edit');
    }

    /**
     * Ažuriranje dokumenta
     *
     * @param FlipbookDocument $document
     * @return ResponseInterface
     */
    public function updateAction(FlipbookDocument $document): ResponseInterface
    {
        try {
            // Validacija
            if (empty($document->getTitle())) {
                throw new \InvalidArgumentException('Title is required');
            }

            $this->documentRepository->update($document);
            $this->persistenceManager->persistAll();

            $this->addFlashMessage(
                'Document "' . $document->getTitle() . '" has been updated successfully.',
                'Success',
                AbstractMessage::OK
            );

            return $this->redirect('show', null, null, ['document' => $document]);

        } catch (\Exception $e) {
            $this->addFlashMessage(
                'Error updating document: ' . $e->getMessage(),
                'Error',
                AbstractMessage::ERROR
            );
            
            return new ForwardResponse('edit', null, null, ['document' => $document]);
        }
    }

    /**
     * Brisanje dokumenta
     *
     * @param FlipbookDocument $document
     * @return ResponseInterface
     */
    public function deleteAction(FlipbookDocument $document): ResponseInterface
    {
        try {
            $title = $document->getTitle();
            
            // Obrisati processed images
            $this->pdfProcessorService->deleteProcessedImages($document);
            
            // Obrisati dokument
            $this->documentRepository->remove($document);
            $this->persistenceManager->persistAll();

            $this->addFlashMessage(
                'Document "' . $title . '" has been deleted successfully.',
                'Success',
                AbstractMessage::OK
            );

        } catch (\Exception $e) {
            $this->addFlashMessage(
                'Error deleting document: ' . $e->getMessage(),
                'Error',
                AbstractMessage::ERROR
            );
        }

        return $this->redirect('list');
    }

    /**
     * Process document
     */
    public function processAction(FlipbookDocument $document): ResponseInterface
    {
        try {
            $this->addFlashMessage(
                sprintf('Starting to process: %s', $document->getTitle()),
                'Processing',
                ContextualFeedbackSeverity::INFO
            );
            
            $result = $this->pdfProcessorService->processDocument($document);
            
            if ($result) {
                $this->addFlashMessage(
                    'Document processed successfully!',
                    'Success',
                    ContextualFeedbackSeverity::OK
                );
            } else {
                $this->addFlashMessage(
                    'Processing failed - check logs',
                    'Error',
                    ContextualFeedbackSeverity::ERROR
                );
            }
            
        } catch (\Exception $e) {
            $this->addFlashMessage(
                'Error: ' . $e->getMessage(),
                'Processing Error',
                ContextualFeedbackSeverity::ERROR
            );
        }
        
        return $this->redirect('list');
    }

    /**
     * Preview dokumenta
     *
     * @param FlipbookDocument $document
     * @return ResponseInterface
     */
    public function previewAction(FlipbookDocument $document): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        if (!$document->isCompleted()) {
            $this->addFlashMessage(
                'Document is not processed yet. Preview is not available.',
                'Preview Unavailable',
                AbstractMessage::WARNING
            );
            return $this->redirect('show', null, null, ['document' => $document]);
        }

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setTitle('Preview: ' . $document->getTitle());
        
        // Pripremi flipbook konfiguraciju za preview
        $flipbookConfig = [
            'documentUid' => $document->getUid(),
            'totalPages' => $document->getTotalPages(),
            'images' => array_map(function($image) {
                return [
                    'page' => $image['page'],
                    'src' => $image['publicUrl'],
                    'width' => $image['width'],
                    'height' => $image['height'],
                    'thumbnail' => $image['thumbnail'] ?? null
                ];
            }, $document->getProcessedImages()),
            'config' => array_merge($document->getFlipbookConfig(), [
                'width' => 800,
                'height' => 600,
                'showControls' => true,
                'enableZoom' => true,
                'enableFullscreen' => true
            ])
        ];

        // Dodaj preview assets
        $this->addPreviewAssets($flipbookConfig);
        
        $this->view->assignMultiple([
            'document' => $document,
            'flipbookConfig' => $flipbookConfig,
            'images' => $document->getProcessedImages()
        ]);

        // Assign variables
        $moduleTemplate->assign('document', $document);
        $moduleTemplate->assign('returnUrl', $this->getReturnUrl());
        
        // Return response with template name
        return $moduleTemplate->renderResponse('Backend/Edit');
    }

    /**
     * AJAX akcija za bulk operacije
     *
     * @return ResponseInterface
     */
    public function bulkAction(): ResponseInterface
    {
        $action = $this->request->getArgument('bulkAction') ?? '';
        $documentUids = $this->request->getArgument('documents') ?? [];
        
        if (empty($documentUids) || !is_array($documentUids)) {
            return $this->jsonArrayResponse(['success' => false, 'message' => 'No documents selected']);
        }

        $documentUids = array_map('intval', $documentUids);
        $results = ['success' => 0, 'failed' => 0, 'messages' => []];

        try {
            switch ($action) {
                case 'process':
                    $results = $this->bulkProcess($documentUids);
                    break;
                case 'delete':
                    $results = $this->bulkDelete($documentUids);
                    break;
                case 'reset':
                    $results = $this->bulkReset($documentUids);
                    break;
                default:
                    return $this->jsonArrayResponse(['success' => false, 'message' => 'Invalid bulk action']);
            }

            return $this->jsonArrayResponse([
                'success' => true,
                'results' => $results,
                'message' => "Bulk action completed: {$results['success']} successful, {$results['failed']} failed"
            ]);

        } catch (\Exception $e) {
            return $this->jsonArrayResponse(['success' => false, 'message' => 'Bulk operation failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Bulk processing dokumenata
     *
     * @param array $documentUids
     * @return array
     */
    protected function bulkProcess(array $documentUids): array
    {
        $results = ['success' => 0, 'failed' => 0, 'messages' => []];
        
        foreach ($documentUids as $uid) {
            try {
                $document = $this->documentRepository->findByUid($uid);
                if (!$document) {
                    $results['failed']++;
                    $results['messages'][] = "Document {$uid} not found";
                    continue;
                }

                if ($this->pdfProcessorService->processDocument($document)) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                    $results['messages'][] = "Processing failed for document: {$document->getTitle()}";
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['messages'][] = "Error processing document {$uid}: {$e->getMessage()}";
            }
        }
        
        return $results;
    }

    /**
     * Bulk brisanje dokumenata
     *
     * @param array $documentUids
     * @return array
     */
    protected function bulkDelete(array $documentUids): array
    {
        $results = ['success' => 0, 'failed' => 0, 'messages' => []];
        
        foreach ($documentUids as $uid) {
            try {
                $document = $this->documentRepository->findByUid($uid);
                if (!$document) {
                    $results['failed']++;
                    $results['messages'][] = "Document {$uid} not found";
                    continue;
                }

                $this->pdfProcessorService->deleteProcessedImages($document);
                $this->documentRepository->remove($document);
                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['messages'][] = "Error deleting document {$uid}: {$e->getMessage()}";
            }
        }
        
        $this->persistenceManager->persistAll();
        return $results;
    }

    /**
     * Bulk reset dokumenata na pending status
     *
     * @param array $documentUids
     * @return array
     */
    protected function bulkReset(array $documentUids): array
    {
        $results = ['success' => 0, 'failed' => 0, 'messages' => []];
        
        foreach ($documentUids as $uid) {
            try {
                $document = $this->documentRepository->findByUid($uid);
                if (!$document) {
                    $results['failed']++;
                    $results['messages'][] = "Document {$uid} not found";
                    continue;
                }

                $document->setStatus(FlipbookDocument::STATUS_PENDING);
                $document->setProcessedImages([]);
                $document->setProcessingLog('');
                $this->documentRepository->update($document);
                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['messages'][] = "Error resetting document {$uid}: {$e->getMessage()}";
            }
        }
        
        $this->persistenceManager->persistAll();
        return $results;
    }

    /**
     * Dodaj preview assets
     *
     * @param array $flipbookConfig
     * @return void
     */
    protected function addPreviewAssets(array $flipbookConfig): void
    {
        $this->pageRenderer->addCssFile(
            'EXT:flipbook_converter/Resources/Public/CSS/flipbook.css'
        );
        
        $this->pageRenderer->addJsFooterFile(
            'EXT:flipbook_converter/Resources/Public/JavaScript/FlipbookRenderer.js'
        );
        
        $configJson = json_encode($flipbookConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        
        $script = "
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof FlipbookRenderer !== 'undefined') {
                new FlipbookRenderer({$configJson});
            }
        });
        ";
        
        $this->pageRenderer->addJsFooterInlineCode(
            'flipbook-preview-' . $flipbookConfig['documentUid'],
            $script
        );
    }

    /**
     * Show upload form
     */
    public function uploadFormAction(): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        
        $moduleTemplate->assignMultiple([
            'action' => 'upload',
            'returnUrl' => $this->request->getArgument('returnUrl') ?? ''
        ]);
        
        return $moduleTemplate->renderResponse('Backend/UploadForm');
    }

    /**
     * Show upload form
     */
public function uploadAction(): ResponseInterface
{
    $moduleTemplate = $this->moduleTemplateFactory->create($this->request);

    // Check if form was submitted
    if ($this->request->getMethod() === 'POST') {
        try {
            $uploadedFiles = $this->request->getUploadedFiles();

            if (isset($uploadedFiles['pdfFile'])) {
                $pdfFile = $uploadedFiles['pdfFile'];

                if ($pdfFile->getError() === UPLOAD_ERR_OK) {
                    // Process upload
                    $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
                    $storage = $resourceFactory->getDefaultStorage();

                    try {
                        $folder = $storage->getFolder('flipbook_uploads');
                    } catch (\Exception $e) {
                        $folder = $storage->createFolder('flipbook_uploads');
                    }

                    $newFile = $storage->addFile(
                        $pdfFile->getTemporaryFileName(),
                        $folder,
                        $pdfFile->getClientFilename()
                    );

                    $fileReference = $this->createFileReference($newFile);

                    $document = new FlipbookDocument();
                    $document->setTitle($this->request->getArgument('title') ?: $pdfFile->getClientFilename());
                    $document->setDescription($this->request->getArgument('description') ?: '');
                    $document->setPdfFile($fileReference);
                    $document->setStatus(0);
                    $document->setPid($this->getStoragePid());

                    $this->documentRepository->add($document);
                    $this->persistenceManager->persistAll();

                    $this->addFlashMessage(
                        'PDF uploaded successfully!',
                        'Success',
                        ContextualFeedbackSeverity::OK
                    );

                    // Proveri da li treba odmah procesirati
                    if ($this->request->hasArgument('processImmediately') &&
                        $this->request->getArgument('processImmediately') == '1') {

                        // VAŽNO: Ponovo učitaj dokument nakon persist
                        $persistedDocument = $this->documentRepository->findByUid($document->getUid());

                        if ($persistedDocument) {
                            $this->addFlashMessage(
                                sprintf('Starting processing for document UID: %d', $persistedDocument->getUid()),
                                'Processing Started',
                                ContextualFeedbackSeverity::INFO
                            );

                            try {
                                // Pozovi processDocument samo jednom
                                $result = $this->pdfProcessorService->processDocument($persistedDocument);

                                if ($result) {
                                    $this->addFlashMessage(
                                        'Document processed successfully!',
                                        'Success',
                                        ContextualFeedbackSeverity::OK
                                    );
                                } else {
                                    $this->addFlashMessage(
                                        'Processing failed - check logs',
                                        'Warning',
                                        ContextualFeedbackSeverity::WARNING
                                    );
                                }
                            } catch (\Exception $e) {
                                $this->addFlashMessage(
                                    'Processing error: ' . $e->getMessage(),
                                    'Error',
                                    ContextualFeedbackSeverity::ERROR
                                );
                            }
                        }
                    }

                    return $this->redirect('list');
                }
            }
        } catch (\Exception $e) {
            $this->addFlashMessage(
                'Upload failed: ' . $e->getMessage(),
                'Error',
                ContextualFeedbackSeverity::ERROR
            );
        }
    }

    // Show upload form
    return $moduleTemplate->renderResponse('Backend/Upload');
}
    /**
     * Create FileReference from File
     * 
     * @param \TYPO3\CMS\Core\Resource\File $file
     * @return \TYPO3\CMS\Extbase\Domain\Model\FileReference
     */
    protected function createFileReference(\TYPO3\CMS\Core\Resource\File $file): \TYPO3\CMS\Extbase\Domain\Model\FileReference
    {
        $fileReference = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Domain\Model\FileReference::class);
        
        // Create core FileReference
        $coreFileReference = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\FileReference::class, [
            'uid_local' => $file->getUid(),
            'uid_foreign' => 0, // Will be set when document is saved
            'uid' => 0,
            'pid' => $this->getStoragePid(),
        ]);
        
        $fileReference->setOriginalResource($coreFileReference);
        
        return $fileReference;
    }

    /**
     * Get storage PID for new records
     * 
     * @return int
     */
    protected function getStoragePid(): int
    {
        // Get from TypoScript or use current page
        return (int)($this->settings['storagePid'] ?? $GLOBALS['TSFE']->id ?? 0);
    }

    /**
     * Helper method to get available storage folders
     */
    protected function getStorageFolders(): array
    {
        $folders = [];
        $storages = $this->storageRepository->findAll();
        
        foreach ($storages as $storage) {
            if ($storage->isOnline() && $storage->isBrowsable()) {
                $rootFolder = $storage->getRootLevelFolder();
                $folders[] = [
                    'uid' => $storage->getUid(),
                    'name' => $storage->getName(),
                    'folder' => $rootFolder->getIdentifier()
                ];
            }
        }
        
        return $folders;
    }

    /**
     * Helper method to get return URL
     */
    protected function getReturnUrl(): string
    {
        return $this->request->hasArgument('returnUrl') 
            ? $this->request->getArgument('returnUrl')
            : $this->uriBuilder->reset()->setCreateAbsoluteUri(true)->uriFor('list');
    }

    /**
     * Show statistics
     */
    public function statisticsAction(): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        
        // Get statistics
        $totalDocuments = $this->documentRepository->countAll();
        //$documentsByStatus = $this->documentRepository->countByStatus();
        $documentsByStatus = [
            FlipbookDocument::STATUS_PENDING => $this->documentRepository->countByStatus(FlipbookDocument::STATUS_PENDING),
            FlipbookDocument::STATUS_PROCESSING => $this->documentRepository->countByStatus(FlipbookDocument::STATUS_PROCESSING),
            FlipbookDocument::STATUS_COMPLETED => $this->documentRepository->countByStatus(FlipbookDocument::STATUS_COMPLETED),
            FlipbookDocument::STATUS_ERROR => $this->documentRepository->countByStatus(FlipbookDocument::STATUS_ERROR),
        ];
        // Ensure all statuses are present         
        // Calculate processing stats with null coalescing
        $processingStats = [
            'totalProcessed' => $documentsByStatus[2] ?? 0,
            'totalFailed' => $documentsByStatus[3] ?? 0,
            'totalPending' => $documentsByStatus[0] ?? 0,
            'totalProcessing' => $documentsByStatus[1] ?? 0,
            'successRate' => $totalDocuments > 0 
                ? round(((($documentsByStatus[2] ?? 0) / $totalDocuments) * 100), 2) 
                : 0
        ];
        
        // Get recent documents
        $recentDocuments = $this->documentRepository->findRecent(10);
        
        // Get storage usage
        $storageStats = $this->calculateStorageStats();
        
        // Assign to view
        $moduleTemplate->assign('totalDocuments', $totalDocuments);
        $moduleTemplate->assign('documentsByStatus', $documentsByStatus);
        $moduleTemplate->assign('processingStats', $processingStats);
        $moduleTemplate->assign('recentDocuments', $recentDocuments);
        $moduleTemplate->assign('storageStats', $storageStats);
        
        return $moduleTemplate->renderResponse('Backend/Statistics');
    }

    /**
     * Calculate storage statistics
     */
    protected function calculateStorageStats(): array
    {
        $totalSize = 0;
        $totalPages = 0;
        
        $documents = $this->documentRepository->findAll();
        foreach ($documents as $document) {
            if ($document->getPdfFile()) {
                $totalSize += $document->getPdfFile()->getSize();
            }
            $totalPages += $document->getPageCount();
        }
        
        return [
            'totalSize' => $this->formatBytes($totalSize),
            'totalPages' => $totalPages,
            'averageSize' => count($documents) > 0 
                ? $this->formatBytes($totalSize / count($documents)) 
                : '0 B',
            'averagePages' => count($documents) > 0 
                ? round($totalPages / count($documents), 1) 
                : 0
        ];
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    public function reprocessAction(FlipbookDocument $document): ResponseInterface
    {
        try {
            if ($this->pdfProcessorService->processDocument($document)) {
                $this->addFlashMessage(
                    'Document has been reprocessed successfully.',
                    'Success',
                    ContextualFeedbackSeverity::OK
                );
            } else {
                $this->addFlashMessage(
                    'Reprocessing failed. Check the log for details.',
                    'Error',
                    ContextualFeedbackSeverity::ERROR
                );
            }
        } catch (\Throwable $e) {
            $this->addFlashMessage(
                'Exception during reprocessing: ' . $e->getMessage(),
                'Exception',
                ContextualFeedbackSeverity::ERROR
            );
        }

        return $this->redirect('show', null, null, ['document' => $document]);
    }
    
    public function bulkActionAction(): ResponseInterface
    {
        $documentIds = $this->request->getArgument('documents') ?? [];
        $action = $this->request->getArgument('bulkAction');
        
        if ($action === 'process' && !empty($documentIds)) {
            $processed = 0;
            $errors = 0;
            
            foreach ($documentIds as $documentId) {
                $document = $this->documentRepository->findByUid((int)$documentId);
                if ($document && ($document->getStatus() === FlipbookDocument::STATUS_PENDING || 
                                $document->getStatus() === FlipbookDocument::STATUS_ERROR)) {
                    try {
                        $result = $this->pdfProcessorService->processDocument($document);
                        if ($result) {
                            $processed++;
                        } else {
                            $errors++;
                        }
                    } catch (\Exception $e) {
                        $errors++;
                        $this->logger->error('Bulk processing failed for document', [
                            'documentId' => $documentId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
            
            if ($processed > 0) {
                $this->addFlashMessage(
                    sprintf('Successfully started processing for %d document(s)', $processed),
                    'Success',
                    ContextualFeedbackSeverity::OK
                );
            }
            
            if ($errors > 0) {
                $this->addFlashMessage(
                    sprintf('Failed to process %d document(s)', $errors),
                    'Error',
                    ContextualFeedbackSeverity::ERROR
                );
            }
        }
        
        return $this->redirect('statistics');
    }

    /**
     * Process single document
     */
    public function processSingleAction(): ResponseInterface
    {
        // Proveri da li postoji argument
        if (!$this->request->hasArgument('documentUid')) {
            $this->addFlashMessage(
                'No document selected for processing',
                'Error',
                ContextualFeedbackSeverity::ERROR
            );
            return $this->redirect('list');
        }
        
        $documentUid = $this->request->getArgument('documentUid');
        
        try {
            $document = $this->documentRepository->findByUid((int)$documentUid);
            
            if (!$document) {
                throw new \Exception('Document not found');
            }
            
            // Debug info
            $this->addFlashMessage(
                sprintf('Starting to process document: %s (UID: %d, Status: %d)', 
                    $document->getTitle(), 
                    $document->getUid(), 
                    $document->getStatus()
                ),
                'Debug Info',
                ContextualFeedbackSeverity::INFO
            );
            
            $result = $this->pdfProcessorService->processDocument($document);
            
            if ($result) {
                $this->addFlashMessage(
                    'Document processed successfully!',
                    'Success',
                    ContextualFeedbackSeverity::OK
                );
            } else {
                $this->addFlashMessage(
                    'Processing failed - check logs',
                    'Error',
                    ContextualFeedbackSeverity::ERROR
                );
            }
            
        } catch (\Exception $e) {
            $this->addFlashMessage(
                'Error: ' . $e->getMessage(),
                'Processing Error',
                ContextualFeedbackSeverity::ERROR
            );
        }
        
        return $this->redirect('list');
    }

    /**
     * Return JSON response
     */
    protected function jsonResponse(?string $json = null): ResponseInterface
    {
        return parent::jsonResponse($json);
    }

    /**
     * Helper method to return JSON response with array data
     */
    protected function jsonArrayResponse(array $data): ResponseInterface
    {
        return $this->jsonResponse(json_encode($data));
    }
}