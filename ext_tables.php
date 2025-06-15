<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

(static function (): void {
    $extensionKey = 'flipbook_converter';

    // Backend modul registracija
    ExtensionUtility::registerModule(
        'FlipbookConverter',
        'web',
        'flipbook',
        '',
        [
            \Gmbit\FlipbookConverter\Controller\BackendController::class => 'list, show, new, create, edit, update, delete, process, preview',
        ],
        [
            'access' => 'user,group',
            'icon' => 'EXT:flipbook_converter/Resources/Public/Images/Icons/flipbook-icon.svg',
            'labels' => 'LLL:EXT:flipbook_converter/Resources/Private/Language/locallang_mod.xlf',
            'navigationComponentId' => '',
            'inheritNavigationComponentFromMainModule' => false,
        ]
    );

    // Plugin registracija u Content Element wizard
    ExtensionManagementUtility::addPlugin(
        [
            'LLL:EXT:flipbook_converter/Resources/Private/Language/locallang.xlf:plugin.flipbook.title',
            'flipbookconverter_flipbook',
            'content-flipbook',
        ],
        'CType',
        $extensionKey
    );

    // TCA za tt_content tabelu (Content Element konfiguracija)
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

    // FlexForm konfiguracija za Content Element
    $GLOBALS['TCA']['tt_content']['types']['flipbookconverter_flipbook']['pi_flexform_ds'] = 
        'FILE:EXT:flipbook_converter/Configuration/FlexForms/FlipbookConfiguration.xml';

    // Dodavanje custom CSS/JS za backend
    $GLOBALS['TBE_STYLES']['skins'][$extensionKey]['stylesheetDirectories'][] = 
        'EXT:flipbook_converter/Resources/Public/CSS/Backend/';

    // Context menu opcije za flipbook records
    $GLOBALS['TYPO3_CONF_VARS']['BE']['ContextMenu']['ItemProviders'][1668759512] = 
        \Gmbit\FlipbookConverter\ContextMenu\ItemProvider::class;

    // Custom form engine za backend
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1668759513] = [
        'nodeName' => 'flipbookPreview',
        'priority' => 40,
        'class' => \Gmbit\FlipbookConverter\Form\Element\FlipbookPreviewElement::class,
    ];

})();