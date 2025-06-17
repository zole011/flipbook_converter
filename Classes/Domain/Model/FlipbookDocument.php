<?php

declare(strict_types=1);

namespace Gmbit\FlipbookConverter\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * Model za FlipbookDocument
 */
/**
 * @TYPO3\CMS\Extbase\Annotation\Entity(tableName="tx_flipbookconverter_document")
 */
class FlipbookDocument extends AbstractEntity
{
    /**
     * @var string
     */
    protected string $title = '';

    /**
     * @var string
     */
    protected string $description = '';

    /**
     * @var FileReference|null
     */
    protected ?FileReference $pdfFile = null;

    /**
     * @var int
     */
    protected int $status = 0;

    /**
     * Status konstante
     */
    public const STATUS_PENDING = 0;
    public const STATUS_PROCESSING = 1;
    public const STATUS_COMPLETED = 2;
    public const STATUS_ERROR = 3;

    /**
     * @var string
     */
    protected string $processedImages = '';

    /**
     * @var string
     */
    protected string $processingLog = '';

    /**
     * @var int
     */
    protected int $totalPages = 0;

    /**
     * @var int
     */
    protected int $fileSize = 0;

    /**
     * @var string
     */
    protected string $fileHash = '';

    /**
     * @var string
     */
    protected string $flipbookConfig = '';

    /**
     * @var int
     */
    protected int $processingTime = 0;

    /**
     * @var \DateTime|null
     */
    protected ?\DateTime $lastProcessed = null;

    /**
     * @var ObjectStorage<FileReference>
     */
    protected ObjectStorage $images;

    public function __construct()
    {
        $this->images = new ObjectStorage();
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return FileReference|null
     */
    public function getPdfFile(): ?FileReference
    {
        return $this->pdfFile;
    }

    /**
     * @param FileReference|null $pdfFile
     */
    public function setPdfFile(?FileReference $pdfFile): void
    {
        $this->pdfFile = $pdfFile;
    }

    /**
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @param int $status
     */
    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    /**
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * @return bool
     */
    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /**
     * @return bool
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * @return bool
     */
    public function hasError(): bool
    {
        return $this->status === self::STATUS_ERROR;
    }

    /**
     * @return string
     */
    public function getStatusLabel(): string
    {
        switch ($this->status) {
            case self::STATUS_PENDING:
                return 'Pending';
            case self::STATUS_PROCESSING:
                return 'Processing';
            case self::STATUS_COMPLETED:
                return 'Completed';
            case self::STATUS_ERROR:
                return 'Error';
            default:
                return 'Unknown';
        }
    }

    /**
     * @return array
     */
    public function getProcessedImages(): array
    {
        if (empty($this->processedImages)) {
            return [];
        }
        
        $decoded = json_decode($this->processedImages, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array $images
     */
    public function setProcessedImages(array $images): void
    {
        $this->processedImages = json_encode($images);
    }

    /**
     * @return string
     */
    public function getProcessingLog(): string
    {
        return $this->processingLog;
    }

    /**
     * @param string $processingLog
     */
    public function setProcessingLog(string $processingLog): void
    {
        $this->processingLog = $processingLog;
    }

    /**
     * @param string $logEntry
     */
    public function addToProcessingLog(string $logEntry): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $this->processingLog .= "[{$timestamp}] {$logEntry}\n";
    }

    /**
     * @return int
     */
    public function getTotalPages(): int
    {
        return $this->totalPages;
    }

    /**
     * @param int $totalPages
     */
    public function setTotalPages(int $totalPages): void
    {
        $this->totalPages = $totalPages;
    }

    /**
     * @return int
     */
    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    /**
     * @param int $fileSize
     */
    public function setFileSize(int $fileSize): void
    {
        $this->fileSize = $fileSize;
    }

    /**
     * @return string
     */
    public function getFileSizeFormatted(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = $this->fileSize;
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * @return string
     */
    public function getFileHash(): string
    {
        return $this->fileHash;
    }

    /**
     * @param string $fileHash
     */
    public function setFileHash(string $fileHash): void
    {
        $this->fileHash = $fileHash;
    }

    /**
     * @return array
     */
    public function getFlipbookConfig(): array
    {
        if (empty($this->flipbookConfig)) {
            return $this->getDefaultConfig();
        }
        
        $decoded = json_decode($this->flipbookConfig, true);
        return is_array($decoded) ? array_merge($this->getDefaultConfig(), $decoded) : $this->getDefaultConfig();
    }

    /**
     * @param array $config
     */
    public function setFlipbookConfig(array $config): void
    {
        $this->flipbookConfig = json_encode($config);
    }

    /**
     * @return array
     */
    protected function getDefaultConfig(): array
    {
        return [
            'width' => 800,
            'height' => 600,
            'backgroundColor' => '#ffffff',
            'showControls' => true,
            'showPageNumbers' => true,
            'enableZoom' => true,
            'enableFullscreen' => true,
            'autoplay' => false,
            'autoplayDelay' => 3000,
            'enableKeyboard' => true,
            'enableTouch' => true,
            'animationDuration' => 500,
        ];
    }

    /**
     * @return int
     */
    public function getProcessingTime(): int
    {
        return $this->processingTime;
    }

    /**
     * @param int $processingTime
     */
    public function setProcessingTime(int $processingTime): void
    {
        $this->processingTime = $processingTime;
    }

    /**
     * @return \DateTime|null
     */
    public function getLastProcessed(): ?\DateTime
    {
        return $this->lastProcessed;
    }

    /**
     * @param \DateTime|null $lastProcessed
     */
    public function setLastProcessed(?\DateTime $lastProcessed): void
    {
        $this->lastProcessed = $lastProcessed;
    }

    /**
     * @return ObjectStorage<FileReference>
     */
    public function getImages(): ObjectStorage
    {
        return $this->images;
    }

    /**
     * @param ObjectStorage<FileReference> $images
     */
    public function setImages(ObjectStorage $images): void
    {
        $this->images = $images;
    }

    /**
     * @param FileReference $image
     */
    public function addImage(FileReference $image): void
    {
        $this->images->attach($image);
    }

    /**
     * @param FileReference $image
     */
    public function removeImage(FileReference $image): void
    {
        $this->images->detach($image);
    }
}