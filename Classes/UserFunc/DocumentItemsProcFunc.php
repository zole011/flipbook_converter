<?php

declare(strict_types=1);

namespace Gmbit\FlipbookConverter\UserFunc;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Doctrine\DBAL\ParameterType;

/**
 * Custom ItemsProcFunc za učitavanje flipbook dokumenata
 */
class DocumentItemsProcFunc
{
    /**
     * Dobij flipbook dokumente za TCA select field
     *
     * @param array $config
     */
    public function getFlipbookDocuments(array &$config): void
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connection = $connectionPool->getConnectionForTable('tx_flipbookconverter_document');

        $queryBuilder = $connection->createQueryBuilder();
        
        // RAW query koji ignoriše sve enable fields
        $result = $queryBuilder
            ->select('uid', 'title', 'status', 'hidden', 'deleted', 'total_pages')
            ->from('tx_flipbookconverter_document')
            ->where(
                $queryBuilder->expr()->eq('status', $queryBuilder->createNamedParameter(2, ParameterType::INTEGER))
            )
            ->orderBy('title', 'ASC')
            ->executeQuery();

        while ($row = $result->fetchAssociative()) {
            $label = $row['title'];
            
            // Dodaj dodatne info za debugging
            $status = [];
            if ($row['hidden']) $status[] = 'HIDDEN';
            if ($row['deleted']) $status[] = 'DELETED';
            if (!empty($status)) {
                $label .= ' [' . implode(', ', $status) . ']';
            }
            
            $label .= ' (Pages: ' . $row['total_pages'] . ')';

            $config['items'][] = [
                'label' => $label,
                'value' => (int)$row['uid'],
            ];
        }
    }
}