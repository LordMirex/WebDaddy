<?php
/**
 * Video Processing System - Phase 7
 * 
 * Handles video optimization, compression, thumbnail generation,
 * and multiple quality versions for optimal playback
 * 
 * Requirements: FFmpeg must be installed
 */

require_once __DIR__ . '/config.php';

class VideoProcessor {
    
    /**
     * Process uploaded video: generate thumbnails and optimized versions
     * 
     * @param string $videoPath Full path to uploaded video file
     * @param string $category Category: 'templates' or 'tools'
     * @return array Result with thumbnail URLs and video versions
     */
    public static function processVideo($videoPath, $category = 'templates') {
        $result = [
            'success' => false,
            'thumbnail_url' => '',
            'video_versions' => [],
            'metadata' => [],
            'error' => ''
        ];
        
        if (!file_exists($videoPath)) {
            $result['error'] = 'Video file not found';
            return $result;
        }
        
        $videoInfo = pathinfo($videoPath);
        $baseDir = $videoInfo['dirname'];
        $baseName = $videoInfo['filename'];
        
        $metadata = self::extractVideoMetadata($videoPath);
        if (!$metadata['success']) {
            $result['error'] = 'Failed to read video metadata: ' . ($metadata['error'] ?? 'unknown error');
            error_log('VideoProcessor: Metadata extraction failed - ' . $result['error']);
            return $result;
        }
        
        $result['metadata'] = $metadata['data'];
        
        $thumbnailResult = self::generateThumbnail($videoPath, $baseDir, $baseName, $category);
        if (!$thumbnailResult['success']) {
            $result['error'] = 'Failed to generate video thumbnail: ' . ($thumbnailResult['error'] ?? 'unknown error');
            error_log('VideoProcessor: Thumbnail generation failed - ' . $result['error']);
            return $result;
        }
        $result['thumbnail_url'] = $thumbnailResult['url'];
        
        $versionsResult = self::generateVideoVersions($videoPath, $baseDir, $baseName, $category, $metadata['data']);
        if (!$versionsResult['success']) {
            $result['error'] = 'Failed to generate video quality versions: ' . ($versionsResult['error'] ?? 'unknown error');
            error_log('VideoProcessor: Video versions generation failed - ' . $result['error']);
            return $result;
        }
        
        if (empty($versionsResult['versions'])) {
            $result['error'] = 'No video quality versions were generated';
            error_log('VideoProcessor: No video versions created');
            return $result;
        }
        
        $result['video_versions'] = $versionsResult['versions'];
        
        $result['success'] = true;
        error_log('VideoProcessor: Successfully processed video - generated ' . count($versionsResult['versions']) . ' quality versions');
        return $result;
    }
    
    /**
     * Extract video metadata using FFprobe
     * 
     * @param string $videoPath Path to video file
     * @return array Metadata including duration, resolution, bitrate, codec
     */
    public static function extractVideoMetadata($videoPath) {
        $result = [
            'success' => false,
            'data' => [],
            'error' => ''
        ];
        
        $ffprobePath = self::getFfprobePath();
        if (!$ffprobePath) {
            $result['error'] = 'FFprobe not found';
            return $result;
        }
        
        $cmd = escapeshellcmd($ffprobePath) . ' -v quiet -print_format json -show_format -show_streams ' . escapeshellarg($videoPath) . ' 2>&1';
        $output = shell_exec($cmd);
        
        if (!$output) {
            $result['error'] = 'Failed to execute ffprobe';
            return $result;
        }
        
        $data = json_decode($output, true);
        if (!$data) {
            $result['error'] = 'Failed to parse video metadata';
            return $result;
        }
        
        $videoStream = null;
        if (isset($data['streams'])) {
            foreach ($data['streams'] as $stream) {
                if (isset($stream['codec_type']) && $stream['codec_type'] === 'video') {
                    $videoStream = $stream;
                    break;
                }
            }
        }
        
        $result['success'] = true;
        $result['data'] = [
            'duration' => isset($data['format']['duration']) ? (float)$data['format']['duration'] : 0,
            'size' => isset($data['format']['size']) ? (int)$data['format']['size'] : 0,
            'bitrate' => isset($data['format']['bit_rate']) ? (int)$data['format']['bit_rate'] : 0,
            'width' => $videoStream['width'] ?? 0,
            'height' => $videoStream['height'] ?? 0,
            'codec' => $videoStream['codec_name'] ?? 'unknown',
            'fps' => isset($videoStream['r_frame_rate']) ? self::parseFps($videoStream['r_frame_rate']) : 0
        ];
        
        return $result;
    }
    
