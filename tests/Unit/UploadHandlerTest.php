<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Test suite for Upload Handler functionality
 * Tests file validation, upload processing, and security features
 */
class UploadHandlerTest extends TestCase
{
    private $testImagePath;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test database
        createTestDatabase();
        
        // Load required files
        require_once __DIR__ . '/../../includes/config.php';
        require_once __DIR__ . '/../../includes/db.php';
        require_once __DIR__ . '/../../includes/upload_handler.php';
        
        // Create a test image
        $this->testImagePath = createTestImage(1920, 1080);
    }
    
    protected function tearDown(): void
    {
        // Clean up test image
        if (file_exists($this->testImagePath)) {
            unlink($this->testImagePath);
        }
        
        // Clean up test files
        cleanupTestFiles();
        cleanupTestDatabase();
        
        parent::tearDown();
    }
    
    /**
     * @test
     * @group upload
     */
    public function it_validates_image_file_size()
    {
        // Create a fake large file info
        $largeFile = [
            'name' => 'large_image.jpg',
            'type' => 'image/jpeg',
            'size' => 25 * 1024 * 1024, // 25MB (exceeds 20MB limit)
            'tmp_name' => $this->testImagePath,
            'error' => UPLOAD_ERR_OK
        ];
        
        // Use reflection to test private method
        $uploadHandler = new \ReflectionClass('UploadHandler');
        $method = $uploadHandler->getMethod('validateFile');
        $method->setAccessible(true);
        
        $result = $method->invokeArgs(null, [$largeFile, 'image']);
        
        $this->assertFalse($result['valid'], 'Large image should be rejected');
        $this->assertStringContainsString('exceeds maximum', $result['error']);
    }
    
    /**
     * @test
     * @group upload
     */
    public function it_validates_image_file_type()
    {
        // Create a test PHP file (should be rejected)
        $phpFile = tempnam(sys_get_temp_dir(), 'test_php_');
        file_put_contents($phpFile, '<?php echo "malicious"; ?>');
        
        $invalidFile = [
            'name' => 'malicious.php.jpg', // Double extension attack
            'type' => 'application/x-php',
            'size' => 1024,
            'tmp_name' => $phpFile,
            'error' => UPLOAD_ERR_OK
        ];
        
        // Use reflection to test private method
        $uploadHandler = new \ReflectionClass('UploadHandler');
        $method = $uploadHandler->getMethod('validateFile');
        $method->setAccessible(true);
        
        $result = $method->invokeArgs(null, [$invalidFile, 'image']);
        
        $this->assertFalse($result['valid'], 'PHP file should be rejected');
        
        unlink($phpFile);
    }
    
    /**
     * @test
     * @group upload
     */
    public function it_accepts_valid_image_formats()
    {
        $validExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        
        foreach ($validExtensions as $ext) {
            $this->assertTrue(
                in_array($ext, ALLOWED_IMAGE_EXTENSIONS),
                "Extension {$ext} should be allowed"
            );
        }
    }
    
    /**
     * @test
     * @group upload
     */
    public function it_rejects_svg_files_for_security()
    {
        // SVG files can contain XSS vulnerabilities
        $svgFile = tempnam(sys_get_temp_dir(), 'test_svg_');
        file_put_contents($svgFile, '<svg><script>alert("XSS")</script></svg>');
        
        $svgUpload = [
            'name' => 'image.svg',
            'type' => 'image/svg+xml',
            'size' => 1024,
            'tmp_name' => $svgFile,
            'error' => UPLOAD_ERR_OK
        ];
        
        // Use reflection to test private method
        $uploadHandler = new \ReflectionClass('UploadHandler');
        $method = $uploadHandler->getMethod('validateFile');
        $method->setAccessible(true);
        
        $result = $method->invokeArgs(null, [$svgUpload, 'image']);
        
        $this->assertFalse($result['valid'], 'SVG files should be rejected for security');
        $this->assertStringContainsString('SVG files are not allowed', $result['error']);
        
        unlink($svgFile);
    }
    
    /**
     * @test
     * @group upload
     */
    public function it_generates_unique_filenames()
    {
        $filename1 = \UploadHandler::generateUniqueFilename('test.jpg');
        $filename2 = \UploadHandler::generateUniqueFilename('test.jpg');
        
        $this->assertNotEquals($filename1, $filename2, 'Filenames should be unique');
        $this->assertStringEndsWith('.jpg', $filename1, 'Extension should be preserved');
        $this->assertStringEndsWith('.jpg', $filename2, 'Extension should be preserved');
    }
    
    /**
     * @test
     * @group security
     */
    public function it_sanitizes_filenames()
    {
        // Test filename sanitization
        $dangerousFilename = '../../../etc/passwd.jpg';
        $safeFilename = basename($dangerousFilename);
        
        $this->assertEquals('passwd.jpg', $safeFilename, 'Directory traversal should be prevented');
        
        // Test special characters
        $weirdFilename = 'test@#$%^&*().jpg';
        $sanitized = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($weirdFilename));
        
        $this->assertStringNotContainsString('@', $sanitized);
        $this->assertStringNotContainsString('$', $sanitized);
    }
}
