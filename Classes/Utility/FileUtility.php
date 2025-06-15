<?php

declare(strict_types=1);

namespace Gmbit\FlipbookConverter\Utility;

use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Log\Logger;

/**
 * Utility class za file operations
 */
class FileUtility
{
    protected static Logger $logger;

    /**
     * Initialize logger
     */
    protected static function getLogger(): Logger
    {
        if (!isset(self::$logger)) {
            self::$logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        }
        return self::$logger;
    }

    /**
     * Generate unique filename
     *
     * @param string $originalName
     * @param string $extension
     * @return string
     */
    public static function generateUniqueFilename(string $originalName, string $extension = ''): string
    {
        $timestamp = time();
        $random = bin2hex(random_bytes(4));
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $cleanBaseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
        
        if (empty($extension)) {
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        }
        
        return $cleanBaseName . '_' . $timestamp . '_' . $random . '.' . $extension;
    }

    /**
     * Calculate file hash
     *
     * @param string $filePath
     * @param string $algorithm
     * @return string|null
     */
    public static function calculateFileHash(string $filePath, string $algorithm = 'sha256'): ?string
    {
        try {
            if (!file_exists($filePath)) {
                return null;
            }
            
            return hash_file($algorithm, $filePath);
            
        } catch (\Exception $e) {
            self::getLogger()->error('File hash calculation failed', [
                'error' => $e->getMessage(),
                'file' => $filePath,
                'algorithm' => $algorithm
            ]);
            return null;
        }
    }

    /**
     * Validate PDF file
     *
     * @param string $filePath
     * @return array [isValid, errors]
     */
    public static function validatePdfFile(string $filePath): array
    {
        $errors = [];
        $isValid = true;

        try {
            // Check if file exists
            if (!file_exists($filePath)) {
                $errors[] = 'File does not exist';
                return [false, $errors];
            }

            // Check file size
            $fileSize = filesize($filePath);
            if ($fileSize === false || $fileSize === 0) {
                $errors[] = 'File is empty or unreadable';
                $isValid = false;
            }

            // Check maximum file size (100MB default)
            $maxSize = 100 * 1024 * 1024; // 100MB
            if ($fileSize > $maxSize) {
                $errors[] = 'File size exceeds maximum allowed size (' . self::formatBytes($maxSize) . ')';
                $isValid = false;
            }

            // Check MIME type
            $mimeType = mime_content_type($filePath);
            if ($mimeType !== 'application/pdf') {
                $errors[] = 'File is not a valid PDF (detected MIME type: ' . $mimeType . ')';
                $isValid = false;
            }

            // Check PDF signature
            $handle = fopen($filePath, 'rb');
            if ($handle) {
                $header = fread($handle, 5);
                fclose($handle);
                
                if (substr($header, 0, 4) !== '%PDF') {
                    $errors[] = 'File does not have a valid PDF signature';
                    $isValid = false;
                }
            }

            // Try to get PDF info using ImageMagick if available
            if (extension_loaded('imagick') && $isValid) {
                try {
                    $imagick = new \Imagick();
                    $imagick->pingImage($filePath);
                    $pageCount = $imagick->getNumberImages();
                    $imagick->clear();
                    
                    if ($pageCount === 0) {
                        $errors[] = 'PDF appears to have no pages';
                        $isValid = false;
                    }
                    
                } catch (\Exception $e) {
                    $errors[] = 'PDF validation failed: ' . $e->getMessage();
                    $isValid = false;
                }
            }

        } catch (\Exception $e) {
            $errors[] = 'Validation error: ' . $e->getMessage();
            $isValid = false;
        }

        return [$isValid, $errors];
    }

    /**
     * Get PDF page count
     *
     * @param string $filePath
     * @return int|null
     */
    public static function getPdfPageCount(string $filePath): ?int
    {
        try {
            if (extension_loaded('imagick')) {
                $imagick = new \Imagick();
                $imagick->pingImage($filePath);
                $pageCount = $imagick->getNumberImages();
                $imagick->clear();
                return $pageCount;
            }
            
            // Fallback method - count page objects in PDF
            $content = file_get_contents($filePath);
            if ($content === false) {
                return null;
            }
            
            $pageCount = preg_match_all('/\/Type\s*\/Page\W/', $content);
            return $pageCount > 0 ? $pageCount : null;
            
        } catch (\Exception $e) {
            self::getLogger()->error('PDF page count failed', [
                'error' => $e->getMessage(),
                'file' => $filePath
            ]);
            return null;
        }
    }

    /**
     * Create folder structure in storage
     *
     * @param ResourceStorage $storage
     * @param string $folderPath
     * @return Folder|null
     */
    public static function createFolderStructure(ResourceStorage $storage, string $folderPath): ?Folder
    {
        try {
            $folderParts = explode('/', trim($folderPath, '/'));
            $currentFolder = $storage->getRootLevelFolder();
            
            foreach ($folderParts as $folderName) {
                if (empty($folderName)) {
                    continue;
                }
                
                try {
                    $currentFolder = $storage->getFolder($currentFolder->getIdentifier() . $folderName);
                } catch (\Exception $e) {
                    $currentFolder = $storage->createFolder($folderName, $currentFolder);
                }
            }
            
            return $currentFolder;
            
        } catch (\Exception $e) {
            self::getLogger()->error('Folder structure creation failed', [
                'error' => $e->getMessage(),
                'path' => $folderPath
            ]);
            return null;
        }
    }