    /**
     * Generate video thumbnail from middle of video
     * 
     * @param string $videoPath Path to video file
     * @param string $outputDir Output directory
     * @param string $baseName Base filename
     * @param string $category Category
     * @return array Result with thumbnail URL
     */
    public static function generateThumbnail($videoPath, $outputDir, $baseName, $category) {
        $result = [
            'success' => false,
            'url' => '',
            'path' => '',
            'error' => ''
        ];
        
        $ffmpegPath = self::getFfmpegPath();
        if (!$ffmpegPath) {
            $result['error'] = 'FFmpeg not found';
            return $result;
        }
        
        $thumbnailName = $baseName . '_thumb.jpg';
        $thumbnailPath = $outputDir . '/' . $thumbnailName;
        
        $cmd = escapeshellcmd($ffmpegPath) . ' -i ' . escapeshellarg($videoPath) . 
               ' -ss 00:00:01 -vframes 1 -vf scale=1280:720:force_original_aspect_ratio=decrease -q:v 2 ' . 
               escapeshellarg($thumbnailPath) . ' -y 2>&1';
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0 || !file_exists($thumbnailPath)) {
            $result['error'] = 'Failed to generate thumbnail';
            return $result;
        }
        
        $subcategoryPath = str_replace(UPLOAD_DIR, '', $outputDir);
        $thumbnailUrl = UPLOAD_URL . $subcategoryPath . '/' . $thumbnailName;
        
        $result['success'] = true;
        $result['url'] = $thumbnailUrl;
        $result['path'] = $thumbnailPath;
        
