<?php
declare(strict_types=1);

namespace Gmbit\FlipbookConverter\Preview;

use TYPO3\CMS\Backend\Preview\PreviewRendererInterface;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem;

class FlipbookPreviewRenderer implements PreviewRendererInterface
{
    /**
     * Render preview
     */
    public function renderPageModulePreviewContent(GridColumnItem $item): string
    {
        $record = $item->getRecord();
        $content = [];
        
        if ($record['flipbook_document']) {
            $content[] = '<strong>Document ID:</strong> ' . $record['flipbook_document'];
        }
        
        if ($record['flipbook_width']) {
            $content[] = '<strong>Width:</strong> ' . $record['flipbook_width'];
        }
        
        if ($record['flipbook_height']) {
            $content[] = '<strong>Height:</strong> ' . $record['flipbook_height'];
        }
        
        return implode('<br>', $content);
    }
    
    /**
     * Render preview header
     */
    public function renderPageModulePreviewHeader(GridColumnItem $item): string
    {
        return '';
    }
    
    /**
     * Render preview footer
     */
    public function renderPageModulePreviewFooter(GridColumnItem $item): string
    {
        return '';
    }
    
    /**
     * Wrap preview content  
     */
    public function wrapPageModulePreview(string $previewHeader, string $previewContent, GridColumnItem $item): string
    {
        return $previewHeader . $previewContent;
    }
}