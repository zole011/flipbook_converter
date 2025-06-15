<?php

declare(strict_types=1);

namespace Gmbit\FlipbookConverter\Controller;

use Gmbit\FlipbookConverter\Domain\Model\FlipbookDocument;
use Gmbit\FlipbookConverter\Domain\Repository\FlipbookDocumentRepository;
use Gmbit\FlipbookConverter\Service\ConfigurationService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use Psr\Http\Message\ResponseInterface;

/**
 * Frontend Controller za Flipbook prikaz
 */
class FlipbookController extends ActionController
{
    protected FlipbookDocumentRepository $documentRepository;
    protected ConfigurationService $configurationService;
    protected PageRenderer $pageRenderer;

    public function __construct(
        FlipbookDocumentRepository $documentRepository,
        ConfigurationService $configurationService,
        PageRenderer $pageRenderer
    ) {
        $this->documentRepository = $documentRepository;
        $this->configurationService = $configurationService;
        $this->pageRenderer = $pageRenderer;
    }

    /**
     * Glavna akcija za prikaz flipbook-a
     *
     * @return ResponseInterface
     */
    public function showAction(): ResponseInterface
    {
        // Dobiti konfiguraciju iz FlexForm
        $flexFormData = $this->configurationManager->getContentObject()->data['pi_flexform'] ?? '';
        $config = $this->configurationService->parseFlexFormData($flexFormData);
        
        $documentUid = (int)($config['document'] ?? 0);
        
        if (!$documentUid) {
            $this->addFlashMessage(
                'No document selected for flipbook display.',
                'Configuration Error',
                \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR
            );
            return $this->htmlResponse('');
        }

        $document = $this->documentRepository->findByUid($documentUid);
        
        if (!$document || !$document->isCompleted()) {
            $this->addFlashMessage(
                'Selected document is not available or not processed yet.',
                'Document Error',
                \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR
            );
            return $this->htmlResponse('');
        }

        // Pripremi konfiguraciju za JavaScript
        $flipbookConfig = $this->prepareFlipbookConfiguration($document, $config);
        
        // Dodaj CSS i JavaScript fajlove
        $this->addAssetsToPage();
        
        // Dodaj inline JavaScript konfiguraciju
        $this->addInlineJavaScript($flipbookConfig);

        $this->view->assignMultiple([
            'document' => $document,
            'config' => $config,
            'flipbookConfig' => $flipbookConfig,
            'images' => $document->getProcessedImages(),
            'uniqueId' => 'flipbook-' . $documentUid . '-' . uniqid()
        ]);

        return $this->htmlResponse();
    }

    /**
     * AJAX akcija za renderovanje flipbook-a
     *
     * @return ResponseInterface
     */
    public function renderAction(): ResponseInterface
    {
        $documentUid = (int)($this->request->getArgument('document') ?? 0);
        $page = (int)($this->request->getArgument('page') ?? 1);
        
        if (!$documentUid) {
            return $this->jsonResponse(['error' => 'Document UID required']);
        }

        $document = $this->documentRepository->findByUid($documentUid);
        
        if (!$document || !$document->isCompleted()) {
            return $this->jsonResponse(['error' => 'Document not found or not processed']);
        }

        $images = $document->getProcessedImages();
        
        if ($page < 1 || $page > count($images)) {
            return $this->jsonResponse(['error' => 'Invalid page number']);
        }

        $pageData = $images[$page - 1] ?? null;
        
        if (!$pageData) {
            return $this->jsonResponse(['error' => 'Page data not found']);
        }

        return $this->jsonResponse([
            'success' => true,
            'page' => $page,
            'totalPages' => count($images),
            'image' => $pageData,
            'document' => [
                'uid' => $document->getUid(),
                'title' => $document->getTitle(),
                'description' => $document->getDescription()
            ]
        ]);
    }

    /**
     * Akcija za lazy loading slika
     *
     * @return ResponseInterface
     */
    public function loadImageAction(): ResponseInterface
    {
        $documentUid = (int)($this->request->getArgument('document') ?? 0);
        $pageNumber = (int)($this->request->getArgument('page') ?? 1);
        $imageType = $this->request->getArgument('type') ?? 'full'; // full, thumbnail
        
        if (!$documentUid) {
            return $this->jsonResponse(['error' => 'Document UID required']);
        }

        $document = $this->documentRepository->findByUid($documentUid);
        
        if (!$document || !$document->isCompleted()) {
            return $this->jsonResponse(['error' => 'Document not found or not processed']);
        }

        $images = $document->getProcessedImages();
        $pageIndex = $pageNumber - 1;
        
        if (!isset($images[$pageIndex])) {
            return $this->jsonResponse(['error' => 'Page not found']);
        }

        $imageData = $images[$pageIndex];
        
        $responseData = [
            'success' => true,
            'page' => $pageNumber,
            'url' => $imageType === 'thumbnail' && isset($imageData['thumbnail']) 
                ? $imageData['thumbnail']['publicUrl'] 
                : $imageData['publicUrl'],
            'width' => $imageType === 'thumbnail' && isset($imageData['thumbnail'])
                ? $imageData['thumbnail']['width']
                : $imageData['width'],
            'height' => $imageType === 'thumbnail' && isset($imageData['thumbnail'])
                ? $imageData['thumbnail']['height']
                : $imageData['height']
        ];

        return $this->jsonResponse($responseData);
    }

