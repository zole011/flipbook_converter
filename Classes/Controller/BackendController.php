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
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function showAction(): \Psr\Http\Message\ResponseInterface
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
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function renderAction(): \Psr\Http\Message\ResponseInterface
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
            'config' => array_merge($documentConfig, [
                'width' => $config['width'] ?? $documentConfig['width'],
                'height' => $config['height'] ?? $documentConfig['height'],
                'backgroundColor' => $config['backgroundColor'] ?? $documentConfig['backgroundColor'],
                'showControls' => (bool)($config['showControls'] ?? $documentConfig['showControls']),
                'showPageNumbers' => (bool)($config['showPageNumbers'] ?? $documentConfig['showPageNumbers']),
                'enableZoom' => (bool)($config['enableZoom'] ?? $documentConfig['enableZoom']),
                'enableFullscreen' => (bool)($config['enableFullscreen'] ?? $documentConfig['enableFullscreen']),
                'autoplay' => (bool)($config['autoplay'] ?? $documentConfig['autoplay']),
                'autoplayDelay' => (int)($config['autoplayDelay'] ?? $documentConfig['autoplayDelay']),
                'enableKeyboard' => (bool)($config['enableKeyboard'] ?? $documentConfig['enableKeyboard']),
                'enableTouch' => (bool)($config['enableTouch'] ?? $documentConfig['enableTouch']),
                'animationDuration' => (int)($config['animationDuration'] ?? $documentConfig['animationDuration']),
            ]),
            'ajaxUrl' => $this->uriBuilder->reset()->setTargetPageUid($this->getTypoScriptFrontendController()->id)
                ->uriFor('render', [], 'Flipbook', 'FlipbookConverter', 'Flipbook'),
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
                new FlipbookRenderer({$configJson});
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
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function jsonResponse(array $data): \Psr\Http\Message\ResponseInterface
    {
        $response = $this->responseFactory->createResponse()
            ->withHeader('Content-Type', 'application/json');
        
        $response->getBody()->write(json_encode($data));
        
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
}