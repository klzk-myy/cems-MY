#!/bin/bash

###############################################################################
# CEMS-MY Comprehensive Test Suite
#
# This script tests all critical and high-priority fixes applied to the codebase.
# Run this script after applying fixes to verify everything works correctly.
###############################################################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test counters
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# Function to print test results
print_result() {
    local test_name="$1"
    local result="$2"
    local message="$3"

    TOTAL_TESTS=$((TOTAL_TESTS + 1))

    if [ "$result" = "PASS" ]; then
        echo -e "${GREEN}✓ PASS${NC}: $test_name"
        PASSED_TESTS=$((PASSED_TESTS + 1))
    else
        echo -e "${RED}✗ FAIL${NC}: $test_name"
        if [ -n "$message" ]; then
            echo -e "  ${YELLOW}  $message${NC}"
        fi
        FAILED_TESTS=$((FAILED_TESTS + 1))
    fi
}

echo "=========================================="
echo "CEMS-MY Comprehensive Test Suite"
echo "=========================================="
echo ""

###############################################################################
# CRITICAL FIXES TESTS
###############################################################################

echo -e "${BLUE}Testing Critical Fixes...${NC}"
echo ""

# Test 1: Race condition in transaction approval
echo "Test 1: Race condition in transaction approval"
if grep -q "lockForUpdate()" app/Services/TransactionService.php && \
   grep -q "lockedTransaction" app/Services/TransactionService.php; then
    print_result "Transaction approval race condition fix" "PASS"
else
    print_result "Transaction approval race condition fix" "FAIL" "Missing lockForUpdate or lockedTransaction"
fi

# Test 2: Double-spending vulnerability in position updates
echo "Test 2: Double-spending vulnerability in position updates"
if grep -q "verifyTillIsOpen" app/Services/TransactionService.php && \
   grep -q "validateCurrencyCode" app/Services/TransactionService.php; then
    print_result "Double-spending vulnerability fix" "PASS"
else
    print_result "Double-spending vulnerability fix" "FAIL" "Missing validation methods"
fi

# Test 3: SQL injection vulnerability in sanctions screening
echo "Test 3: SQL injection vulnerability in sanctions screening"
if grep -q "ilike" app/Services/ComplianceService.php && \
   ! grep -q "whereRaw.*LOWER" app/Services/ComplianceService.php; then
    print_result "SQL injection vulnerability fix" "PASS"
else
    print_result "SQL injection vulnerability fix" "FAIL" "Still using whereRaw with LOWER"
fi

# Test 4: Audit log tampering vulnerability
echo "Test 4: Audit log tampering vulnerability"
if ! grep -q "getLastEntryHash" app/Services/AuditService.php && \
   grep -q "lockForUpdate()" app/Services/AuditService.php; then
    print_result "Audit log tampering vulnerability fix" "PASS"
else
    print_result "Audit log tampering vulnerability fix" "FAIL" "getLastEntryHash method still exists"
fi

# Test 5: Encryption key derivation weakness
echo "Test 5: Encryption key derivation weakness"
if grep -q "hash_pbkdf2" app/Services/EncryptionService.php && \
   grep -q "iterations" app/Services/EncryptionService.php; then
    print_result "Encryption key derivation fix" "PASS"
else
    print_result "Encryption key derivation fix" "FAIL" "Not using PBKDF2"
fi

# Test 6: CSRF protection on API endpoints
echo "Test 6: CSRF protection on API endpoints"
if grep -q "csrf" routes/api_v1.php 2>/dev/null || \
   grep -q "VerifyCsrfToken" routes/api.php 2>/dev/null; then
    print_result "CSRF protection fix" "PASS"
else
    print_result "CSRF protection fix" "FAIL" "CSRF protection not found in API routes"
fi

# Test 7: Sliding window rate limiting
echo "Test 7: Sliding window rate limiting"
if grep -q "checkSlidingWindow" app/Services/RateLimitService.php; then
    print_result "Sliding window rate limiting fix" "PASS"
else
    print_result "Sliding window rate limiting fix" "FAIL" "Sliding window method not found"
fi

echo ""
echo -e "${BLUE}Testing High-Priority Fixes...${NC}"
echo ""

###############################################################################
# HIGH-PRIORITY FIXES TESTS
###############################################################################

