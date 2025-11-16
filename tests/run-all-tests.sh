#!/bin/bash
###############################################################################
# WebDaddy Empire - Automated Test Runner
# Runs all PHP and browser tests with comprehensive reporting
###############################################################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Print header
echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║       WebDaddy Empire - Automated Test Suite             ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Check if test database exists, create if not
if [ ! -f "database/test_webdaddy.db" ]; then
    echo -e "${YELLOW}Creating test database...${NC}"
    cp database/webdaddy.db database/test_webdaddy.db 2>/dev/null || touch database/test_webdaddy.db
fi

# Track test results
PHP_TESTS_PASSED=false
BROWSER_TESTS_PASSED=false

###############################################################################
# 1. PHP Unit & Integration Tests
###############################################################################
echo -e "\n${BLUE}[1/3] Running PHP Unit & Integration Tests...${NC}"
echo "─────────────────────────────────────────────────────────────"

if vendor/bin/phpunit --colors=always --testdox; then
    echo -e "${GREEN}✓ PHP tests passed${NC}"
    PHP_TESTS_PASSED=true
else
    echo -e "${RED}✗ PHP tests failed${NC}"
    PHP_TESTS_PASSED=false
fi

###############################################################################
# 2. Security Tests
###############################################################################
echo -e "\n${BLUE}[2/3] Running Security Tests...${NC}"
echo "─────────────────────────────────────────────────────────────"

if vendor/bin/phpunit --testsuite security --colors=always --testdox; then
    echo -e "${GREEN}✓ Security tests passed${NC}"
else
    echo -e "${RED}✗ Security tests failed${NC}"
fi

###############################################################################
# 3. Browser Automation Tests (Playwright)
###############################################################################
echo -e "\n${BLUE}[3/3] Running Browser Automation Tests...${NC}"
echo "─────────────────────────────────────────────────────────────"

# Check if server is running, start if not
if ! lsof -i:5000 > /dev/null 2>&1; then
    echo -e "${YELLOW}Starting development server...${NC}"
    php -S 0.0.0.0:5000 router.php > /dev/null 2>&1 &
    SERVER_PID=$!
    sleep 3
    echo -e "${GREEN}Server started (PID: $SERVER_PID)${NC}"
    CLEANUP_SERVER=true
else
    echo -e "${GREEN}Server already running on port 5000${NC}"
    CLEANUP_SERVER=false
fi

# Run Playwright tests
if npx playwright test --reporter=list; then
    echo -e "${GREEN}✓ Browser tests passed${NC}"
    BROWSER_TESTS_PASSED=true
else
    echo -e "${RED}✗ Browser tests failed${NC}"
    BROWSER_TESTS_PASSED=false
fi

# Stop server if we started it
if [ "$CLEANUP_SERVER" = true ]; then
    echo -e "${YELLOW}Stopping development server...${NC}"
    kill $SERVER_PID 2>/dev/null || true
fi

###############################################################################
# Test Summary
###############################################################################
echo -e "\n${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║                    Test Summary                            ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

if [ "$PHP_TESTS_PASSED" = true ]; then
    echo -e "${GREEN}✓ PHP Unit/Integration Tests: PASSED${NC}"
else
    echo -e "${RED}✗ PHP Unit/Integration Tests: FAILED${NC}"
fi

if [ "$BROWSER_TESTS_PASSED" = true ]; then
    echo -e "${GREEN}✓ Browser Automation Tests: PASSED${NC}"
else
    echo -e "${RED}✗ Browser Automation Tests: FAILED${NC}"
fi

echo ""
echo -e "${BLUE}Test Reports:${NC}"
echo "  - PHP Coverage: vendor/phpunit/coverage/"
echo "  - Browser Report: npx playwright show-report"
echo ""

# Exit with appropriate code
if [ "$PHP_TESTS_PASSED" = true ] && [ "$BROWSER_TESTS_PASSED" = true ]; then
    echo -e "${GREEN}All tests passed! ✓${NC}"
    exit 0
else
    echo -e "${RED}Some tests failed. Please review the output above.${NC}"
    exit 1
fi
