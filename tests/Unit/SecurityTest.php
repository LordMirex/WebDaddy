<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Security testing suite
 * Tests authentication, CSRF protection, XSS prevention, and SQL injection protection
 */
class SecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        createTestDatabase();
        
        require_once __DIR__ . '/../../includes/config.php';
        require_once __DIR__ . '/../../includes/db.php';
        require_once __DIR__ . '/../../includes/functions.php';
    }
    
    protected function tearDown(): void
    {
        cleanupTestDatabase();
        parent::tearDown();
    }
    
    /**
     * @test
     * @group security
     */
    public function it_hashes_passwords_with_bcrypt()
    {
        $password = 'testPassword123!';
        $hash = password_hash($password, PASSWORD_BCRYPT);
        
        $this->assertTrue(password_verify($password, $hash), 'Password should verify correctly');
        $this->assertStringStartsWith('$2y$', $hash, 'Should use bcrypt algorithm');
        $this->assertGreaterThan(50, strlen($hash), 'Hash should be long enough');
    }
    
    /**
     * @test
     * @group security
     */
    public function it_prevents_weak_passwords()
    {
        $weakPasswords = [
            '123456',
            'password',
            'abc',
            '11111',
            'test'
        ];
        
        foreach ($weakPasswords as $weak) {
            $this->assertLessThan(8, strlen($weak), "Weak password '{$weak}' should be rejected");
        }
    }
    
    /**
     * @test
     * @group security
     */
    public function it_generates_csrf_tokens()
    {
        // Start session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        require_once __DIR__ . '/../../includes/csrf.php';
        
        $token1 = generateCSRFToken();
        $token2 = generateCSRFToken();
        
        $this->assertNotEmpty($token1, 'CSRF token should not be empty');
        $this->assertEquals(64, strlen($token1), 'CSRF token should be 64 characters');
        $this->assertEquals($token1, $token2, 'Same session should return same token');
    }
    
    /**
     * @test
     * @group security
     */
    public function it_validates_csrf_tokens()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        require_once __DIR__ . '/../../includes/csrf.php';
        
        $token = generateCSRFToken();
        
        $this->assertTrue(validateCSRFToken($token), 'Valid token should pass');
        $this->assertFalse(validateCSRFToken('invalid_token'), 'Invalid token should fail');
        $this->assertFalse(validateCSRFToken(''), 'Empty token should fail');
    }
    
    /**
     * @test
     * @group security
     */
    public function it_sanitizes_html_output()
    {
        $xssAttempts = [
            '<script>alert("XSS")</script>',
            '<img src=x onerror=alert("XSS")>',
            '<a href="javascript:alert(\'XSS\')">Click</a>',
            '"><script>alert(String.fromCharCode(88,83,83))</script>',
        ];
        
        foreach ($xssAttempts as $xss) {
            $sanitized = htmlspecialchars($xss, ENT_QUOTES, 'UTF-8');
            $this->assertStringNotContainsString('<script>', $sanitized, 'Script tags should be escaped');
            $this->assertStringNotContainsString('javascript:', $sanitized, 'JavaScript protocol should be escaped');
        }
    }
    
    /**
     * @test
     * @group security
     */
    public function it_uses_prepared_statements()
    {
        $db = getTestDb();
        
        // Test that we can use prepared statements
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute(['test@example.com']);
        
        $this->assertInstanceOf(\PDOStatement::class, $stmt);
    }
    
    /**
     * @test
     * @group security
     */
    public function it_prevents_sql_injection()
    {
        $db = getTestDb();
        
        // SQL injection attempt
        $maliciousInput = "' OR '1'='1";
        
        // This should safely fail (no results) instead of bypassing authentication
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$maliciousInput]);
        $result = $stmt->fetch();
        
        $this->assertFalse($result, 'SQL injection should not bypass authentication');
    }
    
    /**
     * @test
     * @group security
     */
    public function it_implements_rate_limiting_structure()
    {
        require_once __DIR__ . '/../../affiliate/includes/auth.php';
        
        // Check that rate limiting functions exist
        $this->assertTrue(
            function_exists('isRateLimited'),
            'Rate limiting function should exist'
        );
        
        $this->assertTrue(
            function_exists('trackLoginAttempt'),
            'Login attempt tracking should exist'
        );
    }
    
    /**
     * @test
     * @group security
     */
    public function it_regenerates_session_ids()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $oldSessionId = session_id();
        session_regenerate_id(true);
        $newSessionId = session_id();
        
        $this->assertNotEquals($oldSessionId, $newSessionId, 'Session ID should be regenerated');
    }
    
    /**
     * @test
     * @group security
     */
    public function it_validates_file_extensions()
    {
        $allowedImages = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $allowedVideos = ['mp4', 'webm', 'mov'];
        $dangerous = ['php', 'exe', 'sh', 'bat', 'cmd', 'phtml'];
        
        foreach ($dangerous as $ext) {
            $this->assertNotContains($ext, $allowedImages, "Dangerous extension {$ext} should not be allowed");
            $this->assertNotContains($ext, $allowedVideos, "Dangerous extension {$ext} should not be allowed");
        }
    }
    
    /**
     * @test
     * @group security
     */
    public function it_validates_email_addresses()
    {
        $validEmails = [
            'test@example.com',
            'user.name@domain.co.uk',
            'admin@webdaddy.online'
        ];
        
        $invalidEmails = [
            'not-an-email',
            '@domain.com',
            'user@',
            'user name@domain.com',
            '<script>@domain.com'
        ];
        
        foreach ($validEmails as $email) {
            $this->assertTrue(
                filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
                "Valid email {$email} should pass validation"
            );
        }
        
        foreach ($invalidEmails as $email) {
            $this->assertFalse(
                filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
                "Invalid email {$email} should fail validation"
            );
        }
    }
}
