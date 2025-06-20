<?php

declare(strict_types=1);

defined('TYPO3') or die();

// 1. Registruj Extbase plugin
\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
    'FlipbookConverter',
    'Flipbook',
    'LLL:EXT:flipbook_converter/Resources/Private/Language/locallang_db.xlf:plugin.flipbook'
);

// 2. NUCLEAR OPTION - direktno definiši itemsProcFunc
$flipbookFields = [
    'tx_flipbookconverter_document' => [
        'exclude' => false,
        'label' => 'Flipbook Document',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'itemsProcFunc' => \Gmbit\FlipbookConverter\UserFunc\DocumentItemsProcFunc::class . '->getFlipbookDocuments',
            'items' => [
                [
                    'label' => '--- Please select document ---',
                    'value' => 0,
                ],
            ],
            'minitems' => 1,
            'maxitems' => 1,
            'required' => true,
        ],
    ],
];

// 3. Dodaj polja u tt_content
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('tt_content', $flipbookFields);

// Ostatak koda isti kao gore...
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTcaSelectItem(
    'tt_content',
    'CType',
    [
        'label' => 'Flipbook Display',
        'value' => 'flipbookconverter_flipbook',
        'icon' => 'content-flipbook',
        'group' => 'special',
    ],
    'textmedia',
    'after'
);

$GLOBALS['TCA']['tt_content']['types']['flipbookconverter_flipbook'] = [
    'showitem' => '
        --div--;General,
            --palette--;;general,
            --palette--;;headers,
            tx_flipbookconverter_document,
        --div--;Plugin,
            pi_flexform,
        --div--;Appearance,
            --palette--;;frames,
        --div--;Access,
            --palette--;;hidden,
            --palette--;;access,
    ',
];

$GLOBALS['TCA']['tt_content']['columns']['pi_flexform']['config']['ds']['*,flipbookconverter_flipbook'] = 
    'FILE:EXT:flipbook_converter/Configuration/FlexForms/FlipbookConfiguration.xml';

$GLOBALS['TCA']['tt_content']['ctrl']['typeicon_classes']['flipbookconverter_flipbook'] = 'content-flipbook';

/**
 * ========================================
 * File 1: Configuration/TCA/Overrides/tt_content.php
 * Dodaj na KRAJ postojećeg fajla
 * ========================================
 */

// ============================================
// TEASER CONTENT ELEMENT (dodaj na kraj)
// ============================================

// STEP 2: Dodaj CType u select listu
// DODAJ TEASER CONTENT TYPE
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTcaSelectItem(
    'tt_content',
    'CType',
    [
        'label' => 'Flipbook Teaser',
        'value' => 'flipbookconverter_teaser',
        'icon' => 'content-special-html',
        'group' => 'special',
        'description' => 'Display teaser for flipbook documents'
    ]
);

// TEASER POLJA SA ITEMSPROCFUNC
$tempColumns = [
    'tx_flipbook_teaser_document' => [
        'exclude' => false,
        'label' => 'Flipbook Document',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            
            // KORISTI POSTOJEĆU ITEMSPROCFUNC KLASU
            'itemsProcFunc' => \Gmbit\FlipbookConverter\UserFunc\DocumentItemsProcFunc::class . '->getFlipbookDocuments',
            
            'items' => [
                [
                    'label' => '-- Please select document --',
                    'value' => 0,
                ],
            ],
            'minitems' => 0,
            'maxitems' => 1,
            
            // UKLONI foreign_table jer koristimo itemsProcFunc
            // 'foreign_table' => 'tx_flipbookconverter_document',
            // 'foreign_table_where' => '...',
        ],
    ],
    
    'tx_flipbook_teaser_target_page' => [
        'exclude' => false,
        'label' => 'Target Flipbook Page',
        'description' => 'Page where flipbook plugin is located',
        'config' => [
            'type' => 'group',
            'allowed' => 'pages',
            'size' => 1,
            'maxitems' => 1,
            'minitems' => 0,
            'suggestOptions' => [
                'default' => [
                    'searchWholePhrase' => true
                ]
            ]
        ],
    ],
    
    'tx_flipbook_teaser_style' => [
        'exclude' => false,
        'label' => 'Teaser Style',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'items' => [
                ['Card Style', 'card'],
                ['Mini Style', 'mini'],
                ['Banner Style', 'banner'],
            ],
            'default' => 'card',
        ],
    ],
];

// DODAJ POLJA U TABELU
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('tt_content', $tempColumns);

// DEFINIŠI TYPE KONFIGURACIJU
$GLOBALS['TCA']['tt_content']['types']['flipbookconverter_teaser'] = [
    'showitem' => '
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,
            --palette--;;general,
            header;Teaser Header,
            tx_flipbook_teaser_document,
            tx_flipbook_teaser_target_page,
            tx_flipbook_teaser_style,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:appearance,
            --palette--;;frames,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
            --palette--;;hidden,
            --palette--;;access,
    ',
    'columnsOverrides' => [
        'header' => [
            'config' => [
                'placeholder' => 'Optional header for teaser section'
            ]
        ]
    ]
];

// IKONA
$GLOBALS['TCA']['tt_content']['ctrl']['typeicon_classes']['flipbookconverter_teaser'] = 'content-special-html';