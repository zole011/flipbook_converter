<?php

declare(strict_types=1);

namespace Gmbit\FlipbookConverter\Service;

use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Servis za upravljanje konfiguracijom flipbook-a
 */
class ConfigurationService implements SingletonInterface
{
    protected FlexFormService $flexFormService;

    public function __construct(FlexFormService $flexFormService)
    {
        $this->flexFormService = $flexFormService;
    }

    /**
     * Parsirati FlexForm podatke
     *
     * @param string $flexFormData
     * @return array
     */
    public function parseFlexFormData(string $flexFormData): array
    {
        if (empty($flexFormData)) {
            return $this->getDefaultConfiguration();
        }

        $parsedData = $this->flexFormService->convertFlexFormContentToArray($flexFormData);
        
        return array_merge($this->getDefaultConfiguration(), $parsedData);
    }

    /**
     * Dobiti default konfiguraciju
     *
     * @return array
     */
    public function getDefaultConfiguration(): array
    {
        return [
            // Basic settings
            'document' => 0,
            
            // Display settings
            'width' => 800,
            'height' => 600,
            'backgroundColor' => '#ffffff',
            'responsive' => true,
            
            // Controls
            'showControls' => true,
            'showPageNumbers' => true,
            'showThumbnails' => false,
            'controlsPosition' => 'bottom', // top, bottom, overlay
            
            // Navigation
            'enableKeyboard' => true,
            'enableTouch' => true,
            'enableMouseWheel' => true,
            'swipeThreshold' => 50,
            
            // Zoom and fullscreen
            'enableZoom' => true,
            'maxZoom' => 3.0,
            'enableFullscreen' => true,
            'zoomStep' => 0.1,
            
            // Animation
            'animationDuration' => 500,
            'animationType' => 'slide', // slide, fade, flip
            'easingFunction' => 'ease-in-out',
            
            // Autoplay
            'autoplay' => false,
            'autoplayDelay' => 3000,
            'autoplayPauseOnHover' => true,
            
            // Loading
            'preloadPages' => 3,
            'showLoadingIndicator' => true,
            'loadingText' => 'Loading...',
            
            // Accessibility
            'enableKeyboardNavigation' => true,
            'ariaLabels' => true,
            'altTextFromContent' => true,
            
            // Performance
            'lazyLoading' => true,
            'imageQuality' => 'high', // low, medium, high
            'cacheImages' => true,
        ];
    }

    /**
     * Validirati konfiguraciju
     *
     * @param array $config
     * @return array Validated configuration
     * @throws \InvalidArgumentException
     */
    public function validateConfiguration(array $config): array
    {
        $validated = [];
        $defaults = $this->getDefaultConfiguration();

        // Document ID validation
        $validated['document'] = (int)($config['document'] ?? 0);
        if ($validated['document'] <= 0) {
            throw new \InvalidArgumentException('Valid document ID is required');
        }

        // Dimension validation
        $validated['width'] = $this->validateDimension($config['width'] ?? $defaults['width'], 200, 2000);
        $validated['height'] = $this->validateDimension($config['height'] ?? $defaults['height'], 150, 1500);

        // Color validation
        $validated['backgroundColor'] = $this->validateColor($config['backgroundColor'] ?? $defaults['backgroundColor']);

        // Boolean settings
        $booleanSettings = [
            'responsive', 'showControls', 'showPageNumbers', 'showThumbnails',
            'enableKeyboard', 'enableTouch', 'enableMouseWheel', 'enableZoom',
            'enableFullscreen', 'autoplay', 'autoplayPauseOnHover', 'preloadPages',
            'showLoadingIndicator', 'enableKeyboardNavigation', 'ariaLabels',
            'altTextFromContent', 'lazyLoading', 'cacheImages'
        ];

        foreach ($booleanSettings as $setting) {
            $validated[$setting] = (bool)($config[$setting] ?? $defaults[$setting]);
        }

        // Numeric validations
        $validated['swipeThreshold'] = $this->validateRange((int)($config['swipeThreshold'] ?? $defaults['swipeThreshold']), 10, 200);
        $validated['maxZoom'] = $this->validateRange((float)($config['maxZoom'] ?? $defaults['maxZoom']), 1.0, 10.0);
        $validated['animationDuration'] = $this->validateRange((int)($config['animationDuration'] ?? $defaults['animationDuration']), 0, 2000);
        $validated['autoplayDelay'] = $this->validateRange((int)($config['autoplayDelay'] ?? $defaults['autoplayDelay']), 1000, 30000);
        $validated['preloadPages'] = $this->validateRange((int)($config['preloadPages'] ?? $defaults['preloadPages']), 0, 10);
        $validated['zoomStep'] = $this->validateRange((float)($config['zoomStep'] ?? $defaults['zoomStep']), 0.05, 1.0);

        // Enum validations
        $validated['controlsPosition'] = $this->validateEnum($config['controlsPosition'] ?? $defaults['controlsPosition'], ['top', 'bottom', 'overlay']);
        $validated['animationType'] = $this->validateEnum($config['animationType'] ?? $defaults['animationType'], ['slide', 'fade', 'flip']);
        $validated['easingFunction'] = $this->validateEnum($config['easingFunction'] ?? $defaults['easingFunction'], ['linear', 'ease', 'ease-in', 'ease-out', 'ease-in-out']);
        $validated['imageQuality'] = $this->validateEnum($config['imageQuality'] ?? $defaults['imageQuality'], ['low', 'medium', 'high']);

        // String settings
        $validated['loadingText'] = trim($config['loadingText'] ?? $defaults['loadingText']);
        if (empty($validated['loadingText'])) {
            $validated['loadingText'] = $defaults['loadingText'];
        }

        return $validated;
    }

