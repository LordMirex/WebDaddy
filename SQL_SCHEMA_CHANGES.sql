-- WebDaddy Empire - Database Schema Changes for Payment & Delivery System

-- ============================================================================
-- 1. NEW TABLE: payments
-- ============================================================================
CREATE TABLE payments (
  id INT PRIMARY KEY AUTO_INCREMENT,
  order_id INT NOT NULL UNIQUE,
  payment_method ENUM('manual', 'paystack') NOT NULL,
  amount DECIMAL(10, 2) NOT NULL,
  currency VARCHAR(3) DEFAULT 'NGN',
  status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
  
  -- Paystack specific fields
  paystack_reference VARCHAR(255) UNIQUE,
  paystack_access_code VARCHAR(255),
  paystack_authorization_url TEXT,
  paystack_customer_code VARCHAR(255),
  
  -- Manual payment fields
  manual_verified_by INT NULL,
  manual_verified_at TIMESTAMP NULL,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  INDEX idx_order_id (order_id),
  INDEX idx_status (status),
  INDEX idx_paystack_reference (paystack_reference)
);

-- ============================================================================
-- 2. NEW TABLE: deliveries
-- ============================================================================
CREATE TABLE deliveries (
  id INT PRIMARY KEY AUTO_INCREMENT,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  product_type ENUM('template', 'tool') NOT NULL,
  
  -- Delivery details
  delivery_method ENUM('email', 'download', 'hosted', 'whatsapp') NOT NULL,
  delivery_type ENUM('immediate', 'pending_24h', 'manual') NOT NULL,
  delivery_status ENUM('pending', 'sent', 'delivered', 'failed') DEFAULT 'pending',
  
  -- Delivery content
  delivery_link TEXT,
  delivery_content TEXT,
  file_path VARCHAR(255),
  
  -- Delivery tracking
  sent_at TIMESTAMP NULL,
  delivered_at TIMESTAMP NULL,
  delivery_attempts INT DEFAULT 0,
  last_attempt_at TIMESTAMP NULL,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  INDEX idx_order_id (order_id),
  INDEX idx_status (delivery_status),
  INDEX idx_product_type (product_type)
);

-- ============================================================================
-- 3. NEW TABLE: tool_files
-- ============================================================================
CREATE TABLE tool_files (
  id INT PRIMARY KEY AUTO_INCREMENT,
  tool_id INT NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  file_type ENUM('attachment', 'instruction', 'link', 'video') NOT NULL,
  file_size INT,
  mime_type VARCHAR(100),
  is_public BOOLEAN DEFAULT FALSE,
  download_count INT DEFAULT 0,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (tool_id) REFERENCES tools(id) ON DELETE CASCADE,
  INDEX idx_tool_id (tool_id)
);

-- ============================================================================
-- 4. NEW TABLE: template_hosting
-- ============================================================================
CREATE TABLE template_hosting (
  id INT PRIMARY KEY AUTO_INCREMENT,
  template_id INT NOT NULL,
  order_id INT NOT NULL,
  
  -- Hosting details
  hosted_domain VARCHAR(255),
  hosted_url TEXT,
  status ENUM('pending', 'ready', 'failed', 'expired') DEFAULT 'pending',
  
  -- Timing
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ready_at TIMESTAMP NULL,
  expires_at TIMESTAMP NULL,
  
  FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE CASCADE,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  INDEX idx_template_id (template_id),
  INDEX idx_status (status)
);

-- ============================================================================
-- 5. MODIFY TABLE: orders
-- ============================================================================
ALTER TABLE orders ADD COLUMN (
  payment_method ENUM('manual', 'paystack') DEFAULT 'manual',
  delivery_status ENUM('pending', 'in_progress', 'fulfilled', 'failed') DEFAULT 'pending',
  email_verified BOOLEAN DEFAULT FALSE,
  paystack_payment_id VARCHAR(255),
  is_test_order BOOLEAN DEFAULT FALSE,
  
  INDEX idx_payment_method (payment_method),
  INDEX idx_delivery_status (delivery_status)
);

