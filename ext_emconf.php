<?php

/*
 * Extension Manager/Repository config file for ext "flipbook_converter".
 */

$EM_CONF[$_EXTKEY] = [
    'title' => 'PDF to Flipbook Converter',
    'description' => 'Convert PDF documents to interactive flipbooks with customizable display options. Provides backend management interface and frontend content element for seamless integration.',
    'category' => 'plugin',
    'author' => 'Your Name',
    'author_email' => 'your.email@example.com',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.0.0-13.4.99',
            'php' => '8.1.0-8.3.99',
        ],
        'conflicts' => [],
        'suggests' => [
            'scheduler' => '13.0.0-13.4.99',
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'Gmbit\\FlipbookConverter\\' => 'Classes/',
        ],
    ],
    'clearcacheonload' => true,
];