#!/bin/bash

# Bulk Import System - Development Start Script

echo "🚀 Starting Bulk Import System Development Environment..."
echo ""

# Check if .env exists
if [ ! -f .env ]; then
    echo "⚠️  .env file not found. Creating from .env.example..."
    cp .env.example .env
    php artisan key:generate
fi

# Check if node_modules exists
if [ ! -d "node_modules" ]; then
    echo "📦 Installing Node dependencies..."
    npm install
fi

# Check if vendor exists
if [ ! -d "vendor" ]; then
    echo "📦 Installing Composer dependencies..."
    composer install
fi

# Check if database is migrated
echo "🗄️  Checking database..."
php artisan migrate --force 2>/dev/null || php artisan migrate

# Check if storage link exists
if [ ! -L "public/storage" ]; then
    echo "🔗 Creating storage symlink..."
    php artisan storage:link
fi

# Check if mock data exists
if [ ! -f "storage/app/mock_products.csv" ]; then
    echo "📊 Generating mock data (10,000 rows + 100 images)..."
    php artisan mock:generate --rows=10000 --images=100
else
    echo "✓ Mock data already exists"
fi

echo ""
echo "✨ Ready to start development servers!"
echo ""
echo "Starting concurrent servers..."
echo "  - Laravel Server: http://localhost:8000"
echo "  - Queue Worker: Processing background jobs"
echo "  - Vite Dev Server: Hot module replacement"
echo "  - Logs: Real-time application logs"
echo ""
echo "Press Ctrl+C to stop all services"
echo ""

# Start all services with composer dev
composer dev
