#!/bin/sh

echo "Checking dependencies..."
if [ ! -f "vendor/autoload.php" ]; then
    echo "Composer dependencies not found. Installing..."
    composer install
fi

if [ ! -d "node_modules" ]; then
    echo "NPM dependencies not found. Installing..."
    npm install
fi

# Pastikan key ada (untuk pertama kali setup)
if [ ! -f ".env" ]; then
    cp .env.example .env
    php artisan key:generate
fi

echo "Starting application..."
exec "$@"
