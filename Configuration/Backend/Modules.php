<?php

/**
 * Backend module configuration for TYPO3 13
 */
return [
    'flipbookconverter' => [
        'parent' => 'web',
        'position' => ['after' => 'web_list'],
        'access' => 'user',
        'iconIdentifier' => 'extension-flipbook-converter',
        'labels' => 'LLL:EXT:flipbook_converter/Resources/Private/Language/locallang_backend.xlf',
        'navigationComponent' => '@typo3/backend/navigation/page-tree/page-tree-element',
        'inheritNavigationComponentFromMainModule' => false,
        'extensionName' => 'FlipbookConverter',
        'routes' => [
            '_default' => [
                'target' => \Gmbit\FlipbookConverter\Controller\BackendController::class . '::listAction',
            ],
        ],
        'controllerActions' => [
            \Gmbit\FlipbookConverter\Controller\BackendController::class => [
                'list',
                'edit', 
                'upload',
                'process',
                'bulkProcess',
                'delete',
                'statistics',
                'statisticsData',
                'status',
                'settings',
                'bulkAction',
            ],
        ],
    ],
];