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