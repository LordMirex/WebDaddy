# WebDaddy Empire - Automated Testing Guide

## 📋 Overview

This comprehensive test suite provides **automated testing** for all Phase 11 requirements listed in `TEST_REPORT_PHASE11_REVISED.md`.

### Test Coverage

| Category | Coverage | Test Type |
|----------|----------|-----------|
| **Upload Workflows** | ✅ 90% | PHP Unit + Browser |
| **Image Cropping** | ✅ 75% | Browser (UI validation) |
| **CRUD Operations** | ✅ 85% | PHP Unit + Browser |
| **Affiliate System** | ✅ 80% | PHP Unit + Browser |
| **Email System** | ⚠️ 50% | PHP Unit (needs SMTP config) |
| **Analytics** | ✅ 90% | PHP Unit |
| **Security** | ✅ 95% | PHP Unit + Browser |
| **Performance** | ⚠️ Manual | Requires PageSpeed Tools |
| **Cross-Browser** | ✅ 80% | Playwright (Chromium) |

---

## 🚀 Quick Start

### Run All Tests

```bash
./tests/run-all-tests.sh
```

This runs:
1. PHP Unit Tests
2. Integration Tests
3. Security Tests
4. Browser Automation Tests

### Run Specific Test Suites

#### PHP Unit Tests Only
```bash
composer test:unit
```

#### Integration Tests Only
```bash
composer test:integration
```

#### Security Tests Only
```bash
composer test:security
```

#### Browser Tests Only
```bash
npm test
```

#### Specific Browser Test Tags
```bash
npm run test:admin       # Admin panel tests
npm run test:uploads     # File upload tests
npm run test:affiliate   # Affiliate system tests
npm run test:security    # Security browser tests
```

---

## 📁 Test Structure

```
tests/
├── bootstrap.php                      # PHPUnit bootstrap & helpers
├── phpunit.xml                        # PHPUnit configuration
├── playwright.config.js               # Playwright configuration
├── run-all-tests.sh                   # Master test runner
│
├── Unit/                              # PHP Unit Tests
│   ├── UploadHandlerTest.php         # File upload validation
│   └── SecurityTest.php              # Auth, CSRF, XSS tests
│
├── Integration/                       # Integration Tests
│   ├── DatabaseTest.php              # CRUD, relationships
│   └── ApiEndpointTest.php           # API & analytics
│
├── Security/                          # Security Penetration Tests
│   └── SecurityPenetrationTest.php   # SQL injection, XSS, etc.
│
└── Browser/                           # Playwright Browser Tests
    ├── AdminLoginTest.spec.js        # Authentication flow
    ├── TemplateManagementTest.spec.js # CRUD operations
    ├── FileUploadTest.spec.js        # Upload workflows
    ├── AffiliateSystemTest.spec.js   # Affiliate tracking
    └── SecurityTest.spec.js          # Browser security tests
```

---

## 🧪 Test Categories

### 1. Upload Workflow Tests ✅

**PHP Tests:** `UploadHandlerTest.php`
- ✅ File size validation (20MB images, 100MB videos)
- ✅ File type validation (JPEG, PNG, GIF, WebP, MP4, WebM)
- ✅ MIME type verification
- ✅ SVG rejection (XSS prevention)
- ✅ Malicious file detection
- ✅ Unique filename generation

**Browser Tests:** `FileUploadTest.spec.js`
- ✅ Upload interface visibility
- ✅ File format acceptance
- ✅ Progress indicator display
- ✅ Image cropper integration
- ✅ Error handling

**How to Run:**
```bash
vendor/bin/phpunit tests/Unit/UploadHandlerTest.php
npx playwright test tests/Browser/FileUploadTest.spec.js
```

---

### 2. Security Tests ✅

**PHP Tests:** `SecurityTest.php` + `SecurityPenetrationTest.php`
- ✅ Password hashing (bcrypt)
- ✅ CSRF token generation/validation
- ✅ XSS prevention (htmlspecialchars)
- ✅ SQL injection prevention (prepared statements)
- ✅ Session regeneration
- ✅ Rate limiting structure
- ✅ Email validation
- ✅ File extension validation
- ✅ Directory traversal prevention
- ✅ MIME type validation

**Browser Tests:** `SecurityTest.spec.js`
- ✅ CSRF token in forms
- ✅ XSS prevention in search
- ✅ Secure headers (X-Content-Type-Options, X-Frame-Options)
- ✅ Rate limiting on login
- ✅ User enumeration prevention
- ✅ Session cookie security
- ✅ File upload client-side validation

**How to Run:**
```bash
composer test:security
npx playwright test --grep @security
```

---

### 3. Admin Panel Tests ✅

**Browser Tests:** `AdminLoginTest.spec.js` + `TemplateManagementTest.spec.js`
- ✅ Login form display
- ✅ Invalid credentials rejection
- ✅ Valid login success
- ✅ Unauthorized access prevention
- ✅ Logout functionality
- ✅ CRUD operations interface
- ✅ Form validation
- ✅ Search functionality
- ✅ Upload/URL toggle

**How to Run:**
```bash
npx playwright test tests/Browser/AdminLoginTest.spec.js
npx playwright test tests/Browser/TemplateManagementTest.spec.js
```

**Default Test Credentials:**
- **Email:** admin@example.com
- **Password:** admin123

---

