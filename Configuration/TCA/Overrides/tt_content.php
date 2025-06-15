<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// Plugin registracija
ExtensionManagementUtility::addPlugin(
    [
        'LLL:EXT:flipbook_converter/Resources/Private/Language/locallang.xlf:plugin.flipbook.title',
        'flipbookconverter_flipbook',
        'content-flipbook',
    ],
    'CType',
    'flipbook_converter'
);

// TCA konfiguracija za flipbook content element
$GLOBALS['TCA']['tt_content']['types']['flipbookconverter_flipbook'] = [
    'showitem' => '
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,
            --palette--;;general,
            --palette--;;headers,
        --div--;LLL:EXT:flipbook_converter/Resources/Private/Language/locallang.xlf:tabs.flipbook_settings,
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

// FlexForm konfiguracija
$GLOBALS['TCA']['tt_content']['types']['flipbookconverter_flipbook']['pi_flexform_ds'] = 
    'FILE:EXT:flipbook_converter/Configuration/FlexForms/FlipbookConfiguration.xml';

// Dodaj polje za direktnu vezu sa flipbook dokumentom (opciono)
$tempColumns = [
    'tx_flipbookconverter_document' => [
        'exclude' => true,
        'label' => 'LLL:EXT:flipbook_converter/Resources/Private/Language/locallang_db.xlf:tt_content.tx_flipbookconverter_document',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'foreign_table' => 'tx_flipbookconverter_document',
            'foreign_table_where' => 'AND tx_flipbookconverter_document.pid=###CURRENT_PID### AND tx_flipbookconverter_document.hidden=0 AND tx_flipbookconverter_document.deleted=0 ORDER BY tx_flipbookconverter_document.title',
            'items' => [
                ['LLL:EXT:flipbook_converter/Resources/Private/Language/locallang_db.xlf:tt_content.tx_flipbookconverter_document.select', 0],
            ],
            'size' => 1,
            'maxitems' => 1,
            'minitems' => 0,
            'default' => 0,
        ],
    ],
];

ExtensionManagementUtility::addTCAcolumns('tt_content', $tempColumns);

// Dodaj u showitem za flipbook content element (opciono - alternative to FlexForm)
// Možemo koristiti i direktno polje umesto FlexForm-a za jednostavniji interface
$GLOBALS['TCA']['tt_content']['types']['flipbookconverter_flipbook_simple'] = [
    'showitem' => '
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,
            --palette--;;general,
            --palette--;;headers,
            tx_flipbookconverter_document,
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
];

// Registracija alternativnog simple content elementa
ExtensionManagementUtility::addPlugin(
    [
        'LLL:EXT:flipbook_converter/Resources/Private/Language/locallang.xlf:plugin.flipbook_simple.title',
        'flipbookconverter_flipbook_simple',
        'content-flipbook',
    ],
    'CType',
    'flipbook_converter'
);

// New Content Element Wizard konfiguracija
ExtensionManagementUtility::addPageTSConfig('
    mod.wizards.newContentElement.wizardItems.plugins {
        elements {
            flipbookconverter_flipbook {
                iconIdentifier = content-flipbook
                title = LLL:EXT:flipbook_converter/Resources/Private/Language/locallang.xlf:plugin.flipbook.title
                description = LLL:EXT:flipbook_converter/Resources/Private/Language/locallang.xlf:plugin.flipbook.description
                tt_content_defValues {
                    CType = flipbookconverter_flipbook
                }
            }
            flipbookconverter_flipbook_simple {
                iconIdentifier = content-flipbook
                title = LLL:EXT:flipbook_converter/Resources/Private/Language/locallang.xlf:plugin.flipbook_simple.title
                description = LLL:EXT:flipbook_converter/Resources/Private/Language/locallang.xlf:plugin.flipbook_simple.description
                tt_content_defValues {
                    CType = flipbookconverter_flipbook_simple
                }
            }
        }
        show = *
    }
');

// Preview za backend
$GLOBALS['TCA']['tt_content']['ctrl']['typeicon_classes']['flipbookconverter_flipbook'] = 'content-flipbook';
$GLOBALS['TCA']['tt_content']['ctrl']['typeicon_classes']['flipbookconverter_flipbook_simple'] = 'content-flipbook';