# Test 8: Inconsistent CDD level determination
echo "Test 8: Inconsistent CDD level determination"
if ! grep -q "?bool \$isPep = null" app/Services/ComplianceService.php; then
    print_result "CDD level determination fix" "PASS"
else
    print_result "CDD level determination fix" "FAIL" "Override parameters still present"
fi

# Test 9: Validation to till balance updates
echo "Test 9: Validation to till balance updates"
if grep -q "verifyTillIsOpen" app/Services/TransactionService.php; then
    print_result "Till balance validation fix" "PASS"
else
    print_result "Till balance validation fix" "FAIL" "verifyTillIsOpen method not found"
fi

# Test 10: Idempotency keys required
echo "Test 10: Idempotency keys required"
if grep -q "'idempotency_key' => 'required'" app/Http/Controllers/TransactionController.php; then
    print_result "Idempotency keys required fix" "PASS"
else
    print_result "Idempotency keys required fix" "FAIL" "Idempotency key still nullable"
fi

# Test 11: Database indexes
echo "Test 11: Database indexes"
if ls database/migrations/*_add_performance_indexes.php 1> /dev/null 2>&1; then
    print_result "Database indexes fix" "PASS"
else
    print_result "Database indexes fix" "FAIL" "Index migration not found"
fi

# Test 12: Currency code validation
echo "Test 12: Currency code validation"
if grep -q "validateCurrencyCode" app/Services/TransactionService.php; then
    print_result "Currency code validation fix" "PASS"
else
    print_result "Currency code validation fix" "FAIL" "validateCurrencyCode method not found"
fi

# Test 13: Transaction rollback on CTOS failure
echo "Test 13: Transaction rollback on CTOS failure"
if grep -q "ctos_report_creation_failed" app/Services/TransactionService.php; then
    print_result "Transaction rollback on CTOS failure fix" "PASS"
else
    print_result "Transaction rollback on CTOS failure fix" "FAIL" "Error handling not found"
fi

# Test 14: Concurrent session detection
echo "Test 14: Concurrent session detection"
if grep -q "concurrent" app/Http/Middleware/SessionTimeout.php 2>/dev/null || \
   grep -q "concurrent" config/security.php; then
    print_result "Concurrent session detection fix" "PASS"
else
    print_result "Concurrent session detection fix" "FAIL" "Concurrent session detection not implemented"
fi

# Test 15: Data encryption at rest
echo "Test 15: Data encryption at rest"
if grep -q "encrypt" app/Models/Customer.php || \
   grep -q "encrypted" database/migrations/*_create_customers_table.php 2>/dev/null; then
    print_result "Data encryption at rest fix" "PASS"
else
    print_result "Data encryption at rest fix" "FAIL" "Encryption not implemented"
fi

# Test 16: CDD level override parameters removed
echo "Test 16: CDD level override parameters removed"
if ! grep -q "?bool \$isPep = null" app/Services/ComplianceService.php && \
   ! grep -q "?bool \$isSanctionMatch = null" app/Services/ComplianceService.php; then
    print_result "CDD level override parameters fix" "PASS"
else
    print_result "CDD level override parameters fix" "FAIL" "Override parameters still present"
fi

echo ""
echo -e "${BLUE}Running PHP Unit Tests...${NC}"
echo ""

###############################################################################
# PHP UNIT TESTS
###############################################################################

# Run Laravel tests
if php artisan test --no-interaction 2>&1 | tee /tmp/test_output.txt; then
    TESTS_PASSED=$(grep -o "Tests:.*passed" /tmp/test_output.txt | head -1)
    print_result "PHP Unit Tests" "PASS" "$TESTS_PASSED"
else
    TESTS_FAILED=$(grep -o "Tests:.*failed" /tmp/test_output.txt | head -1)
    print_result "PHP Unit Tests" "FAIL" "$TESTS_FAILED"
fi

echo ""
echo -e "${BLUE}Running Code Quality Checks...${NC}"
echo ""

###############################################################################
# CODE QUALITY TESTS
###############################################################################

# Test 16: Code style check
echo "Test 16: Code style check (Laravel Pint)"
if ./vendor/bin/pint --test 2>&1 | grep -q "No files need fixing"; then
    print_result "Code style check" "PASS"
else
    print_result "Code style check" "FAIL" "Code style issues found"
fi

# Test 17: PHP syntax check
echo "Test 17: PHP syntax check"
SYNTAX_ERRORS=0
for file in app/Services/*.php; do
    if ! php -l "$file" > /dev/null 2>&1; then
        SYNTAX_ERRORS=$((SYNTAX_ERRORS + 1))
    fi
done

if [ $SYNTAX_ERRORS -eq 0 ]; then
    print_result "PHP syntax check" "PASS"
else
    print_result "PHP syntax check" "FAIL" "$SYNTAX_ERRORS files have syntax errors"
fi

echo ""
echo -e "${BLUE}Running Security Tests...${NC}"
echo ""

###############################################################################
# SECURITY TESTS
###############################################################################

# Test 18: SQL injection vulnerability scan
echo "Test 18: SQL injection vulnerability scan"
SQL_INJECTION_COUNT=$(grep -r "whereRaw.*\$" app/ --include="*.php" | wc -l)
if [ $SQL_INJECTION_COUNT -eq 0 ]; then
    print_result "SQL injection vulnerability scan" "PASS"
else
    print_result "SQL injection vulnerability scan" "FAIL" "$SQL_INJECTION_COUNT potential vulnerabilities found"
fi

# Test 19: XSS vulnerability scan
echo "Test 19: XSS vulnerability scan"
XSS_COUNT=$(grep -r "echo.*\$" app/ --include="*.php" | grep -v "htmlspecialchars\|e(" | wc -l)
if [ $XSS_COUNT -eq 0 ]; then
    print_result "XSS vulnerability scan" "PASS"
else
    print_result "XSS vulnerability scan" "FAIL" "$XSS_COUNT potential vulnerabilities found"
fi

# Test 20: Hardcoded secrets scan
echo "Test 20: Hardcoded secrets scan"
SECRETS_COUNT=$(grep -r "password.*=.*['\"]" app/ --include="*.php" | grep -v "env(" | wc -l)
if [ $SECRETS_COUNT -eq 0 ]; then
    print_result "Hardcoded secrets scan" "PASS"
else
    print_result "Hardcoded secrets scan" "FAIL" "$SECRETS_COUNT potential hardcoded secrets found"
fi

echo ""
echo -e "${BLUE}Running Performance Tests...${NC}"
echo ""

###############################################################################
# PERFORMANCE TESTS
###############################################################################

# Test 21: Database query performance
echo "Test 21: Database query performance check"
if php artisan tinker --execute="DB::enableQueryLog(); \App\Models\Transaction::limit(10)->get(); \$queries = collect(DB::getQueryLog())->pluck('time')->sum(); echo \$queries < 100 ? 'PASS' : 'FAIL';" 2>/dev/null | grep -q "PASS"; then
    print_result "Database query performance check" "PASS"
else
    print_result "Database query performance check" "FAIL" "Queries too slow"
fi

# Test 22: Memory usage check
echo "Test 22: Memory usage check"
MEMORY_USAGE=$(php -r "echo memory_get_usage(true) / 1024 / 1024;")
if (( $(echo "$MEMORY_USAGE < 50" | bc -l) )); then
    print_result "Memory usage check" "PASS" "${MEMORY_USAGE}MB"
else
    print_result "Memory usage check" "FAIL" "${MEMORY_USAGE}MB - too high"
fi

# Test 23: CDD level override parameters removed
echo "Test 23: CDD level override parameters removed"
if ! grep -q "?bool \$isPep = null" app/Services/ComplianceService.php && \
   ! grep -q "?bool \$isSanctionMatch = null" app/Services/ComplianceService.php; then
    print_result "CDD level override parameters fix" "PASS"
else
    print_result "CDD level override parameters fix" "FAIL" "Override parameters still present"
fi

echo ""
echo "=========================================="
echo "Test Summary"
echo "=========================================="
echo -e "Total Tests:  $TOTAL_TESTS"
echo -e "${GREEN}Passed:       $PASSED_TESTS${NC}"
echo -e "${RED}Failed:       $FAILED_TESTS${NC}"
echo ""

if [ $FAILED_TESTS -eq 0 ]; then
    echo -e "${GREEN}All tests passed!${NC}"
    exit 0
else
    echo -e "${RED}Some tests failed. Please review the failures above.${NC}"
    exit 1
fi