### 4. Database Tests ✅

**Integration Tests:** `DatabaseTest.php`
- ✅ All 23 tables exist
- ✅ Template CRUD operations
- ✅ Slug uniqueness constraint
- ✅ Affiliate commission calculation
- ✅ Referential integrity
- ✅ Analytics data storage

**How to Run:**
```bash
vendor/bin/phpunit tests/Integration/DatabaseTest.php
```

---

### 5. Affiliate System Tests ✅

**Browser Tests:** `AffiliateSystemTest.spec.js`
- ✅ Registration form display
- ✅ Email validation
- ✅ Password requirements
- ✅ Login with email or code
- ✅ Dashboard access control
- ✅ Affiliate code display
- ✅ Commission statistics
- ✅ URL tracking (aff parameter)
- ✅ Cross-page tracking persistence

**Integration Tests:** `ApiEndpointTest.php`
- ✅ Affiliate click tracking
- ✅ Commission calculation

**How to Run:**
```bash
npx playwright test tests/Browser/AffiliateSystemTest.spec.js
vendor/bin/phpunit --filter affiliate
```

---

### 6. API Endpoint Tests ✅

**Integration Tests:** `ApiEndpointTest.php`
- ✅ AJAX products endpoint
- ✅ Cart operations validation
- ✅ Analytics event tracking
- ✅ Search functionality
- ✅ Affiliate click tracking

**How to Run:**
```bash
vendor/bin/phpunit tests/Integration/ApiEndpointTest.php
```

---

## 📊 Test Reports

### PHPUnit Reports

After running tests, coverage reports are generated:
```bash
# View in browser
open vendor/phpunit/coverage/index.html
```

### Playwright Reports

After running browser tests:
```bash
npm run report
```

Opens an interactive HTML report with:
- Screenshots of failures
- Video recordings
- Step-by-step traces
- Network activity

---

## 🔧 Configuration

### PHPUnit Configuration (`phpunit.xml`)

```xml
<testsuites>
    <testsuite name="unit">
        <directory>tests/Unit</directory>
    </testsuite>
    <testsuite name="integration">
        <directory>tests/Integration</directory>
    </testsuite>
    <testsuite name="security">
        <directory>tests/Security</directory>
    </testsuite>
</testsuites>
```

### Playwright Configuration (`playwright.config.js`)

```javascript
{
  baseURL: 'http://0.0.0.0:5000',
  webServer: {
    command: 'php -S 0.0.0.0:5000 router.php',
    url: 'http://0.0.0.0:5000',
  }
}
```

---

## 🐛 Debugging Tests

### Debug PHP Tests

```bash
vendor/bin/phpunit --filter test_name --testdox
```

### Debug Browser Tests

```bash
# Headed mode (see browser)
npm run test:headed

# UI mode (interactive)
npm run test:ui

# Debug mode (step-through)
npm run test:debug
```

### View Logs

```bash
# PHP error log
tail -f error_log.txt

# Server logs
tail -f /tmp/logs/Server_*.log
```

---

## ⚠️ Known Limitations

### What Still Needs Manual Testing

1. **Email Delivery** - Requires valid SMTP configuration
2. **Performance Metrics** - Use PageSpeed Insights, GTmetrix manually
3. **Cross-Browser** - Automated tests use Chromium only
   - Manually test: Firefox, Safari, Edge, Mobile browsers
4. **Image Cropper** - UI interaction needs visual verification
5. **Video Playback** - Modal and playback quality needs manual check

### Test Environment vs. Production

- Tests use `database/test_webdaddy.db` (separate from production)
- Upload tests use `tests/Fixtures/uploads/` (temporary files)
- Sessions and cookies are isolated
- SMTP emails are not sent (unless configured)

---

## 📝 Adding New Tests

### PHP Unit Test Example

```php
<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;

class MyNewTest extends TestCase
{
    protected function setUp(): void
    {
        createTestDatabase();
        require_once __DIR__ . '/../../includes/config.php';
    }
    
    /** @test */
    public function it_does_something()
    {
        $result = someFunction();
        $this->assertTrue($result);
    }
}
```

### Browser Test Example

```javascript
const { test, expect } = require('@playwright/test');

test('should do something @mytag', async ({ page }) => {
  await page.goto('/some-page');
  await expect(page.locator('h1')).toBeVisible();
});
```

---

## 🎯 Pre-Deployment Checklist

Before deploying to production, ensure all tests pass:

- [ ] `./tests/run-all-tests.sh` completes successfully
- [ ] All PHP unit tests pass (green)
- [ ] All browser tests pass (green)
- [ ] Security tests show no vulnerabilities
- [ ] Manual cross-browser testing complete
- [ ] Performance testing (PageSpeed 90+) complete
- [ ] Email delivery tested with real SMTP
- [ ] Admin panel manually tested
- [ ] Affiliate system manually verified
- [ ] File uploads tested with real files
- [ ] Video cropper manually verified

---

## 📞 Support

If tests fail or you need help:

1. Check test output for specific error messages
2. Review `error_log.txt` for PHP errors
3. Use `npm run report` for detailed browser test failures
4. Check `/tmp/logs/` for workflow logs

---

**Generated:** November 16, 2025  
**Test Framework:** PHPUnit 11 + Playwright 1.48  
**Coverage:** ~80% automated, ~20% manual verification required