        return $result;
    }
    
    /**
     * Generate multiple quality versions of video
     * 
     * @param string $videoPath Original video path
     * @param string $outputDir Output directory
     * @param string $baseName Base filename
     * @param string $category Category
     * @param array $metadata Video metadata
     * @return array Result with video versions
     */
    public static function generateVideoVersions($videoPath, $outputDir, $baseName, $category, $metadata) {
        $result = [
            'success' => false,
            'versions' => [],
            'error' => ''
        ];
        
        $ffmpegPath = self::getFfmpegPath();
        if (!$ffmpegPath) {
            $result['error'] = 'FFmpeg not found';
            return $result;
        }
        
        $originalWidth = $metadata['width'] ?? 1920;
        $originalHeight = $metadata['height'] ?? 1080;
        
        $subcategoryPath = str_replace(UPLOAD_DIR, '', $outputDir);
        $versions = [];
        
        if ($originalWidth >= 1920) {
            $qualities = ['1080p' => ['width' => 1920, 'height' => 1080, 'bitrate' => '5000k'],
                          '720p' => ['width' => 1280, 'height' => 720, 'bitrate' => '2500k'],
                          '480p' => ['width' => 854, 'height' => 480, 'bitrate' => '1000k']];
        } elseif ($originalWidth >= 1280) {
            $qualities = ['720p' => ['width' => 1280, 'height' => 720, 'bitrate' => '2500k'],
                          '480p' => ['width' => 854, 'height' => 480, 'bitrate' => '1000k']];
        } elseif ($originalWidth >= 854) {
            $qualities = ['480p' => ['width' => 854, 'height' => 480, 'bitrate' => '1000k']];
        } else {
            $qualities = [];
        }
        
        foreach ($qualities as $qualityName => $settings) {
            $outputName = $baseName . '_' . $qualityName . '.mp4';
            $outputPath = $outputDir . '/' . $outputName;
            
            $cmd = escapeshellcmd($ffmpegPath) . ' -i ' . escapeshellarg($videoPath) . 
                   ' -vf scale=' . $settings['width'] . ':' . $settings['height'] . ':force_original_aspect_ratio=decrease' .
                   ' -c:v libx264 -preset fast -b:v ' . $settings['bitrate'] . 
                   ' -c:a aac -b:a 128k -movflags +faststart ' . 
                   escapeshellarg($outputPath) . ' -y 2>&1';
            
            exec($cmd, $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($outputPath)) {
                $versions[$qualityName] = [
                    'url' => UPLOAD_URL . $subcategoryPath . '/' . $outputName,
                    'path' => $outputPath,
                    'width' => $settings['width'],
                    'height' => $settings['height'],
                    'size' => filesize($outputPath)
                ];
            }
        }
        
        if (empty($versions)) {
            $outputName = $baseName . '_optimized.mp4';
            $outputPath = $outputDir . '/' . $outputName;
            
            $cmd = escapeshellcmd($ffmpegPath) . ' -i ' . escapeshellarg($videoPath) . 
                   ' -c:v libx264 -preset fast -crf 23 -c:a aac -b:a 128k -movflags +faststart ' . 
                   escapeshellarg($outputPath) . ' -y 2>&1';
            
            exec($cmd, $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($outputPath)) {
                $versions['optimized'] = [
                    'url' => UPLOAD_URL . $subcategoryPath . '/' . $outputName,
                    'path' => $outputPath,
                    'width' => $originalWidth,
                    'height' => $originalHeight,
                    'size' => filesize($outputPath)
                ];
            }
        }
        
        $result['success'] = count($versions) > 0;
        $result['versions'] = $versions;
        
        return $result;
    }
    
    /**
     * Parse FPS from FFprobe fraction format (e.g., "30000/1001")
     */
    private static function parseFps($fpsString) {
        if (strpos($fpsString, '/') !== false) {
            list($num, $den) = explode('/', $fpsString);
            return $den > 0 ? round($num / $den, 2) : 0;
        }
        return (float)$fpsString;
    }
    
    /**
     * Get FFmpeg binary path
     */
    private static function getFfmpegPath() {
        $paths = [
            '/usr/bin/ffmpeg',
            '/usr/local/bin/ffmpeg',
            '/nix/store/*/bin/ffmpeg',
            'ffmpeg'
        ];
        
        foreach ($paths as $path) {
            if (strpos($path, '*') !== false) {
                $matches = glob($path);
                if (!empty($matches) && is_executable($matches[0])) {
                    return $matches[0];
                }
            } elseif (is_executable($path)) {
                return $path;
            }
        }
        
        exec('which ffmpeg 2>/dev/null', $output, $returnCode);
        if ($returnCode === 0 && !empty($output[0])) {
            return trim($output[0]);
        }
        
        return null;
    }
    
    /**
     * Get FFprobe binary path
     */
    private static function getFfprobePath() {
        $paths = [
            '/usr/bin/ffprobe',
            '/usr/local/bin/ffprobe',
            '/nix/store/*/bin/ffprobe',
            'ffprobe'
        ];
        
        foreach ($paths as $path) {
            if (strpos($path, '*') !== false) {
                $matches = glob($path);
                if (!empty($matches) && is_executable($matches[0])) {
                    return $matches[0];
                }
            } elseif (is_executable($path)) {
                return $path;
            }
        }
        
        exec('which ffprobe 2>/dev/null', $output, $returnCode);
        if ($returnCode === 0 && !empty($output[0])) {
            return trim($output[0]);
        }
        
        return null;
    }
    
    /**
     * Optimize existing video file in-place
     * 
     * @param string $videoPath Path to video file
     * @return array Result
     */
    public static function optimizeVideo($videoPath) {
        $result = [
            'success' => false,
            'error' => ''
        ];
        
        $ffmpegPath = self::getFfmpegPath();
        if (!$ffmpegPath) {
            $result['error'] = 'FFmpeg not found';
            return $result;
        }
        
        $tempPath = $videoPath . '.tmp.mp4';
        
        $cmd = escapeshellcmd($ffmpegPath) . ' -i ' . escapeshellarg($videoPath) . 
               ' -c:v libx264 -preset fast -crf 23 -c:a aac -b:a 128k -movflags +faststart ' . 
               escapeshellarg($tempPath) . ' -y 2>&1';
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0 || !file_exists($tempPath)) {
            $result['error'] = 'Video optimization failed';
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
            return $result;
        }
        
        unlink($videoPath);
        rename($tempPath, $videoPath);
        
        $result['success'] = true;
        return $result;
    }
}
