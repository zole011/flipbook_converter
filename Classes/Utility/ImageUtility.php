<?php

declare(strict_types=1);

namespace Gmbit\FlipbookConverter\Utility;

use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Log\Logger;

/**
 * Utility class za image processing operacije
 */
class ImageUtility
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
     * Resize image using ImageMagick
     *
     * @param string $sourcePath
     * @param string $targetPath
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @return bool
     */
    public static function resizeImage(string $sourcePath, string $targetPath, int $width, int $height, bool $crop = false): bool
    {
        try {
            if (!extension_loaded('imagick')) {
                self::getLogger()->warning('ImageMagick extension not available');
                return self::resizeImageWithGD($sourcePath, $targetPath, $width, $height, $crop);
            }

            $imagick = new \Imagick($sourcePath);
            
            if ($crop) {
                // Crop to exact dimensions
                $imagick->cropThumbnailImage($width, $height);
            } else {
                // Resize maintaining aspect ratio
                $imagick->resizeImage($width, $height, \Imagick::FILTER_LANCZOS, 1, true);
            }
            
            // Optimize image
            $imagick->setImageCompressionQuality(90);
            $imagick->stripImage(); // Remove metadata
            
            // Write to target
            $imagick->writeImage($targetPath);
            $imagick->clear();
            
            return true;
            
        } catch (\Exception $e) {
            self::getLogger()->error('ImageMagick resize failed', [
                'error' => $e->getMessage(),
                'source' => $sourcePath,
                'target' => $targetPath
            ]);
            
            // Fallback to GD
            return self::resizeImageWithGD($sourcePath, $targetPath, $width, $height, $crop);
        }
    }

    /**
     * Resize image using GD library (fallback)
     *
     * @param string $sourcePath
     * @param string $targetPath
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @return bool
     */
    protected static function resizeImageWithGD(string $sourcePath, string $targetPath, int $width, int $height, bool $crop = false): bool
    {
        try {
            if (!extension_loaded('gd')) {
                self::getLogger()->error('Neither ImageMagick nor GD extension available');
                return false;
            }

            $imageInfo = getimagesize($sourcePath);
            if (!$imageInfo) {
                return false;
            }

            [$sourceWidth, $sourceHeight, $imageType] = $imageInfo;

            // Create source image
            switch ($imageType) {
                case IMAGETYPE_JPEG:
                    $sourceImage = imagecreatefromjpeg($sourcePath);
                    break;
                case IMAGETYPE_PNG:
                    $sourceImage = imagecreatefrompng($sourcePath);
                    break;
                case IMAGETYPE_GIF:
                    $sourceImage = imagecreatefromgif($sourcePath);
                    break;
                default:
                    return false;
            }

            if (!$sourceImage) {
                return false;
            }

            // Calculate dimensions
            if ($crop) {
                // Crop to exact dimensions
                $ratio = max($width / $sourceWidth, $height / $sourceHeight);
                $newWidth = (int)($sourceWidth * $ratio);
                $newHeight = (int)($sourceHeight * $ratio);
                $cropX = (int)(($newWidth - $width) / 2);
                $cropY = (int)(($newHeight - $height) / 2);
            } else {
                // Resize maintaining aspect ratio
                $ratio = min($width / $sourceWidth, $height / $sourceHeight);
                $newWidth = (int)($sourceWidth * $ratio);
                $newHeight = (int)($sourceHeight * $ratio);
                $cropX = 0;
                $cropY = 0;
            }

            // Create target image
            $targetImage = imagecreatetruecolor($width, $height);
            
            // Preserve transparency for PNG
            if ($imageType === IMAGETYPE_PNG) {
                imagealphablending($targetImage, false);
                imagesavealpha($targetImage, true);
                $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
                imagefill($targetImage, 0, 0, $transparent);
            }

            // Resize/crop
            if ($crop) {
                $tempImage = imagecreatetruecolor($newWidth, $newHeight);
                imagecopyresampled($tempImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $sourceWidth, $sourceHeight);
                imagecopy($targetImage, $tempImage, 0, 0, $cropX, $cropY, $width, $height);
                imagedestroy($tempImage);
            } else {
                imagecopyresampled($targetImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $sourceWidth, $sourceHeight);
            }

            // Save image
            $result = match ($imageType) {
                IMAGETYPE_JPEG => imagejpeg($targetImage, $targetPath, 90),
                IMAGETYPE_PNG => imagepng($targetImage, $targetPath, 6),
                IMAGETYPE_GIF => imagegif($targetImage, $targetPath),
                default => false
            };

            // Cleanup
            imagedestroy($sourceImage);
            imagedestroy($targetImage);

            return $result;

        } catch (\Exception $e) {
            self::getLogger()->error('GD resize failed', [
                'error' => $e->getMessage(),
                'source' => $sourcePath,
                'target' => $targetPath
            ]);
            return false;
        }
    }

    /**
     * Optimize PNG image
     *
     * @param string $imagePath
     * @return bool
     */
    public static function optimizePng(string $imagePath): bool
    {
        try {
            // Try with optipng if available
            if (self::isCommandAvailable('optipng')) {
                exec('optipng -quiet -o2 ' . escapeshellarg($imagePath), $output, $returnCode);
                return $returnCode === 0;
            }

            // Fallback to ImageMagick optimization
            if (extension_loaded('imagick')) {
                $imagick = new \Imagick($imagePath);
                $imagick->setImageCompressionQuality(90);
                $imagick->stripImage();
                $imagick->writeImage($imagePath);
                $imagick->clear();
                return true;
            }

            return false;

        } catch (\Exception $e) {
            self::getLogger()->error('PNG optimization failed', [
                'error' => $e->getMessage(),
                'path' => $imagePath
            ]);
            return false;
        }
    }

    /**
     * Generate image from PDF page using ImageMagick
     *
     * @param string $pdfPath
     * @param int $pageNumber
     * @param string $outputPath
     * @param int $resolution
     * @return bool
     */
    public static function pdfPageToImage(string $pdfPath, int $pageNumber, string $outputPath, int $resolution = 150): bool
    {
        try {
            if (!extension_loaded('imagick')) {
                return false;
            }

            $imagick = new \Imagick();
            $imagick->setResolution($resolution, $resolution);
            $imagick->readImage($pdfPath . '[' . ($pageNumber - 1) . ']'); // Page numbers are 0-indexed
            
            $imagick->setImageFormat('png');
            $imagick->setImageCompressionQuality(90);
            $imagick->writeImage($outputPath);
            $imagick->clear();
            
            return true;

        } catch (\Exception $e) {
            self::getLogger()->error('PDF to image conversion failed', [
                'error' => $e->getMessage(),
                'pdf' => $pdfPath,
                'page' => $pageNumber,
                'output' => $outputPath
            ]);
            return false;
        }
    }

    /**
     * Get image dimensions
     *
     * @param string $imagePath
     * @return array|null [width, height] or null on failure
     */
    public static function getImageDimensions(string $imagePath): ?array
    {
        try {
            $imageInfo = getimagesize($imagePath);
            if ($imageInfo) {
                return [$imageInfo[0], $imageInfo[1]];
            }
            return null;

        } catch (\Exception $e) {
            self::getLogger()->error('Failed to get image dimensions', [
                'error' => $e->getMessage(),
                'path' => $imagePath
            ]);
            return null;
        }
    }

    /**
     * Create image thumbnail
     *
     * @param string $sourcePath
     * @param string $targetPath
     * @param int $maxWidth
     * @param int $maxHeight
     * @return bool
     */
    public static function createThumbnail(string $sourcePath, string $targetPath, int $maxWidth = 200, int $maxHeight = 200): bool
    {
        return self::resizeImage($sourcePath, $targetPath, $maxWidth, $maxHeight, false);
    }

    /**
     * Check if external command is available
     *
     * @param string $command
     * @return bool
     */
    protected static function isCommandAvailable(string $command): bool
    {
        $output = [];
        $returnCode = 0;
        
        // Windows compatibility
        $cmd = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'where' : 'which';
        exec($cmd . ' ' . escapeshellarg($command), $output, $returnCode);
        
        return $returnCode === 0;
    }

    /**
     * Convert image format
     *
     * @param string $sourcePath
     * @param string $targetPath
     * @param string $targetFormat (jpeg, png, gif)
     * @param int $quality
     * @return bool
     */
    public static function convertImageFormat(string $sourcePath, string $targetPath, string $targetFormat, int $quality = 90): bool
    {
        try {
            if (extension_loaded('imagick')) {
                $imagick = new \Imagick($sourcePath);
                $imagick->setImageFormat($targetFormat);
                
                if ($targetFormat === 'jpeg') {
                    $imagick->setImageCompressionQuality($quality);
                }
                
                $imagick->writeImage($targetPath);
                $imagick->clear();
                return true;
            }
            
            return false;

        } catch (\Exception $e) {
            self::getLogger()->error('Image format conversion failed', [
                'error' => $e->getMessage(),
                'source' => $sourcePath,
                'target' => $targetPath,
                'format' => $targetFormat
            ]);
            return false;
        }
    }

    /**
     * Add watermark to image
     *
     * @param string $imagePath
     * @param string $watermarkPath
     * @param string $position (top-left, top-right, bottom-left, bottom-right, center)
     * @param int $opacity (0-100)
     * @return bool
     */
    public static function addWatermark(string $imagePath, string $watermarkPath, string $position = 'bottom-right', int $opacity = 50): bool
    {
        try {
            if (!extension_loaded('imagick')) {
                return false;
            }

            $image = new \Imagick($imagePath);
            $watermark = new \Imagick($watermarkPath);
            
            // Set opacity
            $watermark->evaluateImage(\Imagick::EVALUATE_MULTIPLY, $opacity / 100, \Imagick::CHANNEL_ALPHA);
            
            // Calculate position
            $imageWidth = $image->getImageWidth();
            $imageHeight = $image->getImageHeight();
            $watermarkWidth = $watermark->getImageWidth();
            $watermarkHeight = $watermark->getImageHeight();
            
            [$x, $y] = match ($position) {
                'top-left' => [10, 10],
                'top-right' => [$imageWidth - $watermarkWidth - 10, 10],
                'bottom-left' => [10, $imageHeight - $watermarkHeight - 10],
                'bottom-right' => [$imageWidth - $watermarkWidth - 10, $imageHeight - $watermarkHeight - 10],
                'center' => [($imageWidth - $watermarkWidth) / 2, ($imageHeight - $watermarkHeight) / 2],
                default => [$imageWidth - $watermarkWidth - 10, $imageHeight - $watermarkHeight - 10]
            };
            
            // Composite watermark onto image
            $image->compositeImage($watermark, \Imagick::COMPOSITE_OVER, (int)$x, (int)$y);
            
            // Save
            $image->writeImage($imagePath);
            
            // Cleanup
            $image->clear();
            $watermark->clear();
            
            return true;

        } catch (\Exception $e) {
            self::getLogger()->error('Watermark addition failed', [
                'error' => $e->getMessage(),
                'image' => $imagePath,
                'watermark' => $watermarkPath
            ]);
            return false;
        }
    }
}