#!/bin/bash
JS_DIR="/www/wwwroot/local.host/resources/js"
CSS_DIR="/www/wwwroot/local.host/resources/css"
echo "=== Unused JS Files ==="
find "$JS_DIR" -name "*.js" -type f | while read -r file; do
    rel_path="${file#$JS_DIR/}"
    [ "$rel_path" = "app.js" ] && continue
    [ "$rel_path" = "bootstrap.js" ] && continue
    if ! rg -q "$rel_path" /www/wwwroot/local.host/resources/js/ --type js 2>/dev/null; then
        no_ext="${rel_path%.js}"
        if ! rg -q "$no_ext" /www/wwwroot/local.host/resources/js/ --type js 2>/dev/null; then
            echo "UNUSED: $rel_path"
        fi
    fi
done
echo ""
echo "=== Unused CSS Files ==="
find "$CSS_DIR" -name "*.css" -type f | while read -r file; do
    rel_path="${file#$CSS_DIR/}"
    [ "$rel_path" = "app.css" ] && continue
    if ! rg -q "$rel_path" /www/wwwroot/local.host/resources/ -g '*.{css,js,vue}' 2>/dev/null; then
        echo "UNUSED: $rel_path"
    fi
done