-- ============================================================================
-- 6. MODIFY TABLE: tools
-- ============================================================================
ALTER TABLE tools ADD COLUMN (
  delivery_type ENUM('email_attachment', 'file_download', 'both', 'video_link') DEFAULT 'both',
  has_attached_files BOOLEAN DEFAULT FALSE,
  requires_email BOOLEAN DEFAULT TRUE,
  email_subject VARCHAR(255),
  email_instructions TEXT,
  
  INDEX idx_delivery_type (delivery_type)
);

-- ============================================================================
-- 7. MODIFY TABLE: templates
-- ============================================================================
ALTER TABLE templates ADD COLUMN (
  delivery_type ENUM('hosted_domain', 'file_download', 'both') DEFAULT 'hosted_domain',
  requires_email BOOLEAN DEFAULT TRUE,
  delivery_wait_hours INT DEFAULT 24,
  
  INDEX idx_delivery_type (delivery_type)
);

-- ============================================================================
-- 8. NEW TABLE: email_queue (for reliable email delivery)
-- ============================================================================
CREATE TABLE email_queue (
  id INT PRIMARY KEY AUTO_INCREMENT,
  recipient_email VARCHAR(255) NOT NULL,
  email_type ENUM('order_confirmation', 'payment_received', 'tools_ready', 'template_ready', 'delivery_link', 'reminder') NOT NULL,
  order_id INT,
  delivery_id INT,
  
  subject VARCHAR(255),
  body LONGTEXT,
  
  status ENUM('pending', 'sent', 'failed', 'bounced') DEFAULT 'pending',
  attempts INT DEFAULT 0,
  last_error TEXT,
  
  scheduled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  sent_at TIMESTAMP NULL,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE,
  INDEX idx_status (status),
  INDEX idx_email_type (email_type),
  INDEX idx_scheduled_at (scheduled_at)
);

-- ============================================================================
-- 9. NEW TABLE: payment_logs (for debugging & audit trail)
-- ============================================================================
CREATE TABLE payment_logs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  order_id INT,
  payment_id INT,
  
  event_type VARCHAR(100),
  provider ENUM('paystack', 'manual', 'system') DEFAULT 'system',
  status VARCHAR(100),
  amount DECIMAL(10, 2),
  
  request_data JSON,
  response_data JSON,
  error_message TEXT,
  
  ip_address VARCHAR(45),
  user_agent VARCHAR(255),
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
  INDEX idx_order_id (order_id),
  INDEX idx_event_type (event_type),
  INDEX idx_created_at (created_at)
);

-- ============================================================================
-- 10. USEFUL INDEXES FOR QUERY OPTIMIZATION
-- ============================================================================
CREATE INDEX idx_orders_email ON orders(customer_email);
CREATE INDEX idx_orders_created_at ON orders(created_at);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_order_items_order_id ON order_items(order_id);

-- ============================================================================
-- NOTES
-- ============================================================================
/*
Execution Steps:
1. Run all CREATE TABLE statements first
2. Run all ALTER TABLE statements next
3. Verify all indexes are created

Data Migration:
- Existing orders: Add default values for new columns
- Set payment_method = 'manual' for all existing orders
- No data will be lost; only new columns added

Rollback:
DROP TABLE IF EXISTS payment_logs;
DROP TABLE IF EXISTS email_queue;
DROP TABLE IF EXISTS template_hosting;
DROP TABLE IF EXISTS tool_files;
DROP TABLE IF EXISTS deliveries;
DROP TABLE IF EXISTS payments;
ALTER TABLE tools DROP COLUMN delivery_type, has_attached_files, requires_email, email_subject, email_instructions;
ALTER TABLE templates DROP COLUMN delivery_type, requires_email, delivery_wait_hours;
ALTER TABLE orders DROP COLUMN payment_method, delivery_status, email_verified, paystack_payment_id, is_test_order;
*/