    /**
     * Akcija za search kroz dokument
     *
     * @return ResponseInterface
     */
    public function searchAction(): ResponseInterface
    {
        $documentUid = (int)($this->request->getArgument('document') ?? 0);
        $searchTerm = trim($this->request->getArgument('query') ?? '');
        
        if (!$documentUid || empty($searchTerm)) {
            return $this->jsonResponse(['error' => 'Document UID and search query required']);
        }

        $document = $this->documentRepository->findByUid($documentUid);
        
        if (!$document || !$document->isCompleted()) {
            return $this->jsonResponse(['error' => 'Document not found or not processed']);
        }

        // Za sada vraćamo prazan rezultat - OCR funkcionalnost se može dodati kasnije
        return $this->jsonResponse([
            'success' => true,
            'query' => $searchTerm,
            'results' => [],
            'totalResults' => 0,
            'message' => 'Search functionality will be available in future versions'
        ]);
    }

    /**
     * Pripremi konfiguraciju za flipbook JavaScript
     *
     * @param FlipbookDocument $document
     * @param array $config
     * @return array
     */
    protected function prepareFlipbookConfiguration(FlipbookDocument $document, array $config): array
    {
        $documentConfig = $document->getFlipbookConfig();
        $images = $document->getProcessedImages();
        
        // Mergirati konfiguracije: document config < FlexForm config
        $mergedConfig = $this->configurationService->mergeConfigurations($documentConfig, $config);
        
        return [
            'documentUid' => $document->getUid(),
            'totalPages' => count($images),
            'images' => array_map(function($image) {
                return [
                    'page' => $image['page'],
                    'src' => $image['publicUrl'],
                    'width' => $image['width'],
                    'height' => $image['height'],
                    'thumbnail' => $image['thumbnail'] ?? null
                ];
            }, $images),
            'config' => $this->configurationService->getJavaScriptConfiguration($mergedConfig),
            'urls' => [
                'ajax' => $this->uriBuilder->reset()
                    ->setTargetPageUid($this->getTypoScriptFrontendController()->id)
                    ->uriFor('render', [], 'Flipbook', 'FlipbookConverter', 'Flipbook'),
                'loadImage' => $this->uriBuilder->reset()
                    ->setTargetPageUid($this->getTypoScriptFrontendController()->id)
                    ->uriFor('loadImage', [], 'Flipbook', 'FlipbookConverter', 'Flipbook'),
                'search' => $this->uriBuilder->reset()
                    ->setTargetPageUid($this->getTypoScriptFrontendController()->id)
                    ->uriFor('search', [], 'Flipbook', 'FlipbookConverter', 'Flipbook')
            ],
            'labels' => [
                'loading' => $mergedConfig['loadingText'] ?? 'Loading...',
                'error' => 'Error loading page',
                'prevPage' => 'Previous Page',
                'nextPage' => 'Next Page',
                'zoomIn' => 'Zoom In',
                'zoomOut' => 'Zoom Out',
                'fullscreen' => 'Fullscreen',
                'exitFullscreen' => 'Exit Fullscreen',
                'pageOf' => 'Page {current} of {total}'
            ]
        ];
    }

    /**
     * Dodaj CSS i JavaScript assets na stranicu
     *
     * @return void
     */
    protected function addAssetsToPage(): void
    {
        // CSS fajlovi
        $this->pageRenderer->addCssFile(
            'EXT:flipbook_converter/Resources/Public/CSS/flipbook.css',
            'stylesheet',
            'all',
            '',
            false
        );

        // JavaScript fajlovi
        $this->pageRenderer->addJsFooterFile(
            'EXT:flipbook_converter/Resources/Public/JavaScript/FlipbookRenderer.js',
            'text/javascript',
            false,
            false,
            '',
            true
        );
        
        $this->pageRenderer->addJsFooterFile(
            'EXT:flipbook_converter/Resources/Public/JavaScript/FlipbookControls.js',
            'text/javascript',
            false,
            false,
            '',
            true
        );
    }

    /**
     * Dodaj inline JavaScript konfiguraciju
     *
     * @param array $config
     * @return void
     */
    protected function addInlineJavaScript(array $config): void
    {
        $configJson = json_encode($config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        
        $script = "
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof FlipbookRenderer !== 'undefined') {
                window.flipbookConfig_{$config['documentUid']} = {$configJson};
                new FlipbookRenderer(window.flipbookConfig_{$config['documentUid']});
            } else {
                console.error('FlipbookRenderer not loaded');
            }
        });
        ";
        
        $this->pageRenderer->addJsFooterInlineCode(
            'flipbook-config-' . $config['documentUid'],
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
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
        
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        
        return $response;
    }

    /**
     * Dobiti TypoScriptFrontendController
     *
     * @return TypoScriptFrontendController
     */
    protected function getTypoScriptFrontendController(): TypoScriptFrontendController
    {
        return $GLOBALS['TSFE'];
    }

    /**
     * Validirati request parametre
     *
     * @param array $requiredParams
     * @return array|null Vraća null ako validacija nije uspešna
     */
    protected function validateRequestParams(array $requiredParams): ?array
    {
        $params = [];
        
        foreach ($requiredParams as $param => $type) {
            $value = $this->request->hasArgument($param) ? $this->request->getArgument($param) : null;
            
            if ($value === null) {
                return null;
            }
            
            switch ($type) {
                case 'int':
                    $params[$param] = (int)$value;
                    break;
                case 'string':
                    $params[$param] = (string)$value;
                    break;
                case 'bool':
                    $params[$param] = (bool)$value;
                    break;
                default:
                    $params[$param] = $value;
                    break;
            }
        }
        
        return $params;
    }

    /**
     * Error handling za AJAX akcije
     *
     * @param string $message
     * @param int $code
     * @return ResponseInterface
     */
    protected function errorResponse(string $message, int $code = 400): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($code)
            ->withHeader('Content-Type', 'application/json');
        
        $response->getBody()->write(json_encode([
            'error' => true,
            'message' => $message,
            'code' => $code
        ]));
        
        return $response;
    }
}