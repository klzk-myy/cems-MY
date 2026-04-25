#!/bin/bash

# Browser Testing Script
# Usage: ./browser-test.sh

echo "=========================================="
echo "CEMS-MY Browser Testing Setup"
echo "=========================================="

# Backup current .env
if [ -f .env ]; then
    cp .env .env.backup
    echo "✓ Backed up current .env to .env.backup"
fi

# Use browser testing environment
cp .env.browser .env
echo "✓ Switched to browser testing environment (SQLite)"

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
echo "✓ Cleared Laravel caches"

# Run migrations
echo "Running migrations..."
php artisan migrate:fresh --force --seed --seeder=DatabaseSeeder
if [ $? -eq 0 ]; then
    echo "✓ Database migrated and seeded"
else
    echo "✗ Migration failed"
    # Restore backup
    cp .env.backup .env 2>/dev/null
    exit 1
fi

echo ""
echo "=========================================="
echo "Application ready for browser testing!"
echo "=========================================="
echo ""
echo "Test user credentials:"
echo "  Email: teller@test.com"
echo "  Password: password"
echo ""
echo "To run tests:"
echo "  php artisan test --env=browser"
echo ""
echo "To restore production environment:"
echo "  cp .env.backup .env"
