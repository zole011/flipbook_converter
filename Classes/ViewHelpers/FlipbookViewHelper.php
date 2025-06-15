<?php

declare(strict_types=1);

namespace Gmbit\FlipbookConverter\ViewHelpers;

use Gmbit\FlipbookConverter\Domain\Model\FlipbookDocument;
use Gmbit\FlipbookConverter\Domain\Repository\FlipbookDocumentRepository;
use Gmbit\FlipbookConverter\Service\ConfigurationService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

/**
 * Flipbook ViewHelper za jednostavan prikaz flipbook-a u Fluid template-ima
 * 
 * = Primeri korišćenja =
 * 
 * <fc:flipbook document="{documentUid}" />
 * <fc:flipbook document="{documentObject}" width="800" height="600" />
 * <fc:flipbook document="123" enableZoom="false" showControls="true" />
 */
class FlipbookViewHelper extends AbstractViewHelper
{
    use CompileWithRenderStatic;

    /**
     * @var bool
     */
    protected $escapeOutput = false;

    /**
     * Initialize arguments
     */
    public function initializeArguments(): void
    {
        $this->registerArgument('document', 'mixed', 'Document UID ili FlipbookDocument objekat', true);
        $this->registerArgument('width', 'int', 'Širina flipbook-a u pikselima', false, 800);
        $this->registerArgument('height', 'int', 'Visina flipbook-a u pikselima', false, 600);
        $this->registerArgument('backgroundColor', 'string', 'Boja pozadine', false, '#ffffff');
        $this->registerArgument('showControls', 'bool', 'Prikaži kontrole', false, true);
        $this->registerArgument('showPageNumbers', 'bool', 'Prikaži brojeve stranica', false, true);
        $this->registerArgument('enableZoom', 'bool', 'Omogući zoom', false, true);
        $this->registerArgument('enableFullscreen', 'bool', 'Omogući fullscreen', false, true);
        $this->registerArgument('enableKeyboard', 'bool', 'Omogući keyboard navigaciju', false, true);
        $this->registerArgument('enableTouch', 'bool', 'Omogući touch navigaciju', false, true);
        $this->registerArgument('autoplay', 'bool', 'Automatska reprodukcija', false, false);
        $this->registerArgument('autoplayDelay', 'int', 'Vreme između stranica u ms', false, 3000);
        $this->registerArgument('animationDuration', 'int', 'Trajanje animacije u ms', false, 500);
        $this->registerArgument('lazyLoading', 'bool', 'Lazy loading slika', false, true);
        $this->registerArgument('class', 'string', 'CSS klase za container', false, '');
        $this->registerArgument('style', 'string', 'Inline CSS stilovi', false, '');
        $this->registerArgument('id', 'string', 'HTML ID atribut', false, '');
    }

    /**
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     * @return string
     */
    public static function renderStatic(
        array $arguments,
        \Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    ): string {
        try {
            // Get document
            $document = self::resolveDocument($arguments['document']);
            if (!$document || !$document->isCompleted()) {
                return self::renderError('Document not found or not processed');
            }

            // Prepare configuration
            $config = self::prepareConfiguration($document, $arguments);
            
            // Generate unique ID
            $uniqueId = $arguments['id'] ?: 'flipbook-' . $document->getUid() . '-' . uniqid();
            
            // Add assets
            self::addAssets();
            
            // Add inline configuration
            self::addInlineConfiguration($uniqueId, $config);

            // Render HTML
            return self::renderFlipbookHtml($document, $config, $uniqueId, $arguments);

        } catch (\Exception $e) {
            return self::renderError('Flipbook rendering failed: ' . $e->getMessage());
        }
    }

    /**
     * Resolve document from argument
     *
     * @param mixed $documentArgument
     * @return FlipbookDocument|null
     */
    protected static function resolveDocument($documentArgument): ?FlipbookDocument
    {
        if ($documentArgument instanceof FlipbookDocument) {
            return $documentArgument;
        }

        if (is_numeric($documentArgument)) {
            $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
            $repository = $objectManager->get(FlipbookDocumentRepository::class);
            return $repository->findByUid((int)$documentArgument);
        }

        return null;
    }

    /**
     * Prepare flipbook configuration
     *
     * @param FlipbookDocument $document
     * @param array $arguments
     * @return array
     */
    protected static function prepareConfiguration(FlipbookDocument $document, array $arguments): array
    {
        $documentConfig = $document->getFlipbookConfig();
        $images = $document->getProcessedImages();

        // Merge document config with ViewHelper arguments
        $config = array_merge($documentConfig, [
            'documentUid' => $document->getUid(),
            'totalPages' => count($images),
            'width' => $arguments['width'],
            'height' => $arguments['height'],
            'backgroundColor' => $arguments['backgroundColor'],
            'showControls' => $arguments['showControls'],
            'showPageNumbers' => $arguments['showPageNumbers'],
            'enableZoom' => $arguments['enableZoom'],
            'enableFullscreen' => $arguments['enableFullscreen'],
            'enableKeyboard' => $arguments['enableKeyboard'],
            'enableTouch' => $arguments['enableTouch'],
            'autoplay' => $arguments['autoplay'],
            'autoplayDelay' => $arguments['autoplayDelay'],
            'animationDuration' => $arguments['animationDuration'],
            'lazyLoading' => $arguments['lazyLoading'],
        ]);

        // Add images data
        $config['images'] = array_map(function($image) {
            return [
                'page' => $image['page'],
                'src' => $image['publicUrl'],
                'width' => $image['width'],
                'height' => $image['height'],
                'thumbnail' => $image['thumbnail'] ?? null
            ];
        }, $images);

        return $config;
    }

    /**
     * Add CSS and JavaScript assets
     */
    protected static function addAssets(): void
    {
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);

