<?php

declare(strict_types=1);

namespace Gmbit\FlipbookConverter\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * KOMPLETNO NOVI Flipbook Controller
 */
class FlipbookController extends ActionController
{
    public function showAction(): ResponseInterface
    {
        // FORCE LOG TO PROVE THIS IS THE NEW VERSION
        error_log('*** KOMPLETNO NOVI CONTROLLER - VERSION 2.0 ***');
        error_log('Timestamp: ' . date('Y-m-d H:i:s'));
        
        $currentPageId = (int)($GLOBALS['TSFE']->id ?? 0);
        error_log('Current page ID: ' . $currentPageId);
        
        // Get flipbook content element from current page
        $contentElement = $this->getFlipbookContentElement($currentPageId);
        
        if (!$contentElement) {
            error_log('No flipbook content element found on page');
            $this->view->assign('error', 'No flipbook content element found');
            return $this->htmlResponse();
        }
        
        $documentUid = (int)($contentElement['tx_flipbookconverter_document'] ?? 0);
        error_log('Found content element UID ' . $contentElement['uid'] . ' with document UID: ' . $documentUid);
        
        if ($documentUid === 0) {
            error_log('Content element has no document selected');
            $this->view->assign('error', 'No document selected in content element');
            return $this->htmlResponse();
        }
        
        // Load document
        $document = $this->getDocument($documentUid);
        
        if (!$document) {
            error_log('Document UID ' . $documentUid . ' not found in database');
            $this->view->assign('error', 'Document not found: ' . $documentUid);
            return $this->htmlResponse();
        }
        
        error_log('Loaded document: ' . $document['title'] . ' (UID: ' . $document['uid'] . ')');
        error_log('Document total_pages: ' . ($document['total_pages'] ?? 'NULL'));
        error_log('Document status: ' . ($document['status'] ?? 'NULL'));
        error_log('Document file_size: ' . ($document['file_size'] ?? 'NULL'));
        
        $processedImagesField = $document['processed_images'] ?? '';
        error_log('Document processed_images length: ' . strlen($processedImagesField));
        error_log('Document processed_images preview: ' . substr($processedImagesField, 0, 500) . '...');
        
        // Also check if processed_images is empty or null
        if (empty($processedImagesField)) {
            error_log('WARNING: processed_images field is empty or null!');
            
            // Check if document processing actually completed
            if ($document['status'] == 2) {
                error_log('Document status is 2 (processed) but no processed_images data - possible processing issue');
            }
        }
        
        // Get images for this document
        $images = $this->getDocumentImages($document);
        error_log('Found ' . count($images) . ' real images for document');
        
        // FORCE TEST - try to load one specific file manually
        $testPath = '/pep/public/fileadmin/flipbook_processed/document_1750184382_6851b1beb6152/page_0001.png';
        error_log('FORCE TEST - checking specific file: ' . $testPath);
        error_log('FORCE TEST - file exists: ' . (file_exists($testPath) ? 'YES' : 'NO'));
        
        if (file_exists($testPath)) {
            error_log('FORCE TEST - file found, creating manual image entry');
            $testImage = [
                'page' => 999, // Unique page number for test
                'filePath' => $testPath,
                'publicUrl' => $this->createPublicUrl('/flipbook_processed/document_1750184382_6851b1beb6152/page_0001.png'),
                'width' => 800,
                'height' => 600,
                'fileSize' => filesize($testPath),
                'isManualTest' => true,
            ];
            array_unshift($images, $testImage); // Add to beginning
            error_log('FORCE TEST - added test image, total images now: ' . count($images));
        }
        
        // For debugging - create simple test images if none found
        if (empty($images)) {
            $images = $this->createSimpleTestImages($document['uid']);
            error_log('Created ' . count($images) . ' test images');
        } else {
            error_log('Using real images from database');
        }
        
        // Simple config
        $config = [
            'showControls' => true,
            'showPageNumbers' => true,
            'enableKeyboard' => true,
            'width' => 800,
            'height' => 600,
        ];
        
        // Assign to view
        $this->view->assign('document', $document);
        $this->view->assign('images', $images);
        $this->view->assign('config', $config);
        $this->view->assign('hasImages', !empty($images));
        $this->view->assign('documentUid', $documentUid);
        $this->view->assign('contentElement', $contentElement);
        $this->view->assign('uniqueId', 'flipbook_' . $document['uid']);
        
        // FORCE DEBUG INFO direct to template
        $debugData = [
            'controller_version' => 'VERSION 2.0',
            'timestamp' => date('Y-m-d H:i:s'),
            'document_uid' => $document['uid'],
            'document_total_pages' => $document['total_pages'],
            'processed_images_length' => strlen($document['processed_images'] ?? ''),
            'images_count_real' => count($this->getDocumentImages($document)),
            'images_count_final' => count($images),
            'using_test_images' => empty($this->getDocumentImages($document)),
            'first_image_path' => $this->getFirstImagePath($document),
            'current_working_dir' => getcwd(),
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
        ];
        $this->view->assign('debugData', $debugData);
        
        error_log('*** CONTROLLER FINISHED - RETURNING RESPONSE ***');
        
        return $this->htmlResponse();
    }
    
