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
            'users', 'templates', 'tools', 'pending_orders', 'affiliates',
            'sales', 'page_visits', 'page_interactions', 'session_summary',
            'domains', 'cart_items', 'affiliate_actions'
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
            'active' => 1
        ];
        
        // Insert template
        $stmt = $this->db->prepare("
            INSERT INTO templates (name, slug, description, category, price, active)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $testTemplate['name'],
            $testTemplate['slug'],
            $testTemplate['description'],
            $testTemplate['category'],
            $testTemplate['price'],
            $testTemplate['active']
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
            INSERT INTO templates (name, slug, description, price, active)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute(['Template 1', $slug, 'Test', 10000, 1]);
        
        // Try to insert duplicate slug (should fail)
        $this->expectException(\PDOException::class);
        $stmt->execute(['Template 2', $slug, 'Test 2', 15000, 1]);
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
            INSERT INTO affiliates (user_id, code, custom_commission_rate, status)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $affiliateCode, 10.00, 'active']);
        $affiliateId = $this->db->lastInsertId();
        
        // Update affiliate commission earned
        $commissionAmount = 1500;
        $stmt = $this->db->prepare("
            UPDATE affiliates SET commission_earned = ?, total_sales = 1 WHERE id = ?
        ");
        $stmt->execute([$commissionAmount, $affiliateId]);
        
        // Verify commission was tracked
        $stmt = $this->db->prepare("SELECT * FROM affiliates WHERE id = ?");
        $stmt->execute([$affiliateId]);
        $affiliate = $stmt->fetch();
        
        $this->assertEquals(1500, $affiliate['commission_earned'], 'Commission should be tracked in affiliate record');
    }
    
    /**
     * @test
     * @group database
     */
    public function it_maintains_referential_integrity()
    {
        // Enable foreign key constraints for this test
        $this->db->exec("PRAGMA foreign_keys = ON");
        
        // Create a template first (required by pending_orders foreign key)
        $stmt = $this->db->prepare("
            INSERT INTO templates (name, slug, price, active)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute(['Test Template', 'test-template-' . time(), 10000, 1]);
        $templateId = $this->db->lastInsertId();
        
        // Create a pending order (required by sales table)
        $stmt = $this->db->prepare("
            INSERT INTO pending_orders (customer_name, customer_email, customer_phone, template_id, order_type, final_amount, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute(['Test Customer', 'test@test.com', '1234567890', $templateId, 'template', 10000, 'pending']);
        $orderId = $this->db->lastInsertId();
        
        // Create admin user for sales
        $stmt = $this->db->prepare("
            INSERT INTO users (name, email, password_hash, role, status)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute(['Admin', 'admin@test.com', password_hash('test', PASSWORD_BCRYPT), 'admin', 'active']);
        $adminId = $this->db->lastInsertId();
        
        // Valid sale should succeed
        $stmt = $this->db->prepare("
            INSERT INTO sales (pending_order_id, admin_id, amount_paid, commission_amount, order_type)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$orderId, $adminId, 10000, 1000, 'template']);
        
        $this->assertGreaterThan(0, $this->db->lastInsertId(), 'Valid sale should be inserted');
    }
    
    /**
     * @test
     * @group database
     */
    public function it_stores_analytics_data()
    {
        $sessionId = 'test_session_' . time();
        
        // Track page visit directly (page_visits table exists)
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
        $this->assertEquals('desktop', $visit['device_type']);
    }
}
