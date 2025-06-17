<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

return [
    'ctrl' => [
        'title' => 'LLL:EXT:flipbook_converter/Resources/Private/Language/locallang_db.xlf:tx_flipbookconverter_document',
        'label' => 'title',
        'label_alt' => 'pdf_file',
        'label_alt_force' => 1,
        'descriptionColumn' => 'description',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime',
            'fe_group' => 'fe_group',
        ],
        'sortby' => 'sorting',
        'iconfile' => 'EXT:flipbook_converter/Resources/Public/Images/Icons/flipbook-icon.svg',
        'searchFields' => 'title,description',
        'typeicon_column' => 'status',
        'typeicon_classes' => [
            '0' => 'status-dialog-information',  // Pending
            '1' => 'spinner-circle-light',       // Processing
            '2' => 'status-dialog-ok',           // Completed
            '3' => 'status-dialog-error',        // Error
        ],
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'languageField' => 'sys_language_uid',
        'translationSource' => 'l10n_source',
        'versioningWS' => true,
        'origUid' => 't3_origuid',
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
    ],
    'interface' => [
        'showRecordFieldList' => 'hidden,title,description,pdf_file,status,total_pages,file_size,processing_time,last_processed',
    ],
    'columns' => [
        'sys_language_uid' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.language',
            'config' => [
                'type' => 'language',
            ],
        ],
        'l10n_parent' => [
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.l18n_parent',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['', 0],
                ],
                'foreign_table' => 'tx_flipbookconverter_document',
                'foreign_table_where' => 'AND tx_flipbookconverter_document.pid=###CURRENT_PID### AND tx_flipbookconverter_document.sys_language_uid IN (-1,0)',
                'default' => 0,
            ],
        ],
        'l10n_source' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'l10n_diffsource' => [
            'config' => [
                'type' => 'passthrough',
                'default' => '',
            ],
        ],
        'hidden' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.visible',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'items' => [
                    [
                        0 => '',
                        1 => '',
                        'invertStateDisplay' => true,
                    ],
                ],
            ],
        ],
        'starttime' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.starttime',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'eval' => 'datetime,int',
                'default' => 0,
            ],
        ],
        'endtime' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.endtime',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'eval' => 'datetime,int',
                'default' => 0,
                'range' => [
                    'upper' => mktime(0, 0, 0, 1, 1, 2038),
                ],
            ],
        ],
        'fe_group' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.fe_group',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'size' => 5,
                'maxitems' => 20,
                'items' => [
                    [
                        'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hide_at_login',
                        -1,
                    ],
                    [
                        'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.any_login',
                        -2,
                    ],
                    [
                        'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.usergroups',
                        '--div--',
                    ],
                ],
                'exclusiveKeys' => '-1,-2',
                'foreign_table' => 'fe_groups',
            ],
        ],
        'title' => [
            'exclude' => false,
            'label' => 'LLL:EXT:flipbook_converter/Resources/Private/Language/locallang_db.xlf:tx_flipbookconverter_document.title',
            'description' => 'LLL:EXT:flipbook_converter/Resources/Private/Language/locallang_db.xlf:tx_flipbookconverter_document.title.description',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim,required',
                'max' => 255,
            ],
        ],
        'description' => [
            'exclude' => false,
            'label' => 'LLL:EXT:flipbook_converter/Resources/Private/Language/locallang_db.xlf:tx_flipbookconverter_document.description',
            'config' => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 3,
                'eval' => 'trim',
            ],
        ],
        'pdf_file' => [
            'exclude' => false,
            'label' => 'LLL:EXT:flipbook_converter/Resources/Private/Language/locallang_db.xlf:tx_flipbookconverter_document.pdf_file',
            'description' => 'LLL:EXT:flipbook_converter/Resources/Private/Language/locallang_db.xlf:tx_flipbookconverter_document.pdf_file.description',
            'config' => [
                'type' => 'file',
                'allowed' => 'pdf',
                'maxitems' => 1,
                'minitems' => 1,
                'appearance' => [
                    'createNewRelationLinkTitle' => 'LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:media.addFileReference',
                ],
                'overrideChildTca' => [
                    'types' => [
                        \TYPO3\CMS\Core\Resource\File::FILETYPE_APPLICATION => [
                            'showitem' => '
                                --palette--;;filePalette
                            ',
                        ],
                    ],
                ],
            ],
        ],
        'status' => [
            'exclude' => true,
            'label' => 'LLL:EXT:flipbook_converter/Resources/Private/Language/locallang_db.xlf:tx_flipbookconverter_document.status',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['LLL:EXT:flipbook_converter/Resources/Private/Language/locallang_db.xlf:tx_flipbookconverter_document.status.pending', 0],
                    ['LLL:EXT:flipbook_converter/Resources/Private/Language/locallang_db.xlf:tx_flipbookconverter_document.status.processing', 1],
                    ['LLL:EXT:flipbook_converter/Resources/Private/Language/locallang_db.xlf:tx_flipbookconverter_document.status.completed', 2],
                    ['LLL:EXT:flipbook_converter/Resources/Private/Language/locallang_db.xlf:tx_flipbookconverter_document.status.error', 3],
                ],
                'default' => 0,
                'readOnly' => true,
            ],
        ],
        'processed_images' => [
            'exclude' => true,
            'label' => 'LLL:EXT:flipbook_converter/Resources/Private/Language/locallang_db.xlf:tx_flipbookconverter_document.processed_images',
            'config' => [
                'type' => 'text',
                'renderType' => 'textTable',
                'readOnly' => true,
                'cols' => 40,
                'rows' => 5,
            ],
        ],
        'processing_log' => [
            'exclude' => true,
            'label' => 'LLL:EXT:flipbook_converter/Resources/Private/Language/locallang_db.xlf:tx_flipbookconverter_document.processing_log',
            'config' => [
                'type' => 'text',
                'cols' => 80,
                'rows' => 10,
                'readOnly' => true,
                'wrap' => 'off',
            ],
        ],
        'total_pages' => [
            'exclude' => true,
            'label' => 'LLL:EXT:flipbook_converter/Resources/Private/Language/locallang_db.xlf:tx_flipbookconverter_document.total_pages',
            'config' => [
                'type' => 'input',
                'size' => 10,
                'eval' => 'int',
                'readOnly' => true,
                'default' => 0,
            ],
        ],
        'file_size' => [
            'exclude' => true,
            'label' => 'LLL:EXT:flipbook_converter/Resources/Private/Language/locallang_db.xlf:tx_flipbookconverter_document.file_size',
            'config' => [
                'type' => 'input',
                'size' => 15,
                'eval' => 'int',
                'readOnly' => true,
                'default' => 0,
            ],
        ],
        'file_hash' => [
            'exclude' => true,
            'label' => 'LLL:EXT:flipbook_converter/Resources/Private/Language/locallang_db.xlf:tx_flipbookconverter_document.file_hash',
            'config' => [
                'type' => 'input',
                'size' => 64,
                'max' => 64,
                'readOnly' => true,
            ],
        ],
        //'flipbook_config' => [
        //    'exclude' => false,
        //    'label' => 'LLL:EXT:flipbook_converter/Resources/Private/Language/locallang_db.xlf:tx_flipbookconverter_document.flipbook_config',
        //    'config' => [
        //        'type' => 'flex',
        //        'ds' => [
        //            'default' => 'FILE:EXT:flipbook_converter/Configuration/FlexForms/FlipbookDocumentConfiguration.xml',
        //        ],
        //    ],
        //],
        'processing_time' => [
            'exclude' => true,
            'label' => 'LLL:EXT:flipbook_converter/Resources/Private/Language/locallang_db.xlf:tx_flipbookconverter_document.processing_time',
            'config' => [
                'type' => 'input',
                'size' => 10,
                'eval' => 'int',
                'readOnly' => true,
                'default' => 0,
            ],
        ],
        'last_processed' => [
            'exclude' => true,
            'label' => 'LLL:EXT:flipbook_converter/Resources/Private/Language/locallang_db.xlf:tx_flipbookconverter_document.last_processed',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'eval' => 'datetime,int',
                'readOnly' => true,
                'default' => 0,
            ],
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => '
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,
                    --palette--;;general,
                    --palette--;;file,
                --div--;LLL:EXT:flipbook_converter/Resources/Private/Language/locallang_db.xlf:tabs.processing,
                    --palette--;;processing_status,
                    --palette--;;processing_info,
                    processing_log,
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:language,
                    --palette--;;language,
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
                    --palette--;;hidden,
                    --palette--;;access,
            ',
        ],
        //Izmesteno iz show items--div--;LLL:EXT:flipbook_converter/Resources/Private/Language/locallang_db.xlf:tabs.configuration,
        //            flipbook_config,
    ],
    'palettes' => [
        'general' => [
            'showitem' => '
                title,
                --linebreak--,
                description
            ',
        ],
        'file' => [
            'showitem' => '
                pdf_file
            ',
        ],
        'processing_status' => [
            'showitem' => '
                status,
                --linebreak--,
                total_pages, file_size,
                --linebreak--,
                processing_time, last_processed
            ',
        ],
        'processing_info' => [
            'showitem' => '
                file_hash,
                --linebreak--,
                processed_images
            ',
        ],
        'language' => [
            'showitem' => '
                sys_language_uid;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:sys_language_uid_formlabel,
                l10n_parent
            ',
        ],
        'hidden' => [
            'showitem' => '
                hidden;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:field.default.hidden
            ',
        ],
        'access' => [
            'showitem' => '
                starttime;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:starttime_formlabel,
                endtime;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:endtime_formlabel,
                --linebreak--,
                fe_group;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:fe_group_formlabel
            ',
        ],
    ],
];