# Quick Start Guide

## 🚀 Get Started in 5 Minutes

### 1. Initial Setup
```bash
# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Link storage
php artisan storage:link
```

### 2. Generate Mock Data
```bash
# Generate 10,000 CSV rows and 100 test images
php artisan mock:generate

# Or customize:
php artisan mock:generate --rows=50000 --images=500
```

This creates:
- `storage/app/mock_products.csv` - Ready to import CSV file
- `storage/app/test_images/` - Test images folder

### 3. Start Development Server
```bash
# Option 1: Run all services at once (recommended)
composer dev

# Option 2: Run manually in separate terminals
php artisan serve        # Terminal 1
php artisan queue:work   # Terminal 2
npm run dev              # Terminal 3
```

### 4. Access the Application
Open http://localhost:8000 in your browser

**Default Login:** If you haven't set up users yet, you may need to register first.

### 5. Test the Features

#### CSV Import:
1. Go to http://localhost:8000/import
2. Click "CSV Import" tab
3. Upload the generated mock CSV from `storage/app/mock_products.csv`
4. Click "Import CSV"
5. Watch the import statistics appear!

#### Image Upload:
1. Go to http://localhost:8000/import
2. Click "Image Upload" tab
3. Drag images from `storage/app/test_images/` folder
4. Watch chunked upload in action with progress bars
5. See variant generation complete

#### View Products:
1. Go to http://localhost:8000/products
2. Browse imported products
3. Search by name or SKU
4. See product images (if attached)

## 🧪 Run Tests
```bash
php artisan test
```

## 📝 API Testing with cURL

### Import CSV
```bash
curl -X POST http://localhost:8000/api/products/import \
  -F "file=@storage/app/mock_products.csv"
```

### Initialize Upload
```bash
curl -X POST http://localhost:8000/api/uploads/init \
  -H "Content-Type: application/json" \
  -d '{
    "filename": "test.jpg",
    "total_size": 1048576,
    "mime_type": "image/jpeg",
    "total_chunks": 10
  }'
```

### Get Products
```bash
curl http://localhost:8000/api/products
```

## 🔍 Troubleshooting

### "Target class does not exist" errors
```bash
composer dump-autoload
php artisan optimize:clear
```

### Database errors
```bash
# Reset database
php artisan migrate:fresh

# Re-generate mock data
php artisan mock:generate
```

### Frontend build errors
```bash
# Clear node modules and reinstall
rm -rf node_modules package-lock.json
npm install
npm run dev
```

### Queue not processing
Make sure queue worker is running:
```bash
php artisan queue:work
```

## 📊 Performance Testing

### Import Large CSV
```bash
# Generate 50,000 rows
php artisan mock:generate --rows=50000

# Time the import (use the generated CSV file via UI or API)
```

### Concurrent Uploads
Open multiple browser tabs and upload images simultaneously to test concurrency safety.

## 🎯 Next Steps

1. **Customize**: Modify the Product model to add more fields
2. **Extend**: Add more entities (Users with email as unique key)
3. **Enhance**: Add product edit/delete functionality
4. **Deploy**: Set up production environment

## 📚 More Information

See [README.md](README.md) for complete documentation.

## 💡 Tips

- **CSV Format**: First row must be headers (sku,name,price,category,stock,description)
- **Image Formats**: Supports PNG, JPG, JPEG, GIF, WebP
- **Resume Upload**: If upload fails, it will automatically resume from last successful chunk
- **Idempotent**: Safe to re-import same CSV or re-attach same image
- **Concurrency**: Multiple imports/uploads can run simultaneously

## ⚡ Performance Notes

- CSV imports process ~1000 rows/second on average hardware
- Image uploads use 1MB chunks for optimal speed
- Variant generation is queued for background processing
- Database uses indexes on frequently queried columns

Enjoy building with Bulk Import System! 🎉
