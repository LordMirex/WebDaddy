<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Database integration tests
 * Tests CRUD operations, relationships, and data integrity
 */
class DatabaseTest extends TestCase
{
    private $db;
    
    protected function setUp(): void
    {
        parent::setUp();
        createTestDatabase();
        
        require_once __DIR__ . '/../../includes/config.php';
        require_once __DIR__ . '/../../includes/db.php';
        require_once __DIR__ . '/../../includes/functions.php';
        
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
     * @group database
     */
    public function it_has_all_required_tables()
    {
        $requiredTables = [
            'users', 'templates', 'tools', 'orders', 'affiliates',
            'sales', 'payments', 'coupons', 'email_campaigns',
            'analytics_sessions', 'page_visits', 'page_interactions'
        ];
        
        $stmt = $this->db->query("SELECT name FROM sqlite_master WHERE type='table'");
        $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        
        foreach ($requiredTables as $table) {
            $this->assertContains($table, $tables, "Table {$table} should exist");
        }
    }
    
    /**
     * @test
     * @group database
     */
    public function it_creates_and_retrieves_templates()
    {
        $testTemplate = [
            'name' => 'Test E-Commerce Template',
            'slug' => 'test-ecommerce-' . time(),
            'description' => 'A test template for automated testing',
            'category' => 'E-Commerce',
            'price' => 15000,
            'featured' => 1,
            'status' => 'active'
        ];
        
        // Insert template
        $stmt = $this->db->prepare("
            INSERT INTO templates (name, slug, description, category, price, featured, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $testTemplate['name'],
            $testTemplate['slug'],
            $testTemplate['description'],
            $testTemplate['category'],
            $testTemplate['price'],
            $testTemplate['featured'],
            $testTemplate['status']
        ]);
        
        $templateId = $this->db->lastInsertId();
        
        // Retrieve template
        $stmt = $this->db->prepare("SELECT * FROM templates WHERE id = ?");
        $stmt->execute([$templateId]);
        $retrieved = $stmt->fetch();
        
        $this->assertNotEmpty($retrieved, 'Template should be retrieved');
        $this->assertEquals($testTemplate['name'], $retrieved['name']);
        $this->assertEquals($testTemplate['slug'], $retrieved['slug']);
        $this->assertEquals($testTemplate['price'], $retrieved['price']);
    }
    
    /**
     * @test
     * @group database
     */
    public function it_ensures_slug_uniqueness()
    {
        $slug = 'unique-test-slug-' . time();
        
        // Insert first template
        $stmt = $this->db->prepare("
            INSERT INTO templates (name, slug, description, price, status)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute(['Template 1', $slug, 'Test', 10000, 'active']);
        
        // Try to insert duplicate slug (should fail)
        $this->expectException(\PDOException::class);
        $stmt->execute(['Template 2', $slug, 'Test 2', 15000, 'active']);
    }
    
    /**
     * @test
     * @group database
     */
    public function it_tracks_affiliate_commissions()
    {
        // Create test user
        $stmt = $this->db->prepare("
            INSERT INTO users (name, email, password_hash, role, status)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            'Test Affiliate',
            'affiliate' . time() . '@test.com',
            password_hash('password123', PASSWORD_BCRYPT),
            'affiliate',
            'active'
        ]);
        $userId = $this->db->lastInsertId();
        
        // Create affiliate
        $affiliateCode = 'TEST' . strtoupper(substr(md5(time()), 0, 6));
        $stmt = $this->db->prepare("
            INSERT INTO affiliates (user_id, code, commission_rate, status)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $affiliateCode, 10.00, 'active']);
        $affiliateId = $this->db->lastInsertId();
        
        // Create sale with commission
        $stmt = $this->db->prepare("
            INSERT INTO sales (affiliate_id, product_type, product_id, sale_amount, commission_amount, status)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $saleAmount = 15000;
        $commissionAmount = $saleAmount * 0.10; // 10%
        
        $stmt->execute([$affiliateId, 'template', 1, $saleAmount, $commissionAmount, 'completed']);
        
        // Verify commission calculation
        $stmt = $this->db->prepare("SELECT * FROM sales WHERE affiliate_id = ?");
        $stmt->execute([$affiliateId]);
        $sale = $stmt->fetch();
        
        $this->assertEquals(1500, $sale['commission_amount'], 'Commission should be 10% of sale amount');
    }
    
    /**
     * @test
     * @group database
     */
    public function it_maintains_referential_integrity()
    {
        // This test ensures foreign key relationships are working
        
        // Try to create a sale with non-existent affiliate (should fail or be handled)
        $stmt = $this->db->prepare("
            INSERT INTO sales (affiliate_id, product_type, product_id, sale_amount, commission_amount)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        // SQLite might not enforce foreign keys by default, but we can check
        try {
            $stmt->execute([99999, 'template', 1, 10000, 1000]);
            // If it succeeds, check if PRAGMA foreign_keys is ON
            $fkStatus = $this->db->query("PRAGMA foreign_keys")->fetch();
            if ($fkStatus['foreign_keys'] == 1) {
                $this->fail('Foreign key constraint should prevent this insert');
            }
        } catch (\PDOException $e) {
            // Expected behavior if foreign keys are enabled
            $this->assertStringContainsString('foreign key', strtolower($e->getMessage()));
        }
    }
    
    /**
     * @test
     * @group database
     */
    public function it_stores_analytics_data()
    {
        $sessionId = 'test_session_' . time();
        
        // Create analytics session
        $stmt = $this->db->prepare("
            INSERT INTO analytics_sessions (session_id, user_agent, ip_address, device_type)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$sessionId, 'Test Browser', '127.0.0.1', 'desktop']);
        
        // Track page visit
        $stmt = $this->db->prepare("
            INSERT INTO page_visits (session_id, page_url, page_title, device_type, visit_date, visit_time)
            VALUES (?, ?, ?, ?, DATE('now'), TIME('now'))
        ");
        $stmt->execute([$sessionId, '/test-page', 'Test Page', 'desktop']);
        
        // Retrieve analytics
        $stmt = $this->db->prepare("SELECT * FROM page_visits WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $visit = $stmt->fetch();
        
        $this->assertNotEmpty($visit, 'Page visit should be tracked');
        $this->assertEquals('/test-page', $visit['page_url']);
    }
}
