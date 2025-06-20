<?php

declare(strict_types=1);

namespace Gmbit\FlipbookConverter\Controller;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Core\Information\Typo3Version;
use Psr\Http\Message\ResponseInterface;

class TeaserController extends ActionController
{
public function showAction(): ResponseInterface
{
    // MULTI-METHOD CONTENT LOADING DEBUG
    $contentDataMethods = $this->getContentObjectDataAlternative();
    
    // Current method
    $contentObjectData = $this->getContentObjectData();
    
    // FORCE DEBUG OUTPUT
    \TYPO3\CMS\Core\Utility\DebugUtility::debug([
        'current_method_data' => $contentObjectData,
        'alternative_methods' => $contentDataMethods,
        'tsfe_current_record' => $GLOBALS['TSFE']->currentRecord ?? 'not set',
        'request_arguments' => $this->request->getArguments(),
        'controller_info' => [
            'controller' => get_class($this),
            'action' => $this->request->getControllerActionName(),
            'extension' => $this->request->getControllerExtensionName(),
        ]
    ], 'CONTENT LOADING MULTI-METHOD DEBUG');
    
    // Try to find working content data from any method
    $workingData = $contentObjectData;
    if (empty($workingData['uid'])) {
        foreach ($contentDataMethods as $method => $data) {
            if (is_array($data) && !empty($data['uid'])) {
                $workingData = $data;
                error_log("TEASER: Using content data from method: {$method}");
                break;
            }
        }
    }
    
    // EXTRACT DOCUMENT UID
    $documentUid = (int)($workingData['tx_flipbook_teaser_document'] ?? 0);
    $targetPageUid = (int)($workingData['tx_flipbook_teaser_target_page'] ?? 0);
    $teaserStyle = $workingData['tx_flipbook_teaser_style'] ?? 'card';
    
    error_log("TEASER: Working data UID = " . ($workingData['uid'] ?? 'unknown'));
    error_log("TEASER: Working data CType = " . ($workingData['CType'] ?? 'unknown'));
    error_log("TEASER: Document UID = {$documentUid}");
    
    // TEMPLATE ASSIGNMENT
    $this->view->assignMultiple([
        'contentObjectData' => $workingData,
        'allMethods' => $contentDataMethods,
        'documentUid' => $documentUid,
        'targetPageUid' => $targetPageUid,
        'teaserStyle' => $teaserStyle,
        'workingMethod' => $this->getWorkingMethodName($contentDataMethods, $workingData),
        'showDebugInfo' => true
    ]);
    
    if (!$documentUid) {
        $this->view->assign('errorMessage', 'No document UID found from any method. Check debug info.');
        return $this->htmlResponse();
    }
    
    // SUCCESS PATH
    $document = $this->getDocument($documentUid);
    
    if ($document) {
        $this->view->assignMultiple([
            'document' => $document,
            'flipbookUrl' => "/page/{$targetPageUid}?document={$documentUid}",
            'success' => true
        ]);
    } else {
        $this->view->assign('errorMessage', "Document UID {$documentUid} not found in database");
    }
    
    return $this->htmlResponse();
}
    /**
     * Dohvati content object data iz TSFE
     */
    private function getContentObjectData(): array
    {
        if (isset($GLOBALS['TSFE']) && $GLOBALS['TSFE']->cObj instanceof ContentObjectRenderer) {
            $contentObject = $GLOBALS['TSFE']->cObj;
            return $contentObject->data ?? [];
        }
        
        return [];
    }
    
    /**
     * ISPRAVKA: Development mode check bez getApplicationContext()
     */
    private function isDevelopmentMode(): bool
    {
        // TYPO3 v11/v12 compatible development check
        if (defined('TYPO3_CONTEXT')) {
            return strpos(TYPO3_CONTEXT, 'Development') !== false;
        }
        
        // Fallback metode
        if (isset($_ENV['TYPO3_CONTEXT'])) {
            return strpos($_ENV['TYPO3_CONTEXT'], 'Development') !== false;
        }
        
        // Check preko getenv
        $context = getenv('TYPO3_CONTEXT');
        if ($context && strpos($context, 'Development') !== false) {
            return true;
        }
        
        // Settings fallback
        if (!empty($this->settings['debug'])) {
            return (bool)$this->settings['debug'];
        }
        
        // URL parameter fallback
        if (isset($_GET['debug']) || isset($_GET['show_debug'])) {
            return true;
        }
        
        // Default za development environment
        return false;
    }
    
