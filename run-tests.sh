#!/bin/bash
# run-tests.sh - Run PHPUnit with opcache/xdebug disabled for stable execution
# Usage: ./run-tests.sh [phpunit-args]

php -d opcache.enable=0 -d xdebug.mode=off ./vendor/bin/phpunit "$@"