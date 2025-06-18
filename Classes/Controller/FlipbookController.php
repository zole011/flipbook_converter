<?php

declare(strict_types=1);

namespace Gmbit\FlipbookConverter\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Resource\ResourceFactory;

class FlipbookController extends ActionController
{
    public function showAction(): ResponseInterface
    {
        $currentPageId = (int)($GLOBALS['TSFE']->id ?? 0);
        $contentElement = $this->getFlipbookContentElement($currentPageId);
        
        if (!$contentElement) {
            $this->view->assign('error', 'No flipbook content element found');
            return $this->htmlResponse();
        }
        
        $documentUid = (int)($contentElement['tx_flipbookconverter_document'] ?? 0);
        
        if ($documentUid === 0) {
            $this->view->assign('error', 'No document selected in content element');
            return $this->htmlResponse();
        }
        
        $document = $this->getDocument($documentUid);
        
        if (!$document) {
            $this->view->assign('error', 'Document not found: ' . $documentUid);
            return $this->htmlResponse();
        }
        
        $processedImagesField = $document['processed_images'] ?? '';
        
        $images = $this->getDocumentImages($document);
        
        if (empty($images)) {
            $images = $this->createSimpleTestImages($document['uid']);
        }
        
        $config = [
            'showControls' => true,
            'showPageNumbers' => true,
            'enableKeyboard' => true,
            'width' => 800,
            'height' => 600,
        ];
        
        $this->view->assign('document', $document);
        $this->view->assign('images', $images);
        $this->view->assign('config', $config);
        $this->view->assign('hasImages', !empty($images));
        $this->view->assign('documentUid', $documentUid);
        $this->view->assign('contentElement', $contentElement);
        $this->view->assign('uniqueId', 'flipbook_' . $document['uid']);
        
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
        if (empty($document['processed_images'])) {
            return $images;
        }
        
        $processedImages = json_decode($document['processed_images'], true);
        if (!is_array($processedImages)) {
            return $images;
        }
                
        foreach ($processedImages as $index => $imageData) {            
            if (!is_array($imageData)) {
                continue;
            }
            
            $path = $imageData['path'] ?? '';

            if (strpos($path, '/pep/public/') === 0) {
                $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
                $path = $documentRoot . $path;
            } elseif (strpos($path, '/') === 0) {
                $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
                $path = $documentRoot . '/pep/public' . $path;
            }

            $identifier = $imageData['identifier'] ?? '';
            $page = (int)($imageData['page'] ?? ($index + 1));
            
            if (empty($path) || !file_exists($path)) {
                continue;
            }
            
            $publicUrl = $this->createPublicUrl($identifier);
            
            $images[] = [
                'page' => $page,
                'filePath' => $path,
                'publicUrl' => $publicUrl,
                'width' => 800,
                'height' => 600,
                'fileSize' => filesize($path),
            ];
        }
        
        usort($images, function($a, $b) {
            return $a['page'] <=> $b['page'];
        });
                
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
        
        for ($i = 1; $i <= 3; $i++) {
            $color = ['#FF6B6B', '#4ECDC4', '#45B7D1'][$i - 1];
            
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
