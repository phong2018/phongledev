#!/bin/bash
set -e

echo "==> Building and starting containers..."
docker compose up -d --build

echo "==> Waiting for DB to be ready..."
docker compose exec app sh -c "until php artisan migrate --force 2>/dev/null; do echo 'Retrying in 3s...'; sleep 3; done"

echo "==> Clearing caches..."
docker compose exec app php artisan config:clear
docker compose exec app php artisan cache:clear

echo ""
echo "✅ Ready!"
echo "   App:        http://localhost:8080"
echo "   phpMyAdmin: http://localhost:8081"
echo "   MySQL port: 3306 (user: laravel / secret)"
