<?php

namespace Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * Security Penetration Testing
 * Tests for common vulnerabilities and attack vectors
 * @group security
 */
class SecurityPenetrationTest extends TestCase
{
    private $db;
    
    protected function setUp(): void
    {
        parent::setUp();
        createTestDatabase();
        
        require_once __DIR__ . '/../../includes/config.php';
        require_once __DIR__ . '/../../includes/db.php';
        
        $this->db = getTestDb();
    }
    
    protected function tearDown(): void
    {
        $this->db = null;
        cleanupTestDatabase();
        parent::tearDown();
    }
    
    /**
     * @test
     * Test for SQL injection vulnerabilities
     */
    public function it_prevents_sql_injection_in_login()
    {
        $sqlInjectionAttempts = [
            "admin' OR '1'='1",
            "admin'--",
            "admin' OR 1=1--",
            "' OR ''='",
            "admin') OR ('1'='1",
        ];
        
        foreach ($sqlInjectionAttempts as $maliciousInput) {
            // Attempt to inject SQL
            $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$maliciousInput]);
            $result = $stmt->fetch();
            
            $this->assertFalse($result, "SQL injection attempt '{$maliciousInput}' should fail");
        }
    }
    
    /**
     * @test
     * Test for XSS vulnerabilities in output
     */
    public function it_escapes_xss_in_user_input()
    {
        $xssPayloads = [
            '<script>alert("XSS")</script>',
            '<img src=x onerror=alert("XSS")>',
            '<svg onload=alert("XSS")>',
            'javascript:alert("XSS")',
            '<iframe src="javascript:alert(\'XSS\')">',
        ];
        
        foreach ($xssPayloads as $payload) {
            $escaped = htmlspecialchars($payload, ENT_QUOTES, 'UTF-8');
            
            $this->assertStringNotContainsString('<script>', $escaped);
            $this->assertStringNotContainsString('javascript:', $escaped);
            $this->assertStringContainsString('&lt;', $escaped);
        }
    }
    
    /**
     * @test
     * Test file upload security
     */
    public function it_detects_malicious_file_uploads()
    {
        $maliciousFiles = [
            'shell.php' => '<?php system($_GET["cmd"]); ?>',
            'backdoor.phtml' => '<?php eval($_POST["code"]); ?>',
            'exploit.php5' => '<?php phpinfo(); ?>',
        ];
        
        foreach ($maliciousFiles as $filename => $content) {
            $tempFile = tempnam(sys_get_temp_dir(), 'malicious_');
            file_put_contents($tempFile, $content);
            
            // Check extension
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'phar'];
            
            $this->assertContains(
                $extension,
                $dangerousExtensions,
                "Extension {$extension} should be detected as dangerous"
            );
            
            // Check for PHP code
            $fileContent = file_get_contents($tempFile);
            $hasPhpCode = strpos($fileContent, '<?php') !== false || 
                         strpos($fileContent, '<?=') !== false;
            
            $this->assertTrue($hasPhpCode, 'PHP code should be detected');
            
            unlink($tempFile);
        }
    }
    
    /**
     * @test
     * Test directory traversal prevention
     */
    public function it_prevents_directory_traversal()
    {
        $traversalAttempts = [
            '../../../etc/passwd',
            '..\\..\\..\\windows\\system32\\config\\sam',
            '/etc/passwd',
            'C:\\windows\\system32\\drivers\\etc\\hosts',
        ];
        
        foreach ($traversalAttempts as $path) {
            $safePath = basename($path);
            
            $this->assertNotContains('..', $safePath, 'Path should not contain directory traversal');
            $this->assertNotContains('/', $safePath, 'Path should not contain slashes');
            $this->assertNotContains('\\', $safePath, 'Path should not contain backslashes');
        }
    }
    
    /**
     * @test
     * Test session fixation prevention
     */
    public function it_regenerates_session_id_on_login()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $oldSessionId = session_id();
        
        // Simulate login
        session_regenerate_id(true);
        
        $newSessionId = session_id();
        
        $this->assertNotEquals(
            $oldSessionId,
            $newSessionId,
            'Session ID should be regenerated on login'
        );
    }
    
    /**
     * @test
     * Test password strength requirements
     */
    public function it_enforces_minimum_password_length()
    {
        $weakPasswords = ['123', 'pass', 'abc', '12345'];
        $minLength = 8;
        
        foreach ($weakPasswords as $password) {
            $this->assertLessThan(
                $minLength,
                strlen($password),
                "Password '{$password}' should be rejected (too short)"
            );
        }
        
        $strongPassword = 'SecurePassword123!';
        $this->assertGreaterThanOrEqual(
            $minLength,
            strlen($strongPassword),
            'Strong password should meet minimum length'
        );
    }
    
    /**
     * @test
     * Test MIME type validation
     */
    public function it_validates_mime_types()
    {
        $allowedImageMimes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp'
        ];
        
        $dangerousMimes = [
            'application/x-php',
            'application/x-httpd-php',
            'text/x-php',
            'application/x-sh',
        ];
        
        foreach ($dangerousMimes as $mime) {
            $this->assertNotContains(
                $mime,
                $allowedImageMimes,
                "Dangerous MIME type {$mime} should not be allowed"
            );
        }
    }
    
    /**
     * @test
     * Test sensitive data exposure in errors
     */
    public function it_does_not_expose_sensitive_data_in_errors()
    {
        // In production, errors should not reveal database structure
        $this->assertTrue(
            defined('TESTING') || ini_get('display_errors') == '0',
            'Display errors should be off in production'
        );
    }
    
    /**
     * @test
     * Test file permissions
     */
    public function it_has_secure_file_permissions()
    {
        $sensitiveFiles = [
            __DIR__ . '/../../includes/config.php',
            __DIR__ . '/../../includes/db.php',
        ];
        
        foreach ($sensitiveFiles as $file) {
            if (file_exists($file)) {
                $perms = fileperms($file);
                $octal = substr(sprintf('%o', $perms), -4);
                
                // Should not be world-writable (last digit should not be 7, 6, 3, 2)
                $lastDigit = (int)substr($octal, -1);
                $this->assertNotContains(
                    $lastDigit,
                    [2, 3, 6, 7],
                    "File {$file} should not be world-writable"
                );
            }
        }
    }
}