    /**
     * Clean up temporary files
     *
     * @param array $filePaths
     * @return int Number of files successfully deleted
     */
    public static function cleanupTempFiles(array $filePaths): int
    {
        $deletedCount = 0;
        
        foreach ($filePaths as $filePath) {
            try {
                if (file_exists($filePath) && is_writable($filePath)) {
                    if (unlink($filePath)) {
                        $deletedCount++;
                    }
                }
            } catch (\Exception $e) {
                self::getLogger()->warning('Failed to delete temporary file', [
                    'error' => $e->getMessage(),
                    'file' => $filePath
                ]);
            }
        }
        
        return $deletedCount;
    }

    /**
     * Get file extension from MIME type
     *
     * @param string $mimeType
     * @return string
     */
    public static function getExtensionFromMimeType(string $mimeType): string
    {
        $mimeToExt = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
        ];
        
        return $mimeToExt[$mimeType] ?? '';
    }

    /**
     * Format file size in human readable format
     *
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Check if file is image
     *
     * @param string $filePath
     * @return bool
     */
    public static function isImage(string $filePath): bool
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        return in_array($extension, $imageExtensions);
    }

    /**
     * Check if file is PDF
     *
     * @param string $filePath
     * @return bool
     */
    public static function isPdf(string $filePath): bool
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return $extension === 'pdf';
    }

    /**
     * Create temporary file with unique name
     *
     * @param string $prefix
     * @param string $extension
     * @return string
     */
    public static function createTempFile(string $prefix = 'flipbook_', string $extension = 'tmp'): string
    {
        $tempDir = sys_get_temp_dir();
        $filename = $prefix . uniqid() . '.' . $extension;
        return $tempDir . DIRECTORY_SEPARATOR . $filename;
    }

    /**
     * Copy file to storage
     *
     * @param string $sourcePath
     * @param ResourceStorage $storage
     * @param Folder $targetFolder
     * @param string $filename
     * @param string $conflictMode
     * @return File|null
     */
    public static function copyFileToStorage(
        string $sourcePath,
        ResourceStorage $storage,
        Folder $targetFolder,
        string $filename,
        string $conflictMode = 'replace'
    ): ?File {
        try {
            if (!file_exists($sourcePath)) {
                return null;
            }
            
            return $storage->addFile($sourcePath, $targetFolder, $filename, $conflictMode);
            
        } catch (\Exception $e) {
            self::getLogger()->error('File copy to storage failed', [
                'error' => $e->getMessage(),
                'source' => $sourcePath,
                'target' => $targetFolder->getIdentifier() . $filename
            ]);
            return null;
        }
    }

    /**
     * Check disk space availability
     *
     * @param string $path
     * @param int $requiredBytes
     * @return bool
     */
    public static function hasEnoughDiskSpace(string $path, int $requiredBytes): bool
    {
        try {
            $freeBytes = disk_free_space($path);
            return $freeBytes !== false && $freeBytes >= $requiredBytes;
            
        } catch (\Exception $e) {
            self::getLogger()->warning('Disk space check failed', [
                'error' => $e->getMessage(),
                'path' => $path
            ]);
            return false;
        }
    }

    /**
     * Sanitize filename for safe usage
     *
     * @param string $filename
     * @return string
     */
    public static function sanitizeFilename(string $filename): string
    {
        // Remove or replace dangerous characters
        $filename = preg_replace('/[^\w\s\-\.\(\)]/', '_', $filename);
        
        // Remove multiple consecutive underscores/spaces
        $filename = preg_replace('/[_\s]+/', '_', $filename);
        
        // Trim underscores from start and end
        $filename = trim($filename, '_');
        
        // Ensure filename is not too long
        if (strlen($filename) > 100) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $basename = pathinfo($filename, PATHINFO_FILENAME);
            $filename = substr($basename, 0, 100 - strlen($extension) - 1) . '.' . $extension;
        }
        
        return $filename;
    }

    /**
     * Get MIME type of file
     *
     * @param string $filePath
     * @return string|null
     */
    public static function getMimeType(string $filePath): ?string
    {
        try {
            if (!file_exists($filePath)) {
                return null;
            }
            
            // Try different methods
            if (function_exists('finfo_file')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $filePath);
                finfo_close($finfo);
                if ($mimeType) {
                    return $mimeType;
                }
            }
            
            if (function_exists('mime_content_type')) {
                return mime_content_type($filePath);
            }
            
            // Fallback based on extension
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $extToMime = [
                'pdf' => 'application/pdf',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'svg' => 'image/svg+xml',
            ];
            
            return $extToMime[$extension] ?? 'application/octet-stream';
            
        } catch (\Exception $e) {
            self::getLogger()->error('MIME type detection failed', [
                'error' => $e->getMessage(),
                'file' => $filePath
            ]);
            return null;
        }
    }
}