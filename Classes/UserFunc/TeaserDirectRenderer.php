<?php

declare(strict_types=1);

namespace Gmbit\FlipbookConverter\UserFunc;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Core\Http\ServerRequest;

/**
 * TYPO3 v12+ COMPATIBLE USERFUNC RENDERER
 * 
 * File: Classes/UserFunc/TeaserDirectRenderer.php
 */
class TeaserDirectRenderer
{
    /**
     * TYPO3 v12+ userFunc signature
     */
    public function render(string $content, array $conf, ServerRequest $request): string
    {
        error_log('TEASER DIRECT: UserFunc called (v12+ signature)');
        
        // Dohvati ContentObjectRenderer iz request
        $cObj = $request->getAttribute('currentContentObject');
        
        if (!$cObj instanceof ContentObjectRenderer) {
            return $this->renderError('ContentObjectRenderer not available in request', []);
        }
        
        // Dohvati content data iz cObj
        $contentData = $cObj->data;
        
        // Dohvati parametre iz TypoScript config
        $documentUid = (int)$cObj->stdWrap($conf['documentUid'] ?? '', $conf['documentUid.'] ?? []);
        $targetPageUid = (int)$cObj->stdWrap($conf['targetPageUid'] ?? '', $conf['targetPageUid.'] ?? []);
        $teaserStyle = $cObj->stdWrap($conf['teaserStyle'] ?? 'card', $conf['teaserStyle.'] ?? []);
        $contentUid = (int)$cObj->stdWrap($conf['contentUid'] ?? '', $conf['contentUid.'] ?? []);
        
        error_log("TEASER DIRECT: Content UID = {$contentUid}, Document UID = {$documentUid}");
        
        // Debug output
        $debugInfo = [
            'typo3_version' => 'v12+',
            'request_attributes' => array_keys($request->getAttributes()),
            'content_data_uid' => $contentData['uid'] ?? 'not set',
            'content_data_ctype' => $contentData['CType'] ?? 'not set',
            'content_data_doc_field' => $contentData['tx_flipbook_teaser_document'] ?? 'not set',
            'param_document_uid' => $documentUid,
            'param_target_page' => $targetPageUid,
            'param_style' => $teaserStyle,
        ];
        
        if (!$documentUid) {
            return $this->renderError('No document UID provided', $debugInfo);
        }
        
        // Dohvati dokument iz baze
        $document = $this->getDocument($documentUid);
        
        if (!$document) {
            return $this->renderError("Document UID {$documentUid} not found", $debugInfo);
        }
        
        // Generiši flipbook URL
        $flipbookUrl = $this->generateFlipbookUrl($documentUid, $targetPageUid, $cObj);
        
        // Generiši cover image
        $coverImage = $this->getCoverImage($document);
        
        // Render HTML
        return $this->renderTeaser($document, $flipbookUrl, $coverImage, $teaserStyle, $debugInfo);
    }
    
    /**
     * LEGACY userFunc signature za starije TYPO3 verzije
     */
    public function renderLegacy(string $content, array $conf, ContentObjectRenderer $cObj): string
    {
        error_log('TEASER DIRECT: UserFunc called (legacy signature)');
        
        // Dohvati content data iz cObj
        $contentData = $cObj->data;
        
        // Dohvati parametre iz TypoScript config
        $documentUid = (int)$cObj->stdWrap($conf['documentUid'] ?? '', $conf['documentUid.'] ?? []);
        $targetPageUid = (int)$cObj->stdWrap($conf['targetPageUid'] ?? '', $conf['targetPageUid.'] ?? []);
        $teaserStyle = $cObj->stdWrap($conf['teaserStyle'] ?? 'card', $conf['teaserStyle.'] ?? []);
        $contentUid = (int)$cObj->stdWrap($conf['contentUid'] ?? '', $conf['contentUid.'] ?? []);
        
        error_log("TEASER DIRECT: Content UID = {$contentUid}, Document UID = {$documentUid}");
        
        // Debug output
        $debugInfo = [
            'typo3_version' => 'legacy',
            'content_data_uid' => $contentData['uid'] ?? 'not set',
            'content_data_ctype' => $contentData['CType'] ?? 'not set',
            'content_data_doc_field' => $contentData['tx_flipbook_teaser_document'] ?? 'not set',
            'param_document_uid' => $documentUid,
            'param_target_page' => $targetPageUid,
            'param_style' => $teaserStyle,
        ];
        
        if (!$documentUid) {
            return $this->renderError('No document UID provided', $debugInfo);
        }
        
        // Dohvati dokument iz baze
        $document = $this->getDocument($documentUid);
        
        if (!$document) {
            return $this->renderError("Document UID {$documentUid} not found", $debugInfo);
        }
        
        // Generiši flipbook URL
        $flipbookUrl = $this->generateFlipbookUrl($documentUid, $targetPageUid, $cObj);
        
        // Generiši cover image
        $coverImage = $this->getCoverImage($document);
        
        // Render HTML
        return $this->renderTeaser($document, $flipbookUrl, $coverImage, $teaserStyle, $debugInfo);
    }
    
