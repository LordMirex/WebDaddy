<?php

class ThumbnailGenerator {
    
    private static $sizes = [
        'large' => ['width' => 1280, 'height' => 720, 'suffix' => '-large'],
        'medium' => ['width' => 800, 'height' => 450, 'suffix' => '-medium'],
        'small' => ['width' => 400, 'height' => 225, 'suffix' => '-small'],
        'thumb' => ['width' => 200, 'height' => 112, 'suffix' => '-thumb']
    ];
    
    public static function generateThumbnails($sourcePath, $outputDir, $baseFilename) {
        if (!file_exists($sourcePath) || !is_readable($sourcePath)) {
            return [
                'success' => false,
                'error' => 'Source file not found or not readable'
            ];
        }
        
        $imageInfo = @getimagesize($sourcePath);
        if ($imageInfo === false) {
            return [
                'success' => false,
                'error' => 'Invalid image file'
            ];
        }
        
        $sourceWidth = $imageInfo[0];
        $sourceHeight = $imageInfo[1];
        $mimeType = $imageInfo['mime'];
        
        $sourceImage = self::loadImage($sourcePath, $mimeType);
        if ($sourceImage === false) {
            return [
                'success' => false,
                'error' => 'Failed to load image'
            ];
        }
        
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        $pathInfo = pathinfo($baseFilename);
        $filenameWithoutExt = $pathInfo['filename'];
        
        $thumbnails = [];
        
        foreach (self::$sizes as $sizeName => $dimensions) {
            $targetWidth = $dimensions['width'];
            $targetHeight = $dimensions['height'];
            
            if ($sourceWidth <= $targetWidth && $sourceHeight <= $targetHeight) {
                continue;
            }
            
            $thumbnail = imagecreatetruecolor($targetWidth, $targetHeight);
            
            if (in_array($mimeType, ['image/png', 'image/webp'])) {
                imagealphablending($thumbnail, false);
                imagesavealpha($thumbnail, true);
                $transparent = imagecolorallocatealpha($thumbnail, 0, 0, 0, 127);
                imagefill($thumbnail, 0, 0, $transparent);
            }
            
            imagecopyresampled(
                $thumbnail,
                $sourceImage,
                0, 0,
                0, 0,
                $targetWidth,
                $targetHeight,
                $sourceWidth,
                $sourceHeight
            );
            
            $outputFilename = $filenameWithoutExt . $dimensions['suffix'] . '.jpg';
            $outputPath = $outputDir . '/' . $outputFilename;
            
            $saved = imagejpeg($thumbnail, $outputPath, 90);
            imagedestroy($thumbnail);
            
            if ($saved) {
                chmod($outputPath, 0644);
                $thumbnails[$sizeName] = [
                    'path' => $outputPath,
                    'filename' => $outputFilename,
                    'width' => $targetWidth,
                    'height' => $targetHeight
                ];
            }
        }
        
        imagedestroy($sourceImage);
        
        return [
            'success' => true,
            'thumbnails' => $thumbnails
        ];
    }
    
    private static function loadImage($path, $mimeType) {
        switch ($mimeType) {
            case 'image/jpeg':
            case 'image/jpg':
                return @imagecreatefromjpeg($path);
            case 'image/png':
                return @imagecreatefrompng($path);
            case 'image/gif':
                return @imagecreatefromgif($path);
            case 'image/webp':
                return @imagecreatefromwebp($path);
            default:
                return false;
        }
    }
    
    public static function optimizeImage($sourcePath, $quality = 85) {
        if (!file_exists($sourcePath) || !is_readable($sourcePath)) {
            return false;
        }
        
        $imageInfo = @getimagesize($sourcePath);
        if ($imageInfo === false) {
            return false;
        }
        
        $mimeType = $imageInfo['mime'];
        $sourceImage = self::loadImage($sourcePath, $mimeType);
        
        if ($sourceImage === false) {
            return false;
        }
        
        $tempPath = $sourcePath . '.tmp';
        $result = imagejpeg($sourceImage, $tempPath, $quality);
        imagedestroy($sourceImage);
        
        if ($result) {
            rename($tempPath, $sourcePath);
            chmod($sourcePath, 0644);
            return true;
        }
        
        if (file_exists($tempPath)) {
            unlink($tempPath);
        }
        
        return false;
    }
    
    public static function deleteThumbnails($sourcePath) {
        if (!file_exists($sourcePath)) {
            return false;
        }
        
        $dir = dirname($sourcePath);
        $pathInfo = pathinfo($sourcePath);
        $filenameWithoutExt = $pathInfo['filename'];
        
        $deleted = 0;
        foreach (self::$sizes as $dimensions) {
            $thumbnailPath = $dir . '/' . $filenameWithoutExt . $dimensions['suffix'] . '.jpg';
            if (file_exists($thumbnailPath)) {
                if (unlink($thumbnailPath)) {
                    $deleted++;
                }
            }
        }
        
        return $deleted > 0;
    }
}