    protected function getFlipbookContentElement(int $pageId): ?array
    {
        if ($pageId === 0) return null;
        
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tt_content');
        
        $result = $queryBuilder
            ->select('*')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageId)),
                $queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter('flipbookconverter_flipbook')),
                $queryBuilder->expr()->eq('hidden', 0),
                $queryBuilder->expr()->eq('deleted', 0)
            )
            ->orderBy('sorting', 'ASC')
            ->setMaxResults(1)
            ->executeQuery();
            
        return $result->fetchAssociative() ?: null;
    }
    
    protected function getDocument(int $documentUid): ?array
    {
        if ($documentUid === 0) return null;
        
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_flipbookconverter_document');
        
        $result = $queryBuilder
            ->select('*')
            ->from('tx_flipbookconverter_document')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($documentUid)),
                $queryBuilder->expr()->eq('deleted', 0)
            )
            ->executeQuery();
            
        return $result->fetchAssociative() ?: null;
    }
    
    protected function getDocumentImages(array $document): array
    {
        $images = [];
        
        error_log('Getting images for document UID: ' . $document['uid']);
        error_log('Processed images field: ' . ($document['processed_images'] ?? 'NULL'));
        
        if (empty($document['processed_images'])) {
            error_log('No processed_images field data');
            return $images;
        }
        
        $processedImages = json_decode($document['processed_images'], true);
        if (!is_array($processedImages)) {
            error_log('Failed to decode processed_images JSON');
            return $images;
        }
        
        error_log('Decoded processed_images: ' . print_r($processedImages, true));
        
        foreach ($processedImages as $index => $imageData) {
            error_log('Processing image ' . $index . ': ' . print_r($imageData, true));
            
            if (!is_array($imageData)) {
                error_log('Image data is not array, skipping');
                continue;
            }
            
            $path = $imageData['path'] ?? '';
            $identifier = $imageData['identifier'] ?? '';
            $page = (int)($imageData['page'] ?? ($index + 1));
            
            // Simple file check without conversions
            error_log("Image {$index}: path={$path}, identifier={$identifier}, page={$page}");
            
            if (empty($path)) {
                error_log('Empty path, skipping');
                continue;
            }
            
            $fileExists = file_exists($path);
            error_log('File exists check for ' . $path . ': ' . ($fileExists ? 'YES' : 'NO'));
            
            if (!$fileExists) {
                error_log('File does not exist, skipping: ' . $path);
                continue;
            }
            
            $publicUrl = $this->createPublicUrl($identifier);
            error_log('Created public URL: ' . $publicUrl);
            
            $images[] = [
                'page' => $page,
                'filePath' => $path,
                'publicUrl' => $publicUrl,
                'width' => 800,
                'height' => 600,
                'fileSize' => filesize($path),
            ];
            
            error_log('Added image for page ' . $page);
        }
        
        // Sort by page number
        usort($images, function($a, $b) {
            return $a['page'] <=> $b['page'];
        });
        
        error_log('Final images count: ' . count($images));
        
        return $images;
    }
    
    protected function getFirstImagePath(array $document): string
    {
        if (empty($document['processed_images'])) {
            return 'No processed_images field';
        }
        
        $processedImages = json_decode($document['processed_images'], true);
        if (!is_array($processedImages) || empty($processedImages)) {
            return 'Invalid or empty processed_images JSON';
        }
        
        $firstImage = $processedImages[0] ?? null;
        if (!is_array($firstImage)) {
            return 'First image is not array';
        }
        
        $path = $firstImage['path'] ?? 'No path field';
        $exists = is_string($path) ? (file_exists($path) ? 'EXISTS' : 'NOT FOUND') : 'Invalid path';
        
        return $path . ' (' . $exists . ')';
    }
    
    protected function createPublicUrl(string $identifier): string
    {
        $siteUrl = GeneralUtility::getIndpEnv('TYPO3_SITE_URL');
        return $siteUrl . 'fileadmin' . $identifier;
    }
    
    protected function createSimpleTestImages(int $documentUid): array
    {
        $images = [];
        
        // Kreiraj jednostavne SVG slike umesto placeholder.com
        for ($i = 1; $i <= 3; $i++) {
            $color = ['#FF6B6B', '#4ECDC4', '#45B7D1'][$i - 1];
            
            // Kreiraj SVG kao data URL
            $svg = '<svg width="800" height="600" xmlns="http://www.w3.org/2000/svg">' .
                   '<rect width="100%" height="100%" fill="' . $color . '"/>' .
                   '<text x="50%" y="50%" text-anchor="middle" dy=".3em" font-family="Arial" font-size="48" fill="white">' .
                   'DOC ' . $documentUid . ' PAGE ' . $i .
                   '</text></svg>';
            
            $dataUrl = 'data:image/svg+xml;base64,' . base64_encode($svg);
            
            $images[] = [
                'page' => $i,
                'filePath' => '',
                'publicUrl' => $dataUrl,
                'width' => 800,
                'height' => 600,
                'fileSize' => 0,
                'isTestImage' => true,
            ];
        }
        
        return $images;
    }
}