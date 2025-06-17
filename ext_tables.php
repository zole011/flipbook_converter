<?php
defined('TYPO3') or die();

(function () {
    // Register backend module icon
    $iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
        \TYPO3\CMS\Core\Imaging\IconRegistry::class
    );
    
    $iconRegistry->registerIcon(
        'extension-flipbook-converter',
        \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        ['source' => 'EXT:flipbook_converter/Resources/Public/Icons/Extension.svg']
    );
    
    $iconRegistry->registerIcon(
        'content-flipbook',
        \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        ['source' => 'EXT:flipbook_converter/Resources/Public/Icons/ContentElement.svg']
    );

    // Add content element wizard item
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig(
        '@import "EXT:flipbook_converter/Configuration/TypoScript/PageTSconfig/NewContentElementWizard.typoscript"'
    );

    // Add TypoScript
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile(
        'flipbook_converter',
        'Configuration/TypoScript/',
        'Flipbook Converter'
    );

})();