#!/bin/bash
echo "📦 Menjalankan Laravel caching..."

php artisan cache:clear
php artisan config:cache
php artisan route:cache

echo "✅ Laravel config & route cached (tanpa view)"
