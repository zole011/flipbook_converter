<?php

declare(strict_types=1);

namespace Gmbit\FlipbookConverter\Controller;

use Gmbit\FlipbookConverter\Domain\Model\FlipbookDocument;
use Gmbit\FlipbookConverter\Domain\Repository\FlipbookDocumentRepository;
use Gmbit\FlipbookConverter\Service\PdfProcessorService;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use Psr\Http\Message\ResponseInterface;

/**
 * Backend Controller za upravljanje flipbook dokumentima
 */
class BackendController extends ActionController
{
    protected FlipbookDocumentRepository $documentRepository;
    protected PdfProcessorService $pdfProcessorService;
    protected ModuleTemplateFactory $moduleTemplateFactory;
    protected PersistenceManager $persistenceManager;
    protected PageRenderer $pageRenderer;

    public function __construct(
        FlipbookDocumentRepository $documentRepository,
        PdfProcessorService $pdfProcessorService,
        ModuleTemplateFactory $moduleTemplateFactory,
        PersistenceManager $persistenceManager,
        PageRenderer $pageRenderer
    ) {
        $this->documentRepository = $documentRepository;
        $this->pdfProcessorService = $pdfProcessorService;
        $this->moduleTemplateFactory = $moduleTemplateFactory;
        $this->persistenceManager = $persistenceManager;
        $this->pageRenderer = $pageRenderer;
    }

    /**
     * Inicijalizacija akcija
     */
    protected function initializeAction(): void
    {
        parent::initializeAction();
        
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
        $filter = $this->request->hasArgument('filter') ? $this->request->getArgument('filter') : 'all';
        $search = $this->request->hasArgument('search') ? trim($this->request->getArgument('search')) : '';
        
        // Dobiti dokumente na osnovu filtera
        switch ($filter) {
            case 'pending':
                $documents = $this->documentRepository->findPendingDocuments();
                break;
            case 'processing':
                $documents = $this->documentRepository->findProcessingDocuments();
                break;
            case 'completed':
                $documents = $this->documentRepository->findCompletedDocuments();
                break;
            case 'error':
                $documents = $this->documentRepository->findErrorDocuments();
                break;
            default:
                if (!empty($search)) {
                    $documents = $this->documentRepository->searchByTitle($search);
                } else {
                    $documents = $this->documentRepository->findAll();
                }
                break;
        }

        // Statistike
        $statistics = $this->documentRepository->getStatistics();

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setTitle('Flipbook Documents');
        
        $this->view->assignMultiple([
            'documents' => $documents,
            'statistics' => $statistics,
            'currentFilter' => $filter,
            'searchTerm' => $search,
            'totalFileSize' => $this->documentRepository->getTotalFileSize(),
            'averageProcessingTime' => $this->documentRepository->getAverageProcessingTime()
        ]);

        $moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * Prikaz pojedinačnog dokumenta
     *
     * @param FlipbookDocument|null $document
     * @return ResponseInterface
     */
    public function showAction(?FlipbookDocument $document = null): ResponseInterface
    {
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

        $moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * Forma za kreiranje novog dokumenta
     *
     * @param FlipbookDocument|null $document
     * @return ResponseInterface
     */
    public function newAction(?FlipbookDocument $document = null): ResponseInterface
    {
        if (!$document) {
            $document = GeneralUtility::makeInstance(FlipbookDocument::class);
        }

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setTitle('New Flipbook Document');
        
        $this->view->assign('document', $document);

        $moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($moduleTemplate->renderContent());
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
        $moduleTemplate->setTitle('Edit Flipbook Document: ' . $document->getTitle());
        
        $this->view->assign('document', $document);

        $moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($moduleTemplate->renderContent());
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
     * Procesiranje/reprocessiranje dokumenta
     *
     * @param FlipbookDocument $document
     * @return ResponseInterface
     */
    public function processAction(FlipbookDocument $document): ResponseInterface
    {
        try {
            $isReprocessing = $document->isCompleted() || $document->hasError();
            
            if ($isReprocessing) {
                $success = $this->pdfProcessorService->reprocessDocument($document);
                $action = 'reprocessed';
            } else {
                $success = $this->pdfProcessorService->processDocument($document);
                $action = 'processed';
            }

            if ($success) {
                $this->addFlashMessage(
                    'Document "' . $document->getTitle() . '" has been ' . $action . ' successfully.',
                    'Processing Complete',
                    AbstractMessage::OK
                );
            } else {
                $this->addFlashMessage(
                    'Processing failed for document "' . $document->getTitle() . '". Check the processing log for details.',
                    'Processing Failed',
                    AbstractMessage::ERROR
                );
            }

        } catch (\Exception $e) {
            $this->addFlashMessage(
                'Error processing document: ' . $e->getMessage(),
                'Processing Error',
                AbstractMessage::ERROR
            );
        }

        return $this->redirect('show', null, null, ['document' => $document]);
    }

    /**
     * Preview dokumenta
     *
     * @param FlipbookDocument $document
     * @return ResponseInterface
     */
    public function previewAction(FlipbookDocument $document): ResponseInterface
    {
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

        $moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($moduleTemplate->renderContent());
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
            return $this->jsonResponse(['success' => false, 'message' => 'No documents selected']);
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
                    return $this->jsonResponse(['success' => false, 'message' => 'Invalid bulk action']);
            }

            return $this->jsonResponse([
                'success' => true,
                'results' => $results,
                'message' => "Bulk action completed: {$results['success']} successful, {$results['failed']} failed"
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => 'Bulk operation failed: ' . $e->getMessage()]);
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
     * Helper method za JSON response
     *
     * @param array $data
     * @return ResponseInterface
     */
    protected function jsonResponse(array $data): ResponseInterface
    {
        $response = $this->responseFactory->createResponse()
            ->withHeader('Content-Type', 'application/json');
        
        $response->getBody()->write(json_encode($data));
        
        return $response;
    }
}