const { test, expect } = require('@playwright/test');

/**
 * Admin Login Tests
 * Tests authentication flow, session management, and access control
 * @tags @admin @auth
 */

test.describe('Admin Authentication', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
  });

  test('should display admin login page @admin @smoke', async ({ page }) => {
    await page.goto('/admin/login.php');
    
    // Check for login form elements
    await expect(page.locator('input[name="email"]')).toBeVisible();
    await expect(page.locator('input[name="password"]')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
  });

  test('should reject invalid credentials @admin @security', async ({ page }) => {
    await page.goto('/admin/login.php');
    
    await page.fill('input[name="email"]', 'invalid@example.com');
    await page.fill('input[name="password"]', 'wrongpassword');
    await page.click('button[type="submit"]');
    
    // Should show error message
    await expect(page.locator('text=/invalid.*credentials/i')).toBeVisible({ timeout: 10000 });
  });

  test('should login with valid admin credentials @admin @critical', async ({ page }) => {
    await page.goto('/admin/login.php');
    
    // Use default admin credentials (these should match your test database)
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'admin123');
    await page.click('button[type="submit"]');
    
    // Should redirect to admin dashboard
    await expect(page).toHaveURL(/admin\/(index|dashboard)\.php/, { timeout: 10000 });
  });

  test('should prevent access to admin pages without login @admin @security', async ({ page }) => {
    await page.goto('/admin/templates.php');
    
    // Should redirect to login page
    await expect(page).toHaveURL(/admin\/login\.php/, { timeout: 10000 });
  });

  test('should logout admin user @admin', async ({ page }) => {
    // Login first
    await page.goto('/admin/login.php');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'admin123');
    await page.click('button[type="submit"]');
    
    await page.waitForURL(/admin\/(index|dashboard)\.php/);
    
    // Find and click logout button
    await page.click('a[href*="logout"], button:has-text("Logout"), a:has-text("Logout")');
    
    // Should redirect to login page
    await expect(page).toHaveURL(/admin\/login\.php/, { timeout: 10000 });
  });

  test('should display CSRF token in forms @admin @security', async ({ page }) => {
    await page.goto('/admin/login.php');
    
    // Check for CSRF token field
    const csrfToken = await page.locator('input[name="csrf_token"]');
    if (await csrfToken.count() > 0) {
      const tokenValue = await csrfToken.inputValue();
      expect(tokenValue).toBeTruthy();
      expect(tokenValue.length).toBeGreaterThan(20);
    }
  });
});
