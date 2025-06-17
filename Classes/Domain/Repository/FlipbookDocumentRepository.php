<?php

declare(strict_types=1);

namespace Gmbit\FlipbookConverter\Domain\Repository;

use Gmbit\FlipbookConverter\Domain\Model\FlipbookDocument;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

/**
 * Repository za FlipbookDocument
 */
/**
 * @TYPO3\CMS\Extbase\Annotation\Entity(tableName="tx_flipbookconverter_document")
 */
class FlipbookDocumentRepository extends Repository
{
    /**
     * Default ordering po creation date
     *
     * @var array
     */
    protected $defaultOrderings = [
        'crdate' => QueryInterface::ORDER_DESCENDING,
    ];

    /**
     * Pronađi dokumente po statusu
     *
     * @param int $status
     * @return QueryResultInterface
     */
    public function findByStatus(int $status): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->matching(
            $query->equals('status', $status)
        );
        
        return $query->execute();
    }

    /**
     * Pronađi pending dokumente za processing
     *
     * @return QueryResultInterface
     */
    public function findPendingDocuments(): QueryResultInterface
    {
        return $this->findByStatus(FlipbookDocument::STATUS_PENDING);
    }

    /**
     * Pronađi dokumente u processing stanju
     *
     * @return QueryResultInterface
     */
    public function findProcessingDocuments(): QueryResultInterface
    {
        return $this->findByStatus(FlipbookDocument::STATUS_PROCESSING);
    }

    /**
     * Pronađi completed dokumente
     *
     * @return QueryResultInterface
     */
    public function findCompletedDocuments(): QueryResultInterface
    {
        return $this->findByStatus(FlipbookDocument::STATUS_COMPLETED);
    }

    /**
     * Pronađi dokumente sa greškama
     *
     * @return QueryResultInterface
     */
    public function findErrorDocuments(): QueryResultInterface
    {
        return $this->findByStatus(FlipbookDocument::STATUS_ERROR);
    }

    /**
     * Pronađi dokument po file hash-u
     *
     * @param string $fileHash
     * @return FlipbookDocument|null
     */
    public function findByFileHash(string $fileHash): ?FlipbookDocument
    {
        $query = $this->createQuery();
        $query->matching(
            $query->equals('fileHash', $fileHash)
        );
        
        return $query->execute()->getFirst();
    }

    /**
     * Dobiti statistike
     *
     * @return array
     */
    public function getStatistics(): array
    {
        return [
            'total' => $this->countAll(),
            'pending' => $this->countByStatus(FlipbookDocument::STATUS_PENDING),
            'processing' => $this->countByStatus(FlipbookDocument::STATUS_PROCESSING),
            'completed' => $this->countByStatus(FlipbookDocument::STATUS_COMPLETED),
            'error' => $this->countByStatus(FlipbookDocument::STATUS_ERROR),
        ];
    }

    /**
     * Pronađi dokumente za cleanup (stari error ili processing dokumenti)
     *
     * @param int $maxAge Maksimalna starost u sekundama
     * @return QueryResultInterface
     */
    public function findDocumentsForCleanup(int $maxAge = 86400): QueryResultInterface
    {
        $cutoffTime = time() - $maxAge;
        
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd(
                $query->logicalOr(
                    $query->equals('status', FlipbookDocument::STATUS_PROCESSING),
                    $query->equals('status', FlipbookDocument::STATUS_ERROR)
                ),
                $query->lessThan('tstamp', $cutoffTime)
            )
        );
        
        return $query->execute();
    }

    /**
     * Pronađi najnovije dokumente
     *
     * @param int $limit
     * @return QueryResultInterface
     */
    public function findLatest(int $limit = 10): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->setLimit($limit);
        $query->setOrderings(['crdate' => QueryInterface::ORDER_DESCENDING]);
        
        return $query->execute();
    }

    /**
     * Pretraži dokumente po title-u
     *
     * @param string $searchTerm
     * @return QueryResultInterface
     */
    public function searchByTitle(string $searchTerm): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->matching(
            $query->like('title', '%' . $searchTerm . '%')
        );
        
        return $query->execute();
    }

    /**
     * Pronađi dokumente sa određenim brojem stranica
     *
     * @param int $minPages
     * @param int $maxPages
     * @return QueryResultInterface
     */
    public function findByPageRange(int $minPages, int $maxPages): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd(
                $query->greaterThanOrEqual('totalPages', $minPages),
                $query->lessThanOrEqual('totalPages', $maxPages)
            )
        );
        
        return $query->execute();
    }

    /**
     * Pronađi dokumente veće od određene veličine
     *
     * @param int $minSize Minimalna veličina u bajtovima
     * @return QueryResultInterface
     */
    public function findByMinFileSize(int $minSize): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->matching(
            $query->greaterThanOrEqual('fileSize', $minSize)
        );
        
        return $query->execute();
    }

    /**
     * Dobiti ukupnu veličinu svih dokumenata
     *
     * @return int
     */
    public function getTotalFileSize(): int
    {
        $query = $this->createQuery();
        $query->statement('SELECT SUM(file_size) as total_size FROM tx_flipbookconverter_document WHERE deleted = 0');
        $result = $query->execute(true);
        
        return (int)($result[0]['total_size'] ?? 0);
    }

    /**
     * Dobiti average processing time
     *
     * @return float
     */
    public function getAverageProcessingTime(): float
    {
        $query = $this->createQuery();
        $query->statement('SELECT AVG(processing_time) as avg_time FROM tx_flipbookconverter_document WHERE processing_time > 0 AND deleted = 0');
        $result = $query->execute(true);
        
        return (float)($result[0]['avg_time'] ?? 0);
    }

    /**
     * Dobiti dokumente grupovane po datumu kreiranja
     *
     * @param int $days Broj dana unazad
     * @return array
     */
    public function getCreationStatistics(int $days = 30): array
    {
        $cutoffTime = time() - ($days * 86400);
        
        $query = $this->createQuery();
        $query->statement(
            'SELECT DATE(FROM_UNIXTIME(crdate)) as date, COUNT(*) as count 
             FROM tx_flipbookconverter_document 
             WHERE crdate > ? AND deleted = 0 
             GROUP BY DATE(FROM_UNIXTIME(crdate)) 
             ORDER BY date DESC',
            [$cutoffTime]
        );
        
        return $query->execute(true);
    }

    /**
     * Bulk update status-a
     *
     * @param array $uids
     * @param int $newStatus
     * @return int Broj ažuriranih redova
     */
    public function bulkUpdateStatus(array $uids, int $newStatus): int
    {
        if (empty($uids)) {
            return 0;
        }
        
        $query = $this->createQuery();
        $placeholders = str_repeat('?,', count($uids) - 1) . '?';
        $params = array_merge([$newStatus, time()], $uids);
        
        $query->statement(
            "UPDATE tx_flipbookconverter_document 
             SET status = ?, tstamp = ? 
             WHERE uid IN ({$placeholders}) AND deleted = 0",
            $params
        );
        
        return $query->execute(true);
    }
    /**
     * Count all documents
     */
    public function countAll(): int
    {
        $documents = $this->findAll();
        return count($documents);
    }

    /**
     * Find recent documents
     */
    public function findRecent(int $limit = 10): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->setOrderings(['crdate' => QueryInterface::ORDER_DESCENDING]);
        $query->setLimit($limit);
        return $query->execute();
    }

    /**
     * Count documents by status
     *
     * @param int $status
     * @return int
     */
    public function countByStatus(int $status): int
    {
        $query = $this->createQuery();
        $query->statement(
            'SELECT COUNT(*) AS count FROM tx_flipbookconverter_document WHERE status = ? AND deleted = 0',
            [$status]
        );
        $result = $query->execute(true);
        return (!empty($result) && isset($result[0]['count'])) ? (int)$result[0]['count'] : 0;
    }
}