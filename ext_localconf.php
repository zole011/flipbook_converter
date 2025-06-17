<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use Gmbit\FlipbookConverter\Controller\FlipbookController;

defined('TYPO3') or die();

(static function (): void {
    $extensionKey = 'flipbook_converter';
    
    // Plugin konfiguracija
    ExtensionUtility::configurePlugin(
        'FlipbookConverter',
        'Flipbook',
        [
            FlipbookController::class => 'show, render',
        ],
        // Non-cacheable actions
        [
            FlipbookController::class => '',
        ]
    );

    // Content Element registracija
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig(
        '@import "EXT:flipbook_converter/Configuration/TypoScript/PageTSconfig/NewContentElementWizard.typoscript"'
    );

    // Backend module registracija
    ExtensionManagementUtility::addTypoScriptSetup(
        '@import "EXT:flipbook_converter/Configuration/TypoScript/setup.typoscript"'
    );

    // Scheduler task registracija (za batch processing)
    if (ExtensionManagementUtility::isLoaded('scheduler')) {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\Gmbit\FlipbookConverter\Task\ProcessPdfTask::class] = [
            'extension' => $extensionKey,
            'title' => 'LLL:EXT:' . $extensionKey . '/Resources/Private/Language/locallang.xlf:task.processPdf.title',
            'description' => 'LLL:EXT:' . $extensionKey . '/Resources/Private/Language/locallang.xlf:task.processPdf.description',
        ];
    }

    // Hook za file processing
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processUpload'][$extensionKey] = 
        \Gmbit\FlipbookConverter\Hooks\FileProcessingHook::class . '->processUploadedFile';

    // Command registracija
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][] = 
        \Gmbit\FlipbookConverter\Command\FlipbookProcessCommand::class;


    // Extension konfiguracija
    $extensionConfiguration = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
        ExtensionConfiguration::class
    );
    $flipbookConfig = $extensionConfiguration->get($extensionKey);
    
    if (!empty($flipbookConfig)) {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][$extensionKey] = $flipbookConfig;
    }

    // Cache konfiguracija
    if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['flipbook_converter'] ?? null)) {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['flipbook_converter'] = [
            'frontend' => \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
            'backend' => \TYPO3\CMS\Core\Cache\Backend\FileBackend::class,
            'options' => [
                'defaultLifetime' => 86400, // 24 sata
            ],
            'groups' => ['pages'],
        ];
    }

    // Icon registracija
    $iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
        \TYPO3\CMS\Core\Imaging\IconRegistry::class
    );
    $iconRegistry->registerIcon(
        'flipbook-converter',
        \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        ['source' => 'EXT:flipbook_converter/Resources/Public/Images/Icons/flipbook-icon.svg']
    );
    $iconRegistry->registerIcon(
        'content-flipbook',
        \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        ['source' => 'EXT:flipbook_converter/Resources/Public/Images/Icons/content-element-icon.svg']
    );

// Registruj PDF kao format koji može biti procesiran
$GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'] .= ',pdf,ai';

// Osiguraj da je PDF podržan
if (!str_contains($GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'], 'pdf')) {
    $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'] .= ',pdf';
}

})();