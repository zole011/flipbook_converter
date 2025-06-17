<?php

declare(strict_types=1);

defined('TYPO3') or die();

// Registruj Extbase plugin
\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
    'FlipbookConverter',
    'Flipbook',
    'LLL:EXT:flipbook_converter/Resources/Private/Language/locallang_db.xlf:plugin.flipbook'
);

// Dodaj CType za flipbook
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTcaSelectItem(
    'tt_content',
    'CType',
    [
        'LLL:EXT:flipbook_converter/Resources/Private/Language/locallang_db.xlf:plugin.flipbook',
        'flipbookconverter_flipbook',
        'content-flipbook'
    ],
    'textmedia',
    'after'
);

// Konfiguriši polja za flipbook plugin
$GLOBALS['TCA']['tt_content']['types']['flipbookconverter_flipbook'] = [
    'showitem' => '
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,
            --palette--;;general,
            --palette--;;headers,
        --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:tabs.plugin,
            pi_flexform,
        --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:tabs.appearance,
            --palette--;;frames,
            --palette--;;appearanceLinks,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:language,
            --palette--;;language,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
            --palette--;;hidden,
            --palette--;;access,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:categories,
            categories,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:notes,
            rowDescription,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:extended,
    ',
    'previewRenderer' => \Gmbit\FlipbookConverter\Preview\FlipbookPreviewRenderer::class,
];

// Konfiguriši FlexForm
$GLOBALS['TCA']['tt_content']['columns']['pi_flexform']['config']['ds']['*,flipbookconverter_flipbook'] = 
    'FILE:EXT:flipbook_converter/Configuration/FlexForms/FlipbookConfiguration.xml';

// Postavi ikonu za content type
$GLOBALS['TCA']['tt_content']['ctrl']['typeicon_classes']['flipbookconverter_flipbook'] = 'content-flipbook';

// Dodaj u New Content Element Wizard
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig(
    '@import \'EXT:flipbook_converter/Configuration/TsConfig/Page/NewContentElementWizard.tsconfig\''
);