    /**
     * Dohvati dokument iz baze
     */
    private function getDocument(int $uid): ?array
    {
        try {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable('tx_flipbookconverter_document');
            
            $document = $queryBuilder
                ->select('*')
                ->from('tx_flipbookconverter_document')
                ->where(
                    $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT)),
                    $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)),
                    $queryBuilder->expr()->eq('status', $queryBuilder->createNamedParameter(2, \PDO::PARAM_INT))
                )
                ->executeQuery()
                ->fetchAssociative();
            
            return $document ?: null;
        } catch (\Exception $e) {
            error_log('TEASER DIRECT: Database error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Generiši flipbook URL
     */
    private function generateFlipbookUrl(int $documentUid, int $targetPageUid, ContentObjectRenderer $cObj): string
    {
        if (!$targetPageUid) {
            return '#no-target-page';
        }
        
        try {
            $url = $cObj->typoLink_URL([
                'parameter' => $targetPageUid,
                'additionalParams' => '&document=' . $documentUid,
                'forceAbsoluteUrl' => false
            ]);
            
            return $url;
        } catch (\Exception $e) {
            return "/page/{$targetPageUid}?document={$documentUid}";
        }
    }
    
    /**
     * Generiši cover image
     */
    private function getCoverImage(array $document): string
    {
        // Pokušaj parsed images
        if (!empty($document['processed_images'])) {
            $images = json_decode($document['processed_images'], true);
            if (is_array($images) && !empty($images)) {
                $firstImage = $images[0];
                if (is_array($firstImage) && isset($firstImage['identifier'])) {
                    $siteUrl = GeneralUtility::getIndpEnv('TYPO3_SITE_URL');
                    return $siteUrl . 'fileadmin' . $firstImage['identifier'];
                }
            }
        }
        
        // Fallback placeholder
        return 'data:image/svg+xml;base64,' . base64_encode('
            <svg width="300" height="400" xmlns="http://www.w3.org/2000/svg">
                <rect width="100%" height="100%" fill="#f8f9fa" stroke="#dee2e6"/>
                <text x="50%" y="50%" text-anchor="middle" font-family="Arial" font-size="16" fill="#6c757d">
                    📄 ' . htmlspecialchars($document['title']) . '
                </text>
            </svg>
        ');
    }
    
    /**
     * Render error message
     */
    private function renderError(string $message, array $debugInfo): string
    {
        $debugJson = htmlspecialchars(json_encode($debugInfo, JSON_PRETTY_PRINT));
        
        return '
            <div style="border: 2px solid red; padding: 20px; background: #ffe6e6; margin: 20px 0;">
                <h3 style="color: red;">❌ Flipbook Teaser Error</h3>
                <p><strong>Error:</strong> ' . htmlspecialchars($message) . '</p>
                <details>
                    <summary>Debug Info</summary>
                    <pre style="background: #f0f0f0; padding: 10px; font-size: 12px;">' . $debugJson . '</pre>
                </details>
            </div>
        ';
    }
    
    /**
     * Render successful teaser
     */
    private function renderTeaser(array $document, string $flipbookUrl, string $coverImage, string $style, array $debugInfo): string
    {
        $debugJson = htmlspecialchars(json_encode($debugInfo, JSON_PRETTY_PRINT));
        
        $html = '
            <div style="border: 2px solid #007cba; padding: 20px; background: #f9f9f9; margin: 20px 0;">
                <h3>📖 ' . htmlspecialchars($document['title']) . '</h3>
                
                <div style="display: flex; gap: 20px; align-items: flex-start;">
                    <div style="flex-shrink: 0;">
                        <img src="' . htmlspecialchars($coverImage) . '" alt="' . htmlspecialchars($document['title']) . '" 
                             style="max-width: 150px; border: 1px solid #ccc;" />
                    </div>
                    
                    <div style="flex-grow: 1;">
                        <p><strong>Pages:</strong> ' . ($document['total_pages'] ?? 'Unknown') . '</p>
                        <p><strong>Status:</strong> ' . $document['status'] . '</p>
                        <p><strong>Style:</strong> ' . htmlspecialchars($style) . '</p>
                        
                        <div style="margin-top: 15px;">
                            <a href="' . htmlspecialchars($flipbookUrl) . '" 
                               style="background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">
                                📖 View Flipbook →
                            </a>
                        </div>
                    </div>
                </div>
                
                <details style="margin-top: 20px;">
                    <summary style="cursor: pointer;">🔍 Debug Info</summary>
                    <pre style="background: #e9ecef; padding: 10px; font-size: 12px;">' . $debugJson . '</pre>
                </details>
            </div>
        ';
        
        return $html;
    }
}