    /**
     * Kreirati FlexForm XML iz konfiguracije
     *
     * @param array $config
     * @return string
     */
    public function createFlexFormXml(array $config): string
    {
        $xml = '<?xml version="1.0" encoding="utf-8" standalone="yes"?>';
        $xml .= '<T3FlexForms>';
        $xml .= '<data>';
        $xml .= '<sheet index="sDEF">';
        $xml .= '<language index="lDEF">';

        foreach ($config as $key => $value) {
            $xml .= '<field index="' . htmlspecialchars($key) . '">';
            $xml .= '<value index="vDEF">' . htmlspecialchars((string)$value) . '</value>';
            $xml .= '</field>';
        }

        $xml .= '</language>';
        $xml .= '</sheet>';
        $xml .= '</data>';
        $xml .= '</T3FlexForms>';

        return $xml;
    }

    /**
     * Dobiti konfiguraciju za JavaScript
     *
     * @param array $config
     * @return array
     */
    public function getJavaScriptConfiguration(array $config): array
    {
        return [
            'dimensions' => [
                'width' => $config['width'],
                'height' => $config['height'],
                'responsive' => $config['responsive']
            ],
            'appearance' => [
                'backgroundColor' => $config['backgroundColor'],
                'showControls' => $config['showControls'],
                'showPageNumbers' => $config['showPageNumbers'],
                'showThumbnails' => $config['showThumbnails'],
                'controlsPosition' => $config['controlsPosition']
            ],
            'navigation' => [
                'enableKeyboard' => $config['enableKeyboard'],
                'enableTouch' => $config['enableTouch'],
                'enableMouseWheel' => $config['enableMouseWheel'],
                'swipeThreshold' => $config['swipeThreshold']
            ],
            'zoom' => [
                'enableZoom' => $config['enableZoom'],
                'maxZoom' => $config['maxZoom'],
                'zoomStep' => $config['zoomStep'],
                'enableFullscreen' => $config['enableFullscreen']
            ],
            'animation' => [
                'duration' => $config['animationDuration'],
                'type' => $config['animationType'],
                'easing' => $config['easingFunction']
            ],
            'autoplay' => [
                'enabled' => $config['autoplay'],
                'delay' => $config['autoplayDelay'],
                'pauseOnHover' => $config['autoplayPauseOnHover']
            ],
            'loading' => [
                'preloadPages' => $config['preloadPages'],
                'showIndicator' => $config['showLoadingIndicator'],
                'loadingText' => $config['loadingText'],
                'lazyLoading' => $config['lazyLoading']
            ],
            'accessibility' => [
                'keyboardNavigation' => $config['enableKeyboardNavigation'],
                'ariaLabels' => $config['ariaLabels'],
                'altTextFromContent' => $config['altTextFromContent']
            ]
        ];
    }

    /**
     * Validirati dimenziju
     *
     * @param mixed $value
     * @param int $min
     * @param int $max
     * @return int
     */
    protected function validateDimension($value, int $min, int $max): int
    {
        $value = (int)$value;
        return max($min, min($max, $value));
    }

    /**
     * Validirati color vrednost
     *
     * @param mixed $value
     * @return string
     */
    protected function validateColor($value): string
    {
        $value = (string)$value;
        
        // Hex color validation
        if (preg_match('/^#[a-fA-F0-9]{6}$/', $value)) {
            return $value;
        }
        
        // RGB/RGBA validation
        if (preg_match('/^rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*(,\s*[\d.]+\s*)?\)$/', $value)) {
            return $value;
        }
        
        // Named colors
        $namedColors = ['white', 'black', 'red', 'green', 'blue', 'yellow', 'cyan', 'magenta', 'gray', 'transparent'];
        if (in_array(strtolower($value), $namedColors)) {
            return strtolower($value);
        }
        
        return '#ffffff'; // Default to white
    }

    /**
     * Validirati range vrednost
     *
     * @param mixed $value
     * @param float $min
     * @param float $max
     * @return float
     */
    protected function validateRange($value, float $min, float $max): float
    {
        $value = (float)$value;
        return max($min, min($max, $value));
    }

    /**
     * Validirati enum vrednost
     *
     * @param mixed $value
     * @param array $allowedValues
     * @return string
     */
    protected function validateEnum($value, array $allowedValues): string
    {
        $value = (string)$value;
        return in_array($value, $allowedValues) ? $value : $allowedValues[0];
    }

    /**
     * Mergirati konfiguracije sa prioritetom
     *
     * @param array $baseConfig
     * @param array $overrideConfig
     * @return array
     */
    public function mergeConfigurations(array $baseConfig, array $overrideConfig): array
    {
        $merged = $baseConfig;
        
        foreach ($overrideConfig as $key => $value) {
            if ($value !== null && $value !== '') {
                $merged[$key] = $value;
            }
        }
        
        return $merged;
    }

    /**
     * Dobiti responsive breakpoints
     *
     * @return array
     */
    public function getResponsiveBreakpoints(): array
    {
        return [
            'mobile' => [
                'maxWidth' => 767,
                'config' => [
                    'width' => '100%',
                    'height' => 'auto',
                    'showThumbnails' => false,
                    'controlsPosition' => 'overlay'
                ]
            ],
            'tablet' => [
                'maxWidth' => 1024,
                'config' => [
                    'width' => '100%',
                    'height' => 'auto',
                    'showThumbnails' => true,
                    'controlsPosition' => 'bottom'
                ]
            ],
            'desktop' => [
                'minWidth' => 1025,
                'config' => [
                    'showThumbnails' => true,
                    'controlsPosition' => 'bottom'
                ]
            ]
        ];
    }
}