    /**
     * Dohvati dokument po UID
     */
    private function getDocument(int $uid): ?array
    {
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
    }
    
    /**
     * Dohvati dostupne dokumente za debug
     */
    private function getAvailableDocuments(): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_flipbookconverter_document');
        
        $documents = $queryBuilder
            ->select('uid', 'title', 'status', 'hidden', 'deleted')
            ->from('tx_flipbookconverter_document')
            ->where(
                $queryBuilder->expr()->eq('deleted', 0)
            )
            ->orderBy('title')
            ->executeQuery()
            ->fetchAllAssociative();
        
        return $documents ?: [];
    }
    
    /**
     * Generiši cover image URL
     */
    private function getCoverImage(array $document): string
    {
        // Pokušaj da parsiraj processed_images
        if (!empty($document['processed_images'])) {
            $processedImages = json_decode($document['processed_images'], true);
            if (is_array($processedImages) && !empty($processedImages)) {
                $firstImage = $processedImages[0];
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
                <text x="50%" y="30%" text-anchor="middle" font-family="Arial" font-size="14" fill="#6c757d">
                    📄 ' . htmlspecialchars($document['title']) . '
                </text>
                <text x="50%" y="70%" text-anchor="middle" font-family="Arial" font-size="12" fill="#adb5bd">
                    Click to view flipbook
                </text>
            </svg>
        ');
    }
    
    /**
     * Generiši flipbook URL
     */
    private function generateFlipbookUrl(int $documentUid, int $targetPageUid): string
    {
        if (!$targetPageUid) {
            return "#no-target-page-configured";
        }
        
        if (isset($GLOBALS['TSFE']) && $GLOBALS['TSFE']->cObj instanceof ContentObjectRenderer) {
            $cObj = $GLOBALS['TSFE']->cObj;
            
            try {
                $url = $cObj->typoLink_URL([
                    'parameter' => $targetPageUid,
                    'additionalParams' => '&document=' . $documentUid,
                    'forceAbsoluteUrl' => false
                ]);
                
                return $url;
            } catch (\Exception $e) {
                // Fallback ako typoLink_URL ne radi
                return "/page/{$targetPageUid}?document={$documentUid}";
            }
        }
        
        // Fallback manual URL
        return "/page/{$targetPageUid}?document={$documentUid}";
    }
    
    /**
     * Debug podatci
     */
    private function getDebugData(array $document, array $contentObjectData): array
    {
        // TYPO3 version detection
        $typo3Version = 'unknown';
        if (class_exists(Typo3Version::class)) {
            $versionObj = GeneralUtility::makeInstance(Typo3Version::class);
            $typo3Version = $versionObj->getVersion();
        } elseif (defined('TYPO3_version')) {
            $typo3Version = TYPO3_version;
        }
        
        return [
            'controller_version' => 'VERSION 2.1 - TYPO3 Compatible',
            'timestamp' => date('Y-m-d H:i:s'),
            'typo3_version' => $typo3Version,
            'typo3_context' => defined('TYPO3_CONTEXT') ? TYPO3_CONTEXT : 'unknown',
            'document_info' => [
                'uid' => $document['uid'],
                'title' => $document['title'],
                'status' => $document['status'],
                'total_pages' => $document['total_pages'] ?? 'unknown',
                'processed_images_length' => strlen($document['processed_images'] ?? ''),
            ],
            'content_element_info' => [
                'uid' => $contentObjectData['uid'] ?? 'unknown',
                'ctype' => $contentObjectData['CType'] ?? 'unknown',
                'header' => $contentObjectData['header'] ?? 'no header',
            ],
            'teaser_fields' => [
                'document_uid' => $contentObjectData['tx_flipbook_teaser_document'] ?? 'NOT SET',
                'target_page' => $contentObjectData['tx_flipbook_teaser_target_page'] ?? 'NOT SET',
                'style' => $contentObjectData['tx_flipbook_teaser_style'] ?? 'NOT SET',
            ],
            'database_info' => [
                'total_documents' => count($this->getAvailableDocuments()),
                'completed_documents' => $this->getCompletedDocumentsCount(),
            ],
        ];
    }
    
    /**
     * Broj completed dokumenata
     */
    private function getCompletedDocumentsCount(): int
    {
        try {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable('tx_flipbookconverter_document');
            
            return (int)$queryBuilder
                ->count('uid')
                ->from('tx_flipbookconverter_document')
                ->where(
                    $queryBuilder->expr()->eq('deleted', 0),
                    $queryBuilder->expr()->eq('status', 2)
                )
                ->executeQuery()
                ->fetchOne();
        } catch (\Exception $e) {
            return 0;
        }
    }

/**
 * Dohvati document UID iz različitih izvora
 */
private function getDocumentUidHybrid(): int
{
    // 1. PRIORITET: TypoScript settings (može biti array ili string)
    if (!empty($this->settings['documentUid'])) {
        $value = $this->settings['documentUid'];
        
        // Ako je array (TypoScript field mapping), ignoriši
        if (is_array($value)) {
            error_log("TEASER: documentUid is array (TypoScript field config): " . json_encode($value));
        } else {
            // Ako je string/int, koristi
            $uid = (int)$value;
            if ($uid > 0) {
                error_log("TEASER: Document UID from TypoScript settings: {$uid}");
                return $uid;
            }
        }
    }
    
    // 2. FALLBACK: Content object data (direktno čitanje)
    $contentObjectData = $this->getContentObjectData();
    if (!empty($contentObjectData['tx_flipbook_teaser_document'])) {
        $uid = (int)$contentObjectData['tx_flipbook_teaser_document'];
        if ($uid > 0) {
            error_log("TEASER: Document UID from content object: {$uid}");
            return $uid;
        }
    }
    
    // 3. FALLBACK: URL parametar
    if ($this->request->hasArgument('document')) {
        $uid = (int)$this->request->getArgument('document');
        if ($uid > 0) {
            error_log("TEASER: Document UID from URL parameter: {$uid}");
            return $uid;
        }
    }
    
    error_log("TEASER: No document UID found from any source");
    return 0;
}

/**
 * Dohvati target page UID
 */
private function getTargetPageUidHybrid(): int
{
    // TypoScript settings
    if (!empty($this->settings['targetPageUid'])) {
        $value = $this->settings['targetPageUid'];
        
        // Skip ako je array
        if (!is_array($value)) {
            $uid = (int)$value;
            if ($uid > 0) {
                return $uid;
            }
        }
    }
    
    // Content object data fallback
    $contentObjectData = $this->getContentObjectData();
    return (int)($contentObjectData['tx_flipbook_teaser_target_page'] ?? 0);
}

/**
 * Dohvati teaser style
 */
private function getTeaserStyleHybrid(): string
{
    // TypoScript settings
    if (!empty($this->settings['teaserStyle'])) {
        $value = $this->settings['teaserStyle'];
        
        // Skip ako je array
        if (!is_array($value) && is_string($value)) {
            return $value;
        }
    }
    
    // Content object data fallback
    $contentObjectData = $this->getContentObjectData();
    $style = $contentObjectData['tx_flipbook_teaser_style'] ?? 'card';
    
    // Ensure it's a string
    return is_string($style) ? $style : 'card';
}


// ========================================
// HELPER ZA SAFE VALUE EXTRACTION
// ========================================

/**
 * Safely extract value from TypoScript settings
 */
private function extractSettingValue($key, $default = null)
{
    if (!isset($this->settings[$key])) {
        return $default;
    }
    
    $value = $this->settings[$key];
    
    // Ako je array (TypoScript field config), vrati default
    if (is_array($value)) {
        error_log("TEASER: Setting '{$key}' is array, using fallback: " . json_encode($value));
        return $default;
    }
    
    return $value;
}
// ========================================
// IZMIJENI showAction() metodu:
// ========================================



// ========================================
// HELPER METODE ZA DEBUG
// ========================================

private function getDocumentUidSource(): string
{
    if (!empty($this->settings['documentUid'])) return 'TypoScript settings';
    $contentObjectData = $this->getContentObjectData();
    if (!empty($contentObjectData['tx_flipbook_teaser_document'])) return 'Content object data';
    if ($this->request->hasArgument('document')) return 'URL parameter';
    return 'Not found';
}

private function getTargetPageSource(): string
{
    if (!empty($this->settings['targetPageUid'])) return 'TypoScript settings';
    $contentObjectData = $this->getContentObjectData();
    if (!empty($contentObjectData['tx_flipbook_teaser_target_page'])) return 'Content object data';
    return 'Not found';
}

private function getStyleSource(): string
{
    if (!empty($this->settings['teaserStyle'])) return 'TypoScript settings';
    $contentObjectData = $this->getContentObjectData();
    if (!empty($contentObjectData['tx_flipbook_teaser_style'])) return 'Content object data';
    return 'Default (card)';
}

/**
 * Determine which method provided working data
 */
private function getWorkingMethodName(array $methods, array $workingData): string
{
    if (empty($workingData['uid'])) {
        return 'none - no working data found';
    }
    
    foreach ($methods as $method => $data) {
        if (is_array($data) && ($data['uid'] ?? 0) == ($workingData['uid'] ?? 0)) {
            return $method;
        }
    }
    
    return 'original_method';
}
/**
 * Alternative content data loading methods
 */
private function getContentObjectDataAlternative(): array
{
    $data = [];
    
    // METHOD 1: TSFE cObj (trenutni pristup)
    if (isset($GLOBALS['TSFE']) && $GLOBALS['TSFE']->cObj instanceof ContentObjectRenderer) {
        $data['method1_tsfe_cobj'] = $GLOBALS['TSFE']->cObj->data ?? [];
    }
    
    // METHOD 2: Request attributes
    $request = $this->request;
    if (method_exists($request, 'getAttribute')) {
        $data['method2_request_content'] = $request->getAttribute('currentContentObject') ?? [];
    }
    
    // METHOD 3: ConfigurationManager
    try {
        $contentObject = $this->configurationManager->getContentObject();
        if ($contentObject) {
            $data['method3_config_manager'] = $contentObject->data ?? [];
        }
    } catch (\Exception $e) {
        $data['method3_error'] = $e->getMessage();
    }
    
    // METHOD 4: Direct database lookup
    $contentUid = $this->getContentUidFromContext();
    if ($contentUid) {
        $data['method4_direct_db'] = $this->loadContentFromDatabase($contentUid);
    }
    
    return $data;
}

/**
 * Get content UID from various sources
 */
private function getContentUidFromContext(): int
{
    // URL parameter
    if ($this->request->hasArgument('cid')) {
        return (int)$this->request->getArgument('cid');
    }
    
    // TSFE current content
    if (isset($GLOBALS['TSFE']->currentRecord) && strpos($GLOBALS['TSFE']->currentRecord, 'tt_content:') === 0) {
        return (int)substr($GLOBALS['TSFE']->currentRecord, 11);
    }
    
    // Check cObj data for uid
    if (isset($GLOBALS['TSFE']) && $GLOBALS['TSFE']->cObj) {
        $data = $GLOBALS['TSFE']->cObj->data ?? [];
        if (!empty($data['uid'])) {
            return (int)$data['uid'];
        }
    }
    
    return 0;
}

/**
 * Load content directly from database
 */
private function loadContentFromDatabase(int $uid): array
{
    try {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tt_content');
        
        $content = $queryBuilder
            ->select('*')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAssociative();
        
        return $content ?: [];
    } catch (\Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

}