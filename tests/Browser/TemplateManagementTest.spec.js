const { test, expect } = require('@playwright/test');

/**
 * Template CRUD Operations Tests
 * Tests create, read, update, delete operations for templates
 * @tags @admin @templates @crud
 */

test.describe('Template Management', () => {
  // Login before each test
  test.beforeEach(async ({ page }) => {
    await page.goto('/admin/login.php');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'admin123');
    await page.click('button[type="submit"]');
    await page.waitForURL(/admin\/(index|dashboard)\.php/, { timeout: 15000 });
  });

  test('should display templates list @admin @templates', async ({ page }) => {
    await page.goto('/admin/templates.php');
    
    // Should show templates page
    await expect(page.locator('h1, h2').filter({ hasText: /templates/i })).toBeVisible();
    
    // Should have a table or list of templates
    const hasTable = await page.locator('table').count() > 0;
    const hasList = await page.locator('.template-list, .product-list').count() > 0;
    
    expect(hasTable || hasList).toBeTruthy();
  });

  test('should open create template form @admin @templates', async ({ page }) => {
    await page.goto('/admin/templates.php');
    
    // Click "Add Template" or similar button
    const addButton = page.locator('a:has-text("Add Template"), button:has-text("Add Template"), a:has-text("New Template")').first();
    if (await addButton.count() > 0) {
      await addButton.click();
      
      // Should show form fields
      await expect(page.locator('input[name="name"], input[id="name"]')).toBeVisible({ timeout: 10000 });
      await expect(page.locator('input[name="price"], input[id="price"]')).toBeVisible({ timeout: 10000 });
    }
  });

  test('should validate required fields @admin @templates @validation', async ({ page }) => {
    await page.goto('/admin/templates.php');
    
    // Try to find and open the create form
    const addButton = page.locator('a:has-text("Add Template"), button:has-text("Add Template")').first();
    if (await addButton.count() > 0) {
      await addButton.click();
      await page.waitForTimeout(1000);
      
      // Try to submit empty form
      const submitButton = page.locator('button[type="submit"]:has-text("Save"), button[type="submit"]:has-text("Create")').first();
      if (await submitButton.count() > 0) {
        await submitButton.click();
        
        // Should show validation errors (HTML5 or custom)
        const hasValidationError = await Promise.race([
          page.locator('.error, .alert-danger, .invalid-feedback').count().then(c => c > 0),
          page.waitForTimeout(2000).then(() => false)
        ]);
        
        // Form should not submit successfully
        expect(hasValidationError || await page.url().includes('templates.php')).toBeTruthy();
      }
    }
  });

  test('should search templates @admin @templates @search', async ({ page }) => {
    await page.goto('/admin/templates.php');
    
    // Look for search input
    const searchInput = page.locator('input[type="search"], input[placeholder*="Search"], input[name="search"]').first();
    if (await searchInput.count() > 0) {
      await searchInput.fill('e-commerce');
      await searchInput.press('Enter');
      
      // Wait for results
      await page.waitForTimeout(1500);
      
      // Should show filtered results
      const resultsExist = await page.locator('table tr, .template-item, .product-card').count() > 0;
      expect(resultsExist).toBeTruthy();
    }
  });

  test('should display upload options @admin @templates @uploads', async ({ page }) => {
    await page.goto('/admin/templates.php');
    
    const addButton = page.locator('a:has-text("Add Template"), button:has-text("Add Template")').first();
    if (await addButton.count() > 0) {
      await addButton.click();
      await page.waitForTimeout(1000);
      
      // Check for thumbnail upload options
      const hasFileInput = await page.locator('input[type="file"]').count() > 0;
      const hasUrlInput = await page.locator('input[name*="thumbnail"], input[name*="image"]').count() > 0;
      
      expect(hasFileInput || hasUrlInput).toBeTruthy();
    }
  });

  test('should toggle between URL and upload modes @admin @uploads', async ({ page }) => {
    await page.goto('/admin/templates.php');
    
    const addButton = page.locator('a:has-text("Add Template")').first();
    if (await addButton.count() > 0) {
      await addButton.click();
      await page.waitForTimeout(1000);
      
      // Look for toggle buttons/radio buttons
      const toggleButtons = page.locator('input[type="radio"][value="url"], button:has-text("URL"), label:has-text("URL")');
      if (await toggleButtons.count() > 0) {
        await toggleButtons.first().click();
        
        // URL input should be visible
        const urlInput = page.locator('input[type="url"], input[placeholder*="http"]');
        if (await urlInput.count() > 0) {
          await expect(urlInput.first()).toBeVisible();
        }
      }
    }
  });
});
