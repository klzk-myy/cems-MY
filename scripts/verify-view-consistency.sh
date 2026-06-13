#!/bin/bash
set -e

echo "=== View Consistency Verification ==="

echo ""
echo "1. Checking for invalid @props syntax..."
if grep -R '<@props' resources/views/components/; then
    echo "✗ Invalid @props found"
    exit 1
else
    echo "✓ No invalid @props syntax"
fi

echo ""
echo "2. Checking for standalone compliance views that should use x-app-layout..."
STANDALONE=$(grep -l '<!DOCTYPE html>' resources/views/compliance/risk-dashboard/*.blade.php resources/views/compliance/sanctions/entries/*.blade.php resources/views/compliance/sanctions/import-logs/*.blade.php resources/views/compliance/screening/*.blade.php resources/views/compliance/unified/*.blade.php resources/views/pages/mfa/recovery-codes.blade.php 2>/dev/null || true)
if [ -n "$STANDALONE" ]; then
    echo "✗ Standalone pages still exist:"
    echo "$STANDALONE"
    exit 1
else
    echo "✓ All target views use shared layout"
fi

echo ""
echo "3. Checking for known dummy strings..."
DUMMY=$(grep -R "John Doe\|OFAC-12345\|ID-12345\|123 Main Street" resources/views/ || true)
if [ -n "$DUMMY" ]; then
    echo "✗ Dummy data still present:"
    echo "$DUMMY"
    exit 1
else
    echo "✓ No known dummy data strings"
fi

echo ""
echo "4. Running view consistency tests..."
php artisan test tests/Feature/Views/

echo ""
echo "5. Running Pint..."
vendor/bin/pint --format agent

echo ""
echo "=== Verification Complete ==="