        // Add CSS
        $pageRenderer->addCssFile(
            'EXT:flipbook_converter/Resources/Public/CSS/flipbook.css',
            'stylesheet',
            'all',
            '',
            false
        );

        // Add JavaScript
        $pageRenderer->addJsFooterFile(
            'EXT:flipbook_converter/Resources/Public/JavaScript/FlipbookRenderer.js',
            'text/javascript',
            false,
            false,
            '',
            true
        );
    }

    /**
     * Add inline JavaScript configuration
     *
     * @param string $uniqueId
     * @param array $config
     */
    protected static function addInlineConfiguration(string $uniqueId, array $config): void
    {
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $configJson = json_encode($config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        $script = "
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof FlipbookRenderer !== 'undefined') {
                window.flipbookConfig_{$uniqueId} = {$configJson};
                new FlipbookRenderer(window.flipbookConfig_{$uniqueId});
            }
        });
        ";

        $pageRenderer->addJsFooterInlineCode(
            'flipbook-viewhelper-' . $uniqueId,
            $script
        );
    }

    /**
     * Render flipbook HTML
     *
     * @param FlipbookDocument $document
     * @param array $config
     * @param string $uniqueId
     * @param array $arguments
     * @return string
     */
    protected static function renderFlipbookHtml(FlipbookDocument $document, array $config, string $uniqueId, array $arguments): string
    {
        $cssClasses = ['flipbook-container', 'flipbook-viewhelper'];
        if (!empty($arguments['class'])) {
            $cssClasses[] = $arguments['class'];
        }

        $style = "width: {$config['width']}px; height: {$config['height']}px; background-color: {$config['backgroundColor']};";
        if (!empty($arguments['style'])) {
            $style .= ' ' . $arguments['style'];
        }

        $html = '<div class="' . implode(' ', $cssClasses) . '" ';
        $html .= 'id="' . htmlspecialchars($uniqueId) . '" ';
        $html .= 'data-document-uid="' . $document->getUid() . '" ';
        $html .= 'data-config="' . htmlspecialchars(json_encode($config)) . '" ';
        $html .= 'style="' . htmlspecialchars($style) . '">';

        // Loading indicator
        $html .= '<div class="flipbook-loading" id="loading-' . $uniqueId . '">';
        $html .= '<div class="loading-spinner"></div>';
        $html .= '<div class="loading-text">Loading...</div>';
        $html .= '</div>';

        // Flipbook viewer
        $html .= '<div class="flipbook-viewer" id="viewer-' . $uniqueId . '">';
        $html .= '<div class="flipbook-pages" id="pages-' . $uniqueId . '">';

        // Render pages
        foreach ($config['images'] as $index => $image) {
            $isFirst = $index === 0;
            $display = $isFirst ? 'block' : 'none';
            
            $html .= '<div class="flipbook-page" ';
            $html .= 'data-page="' . $image['page'] . '" ';
            $html .= 'data-src="' . htmlspecialchars($image['src']) . '" ';
            $html .= 'style="display: ' . $display . ';">';
            
            if ($isFirst || $index < ($config['preloadPages'] ?? 3)) {
                $loading = $isFirst ? 'eager' : 'lazy';
                $html .= '<img src="' . htmlspecialchars($image['src']) . '" ';
                $html .= 'alt="Page ' . $image['page'] . ' of ' . htmlspecialchars($document->getTitle()) . '" ';
                $html .= 'class="flipbook-page-image" ';
                $html .= 'loading="' . $loading . '" />';
            } else {
                $html .= '<img data-src="' . htmlspecialchars($image['src']) . '" ';
                $html .= 'alt="Page ' . $image['page'] . ' of ' . htmlspecialchars($document->getTitle()) . '" ';
                $html .= 'class="flipbook-page-image lazy" ';
                $html .= 'loading="lazy" />';
            }
            
            $html .= '</div>';
        }

        $html .= '</div>'; // pages
        $html .= '</div>'; // viewer

        // Controls (simplified version)
        if ($config['showControls']) {
            $html .= self::renderSimpleControls($uniqueId, $document);
        }

        $html .= '</div>'; // container

        return $html;
    }

    /**
     * Render simple controls for ViewHelper
     *
     * @param string $uniqueId
     * @param FlipbookDocument $document
     * @return string
     */
    protected static function renderSimpleControls(string $uniqueId, FlipbookDocument $document): string
    {
        $html = '<div class="flipbook-controls flipbook-controls-simple" id="controls-' . $uniqueId . '">';
        
        // Previous button
        $html .= '<button class="flipbook-btn flipbook-btn-prev" ';
        $html .= 'data-action="prevPage" data-target="' . $uniqueId . '" disabled>';
        $html .= '<span aria-hidden="true">‹</span> Previous';
        $html .= '</button>';
        
        // Page counter
        $html .= '<span class="flipbook-page-counter">';
        $html .= '<span class="flipbook-current-page">1</span> / ';
        $html .= '<span class="flipbook-total-pages">' . $document->getTotalPages() . '</span>';
        $html .= '</span>';
        
        // Next button
        $disabled = $document->getTotalPages() <= 1 ? ' disabled' : '';
        $html .= '<button class="flipbook-btn flipbook-btn-next" ';
        $html .= 'data-action="nextPage" data-target="' . $uniqueId . '"' . $disabled . '>';
        $html .= 'Next <span aria-hidden="true">›</span>';
        $html .= '</button>';
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Render error message
     *
     * @param string $message
     * @return string
     */
    protected static function renderError(string $message): string
    {
        return '<div class="flipbook-error alert alert-warning">' . 
               '<strong>Flipbook Error:</strong> ' . htmlspecialchars($message) . 
               '</div>';
    }
}