# Bulk Import System with Chunked Image Upload

A comprehensive Laravel + React application for bulk CSV import and chunked drag-and-drop image uploads with variant generation.

## Features

### CSV Import
- ✅ Bulk import products from CSV files (10,000+ rows supported)
- ✅ Upsert logic by SKU (create new or update existing)
- ✅ Detailed import statistics (total, imported, updated, invalid, duplicates)
- ✅ Invalid row handling (skips invalid rows without stopping import)
- ✅ Duplicate detection within same import
- ✅ Concurrency-safe with database transactions

### Chunked Image Upload
- ✅ Drag-and-drop interface
- ✅ Chunked upload (1MB chunks)
- ✅ Resume capability on network failure
- ✅ SHA-256 checksum validation per chunk
- ✅ Final file checksum verification
- ✅ Automatic variant generation (256px, 512px, 1024px)
- ✅ Aspect ratio preservation
- ✅ Retry logic for failed chunks
- ✅ Idempotent operations

### Image Processing
- ✅ Multi-size variant generation (256px, 512px, 1024px, original)
- ✅ Aspect ratio maintenance
- ✅ Automatic primary image linking to products
- ✅ Re-attach protection (idempotent)

## Tech Stack

**Backend:**
- Laravel 12
- PHP 8.2
- MySQL/SQLite
- Intervention Image v3
- Queue system for background processing

**Frontend:**
- React 18
- TypeScript
- Inertia.js
- TailwindCSS
- shadcn/ui components
- react-dropzone
- crypto-js for checksum calculation

## Installation

### Prerequisites
- PHP 8.2+
- Composer
- Node.js 18+
- MySQL or SQLite

### Setup Steps

1. **Clone and install dependencies:**
```bash
cd /Applications/XAMPP/xamppfiles/htdocs/projects/hipster/bulkimport
composer install
npm install
```

2. **Environment configuration:**
```bash
cp .env.example .env
php artisan key:generate
```

3. **Configure database in `.env`:**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bulkimport
DB_USERNAME=root
DB_PASSWORD=
```

4. **Run migrations:**
```bash
php artisan migrate
```

5. **Create storage symlink:**
```bash
php artisan storage:link
```

6. **Generate mock data (optional but recommended):**
```bash
# Generate 10,000 CSV rows and 100 test images
php artisan mock:generate

# Custom counts
php artisan mock:generate --rows=50000 --images=500
```

This will create:
- `storage/app/mock_products.csv` - CSV file with mock product data
- `storage/app/test_images/` - Directory with test images

## Running the Application

### Development (concurrent servers):
```bash
composer dev
```

This starts:
- Laravel development server (http://localhost:8000)
- Queue worker
- Vite dev server
- Logs tail

### Manually:
```bash
# Terminal 1: Laravel server
php artisan serve

# Terminal 2: Queue worker (for background jobs)
php artisan queue:work

# Terminal 3: Frontend build
npm run dev
```

## Usage

### 1. CSV Import

1. Navigate to `/import` in your browser
2. Click "CSV Import" tab
3. Select or drag CSV file
4. Click "Import CSV"
5. View import results with statistics

**CSV Format:**
```csv
sku,name,price,category,stock,description
SKU-001,Product Name,99.99,Electronics,100,Product description
```

**Required columns:** `sku`, `name`, `price`
**Optional columns:** `category`, `stock`, `description`

### 2. Image Upload

1. Navigate to `/import`
2. Click "Image Upload" tab
3. Drag and drop images or click to browse
4. Images are uploaded with chunking automatically
5. Variants are generated (256px, 512px, 1024px)

### 3. View Products

Navigate to `/products` to browse imported products with search and pagination.

## API Endpoints

### Product Import
```
POST /api/products/import
Content-Type: multipart/form-data
Body: file=<csv_file>
```

### Chunked Upload
```
POST /api/uploads/init
POST /api/uploads/{uuid}/chunk
POST /api/uploads/{uuid}/complete
GET  /api/uploads/{uuid}/status
GET  /api/uploads/{uuid}/resume
```

### Products
```
GET /api/products
GET /api/products/{id}
POST /api/products/{id}/attach-image
```

### Import Logs
```
GET /api/import-logs
```

## Testing

### Run Unit Tests
```bash
php artisan test --filter=ProductImportServiceTest
```

### Run All Tests
```bash
php artisan test
```

## Database Schema

### Products Table
- `id` - Primary key
- `sku` - Unique product identifier
- `name` - Product name
- `description` - Product description
- `price` - Product price (decimal)
- `category` - Product category
- `stock` - Stock quantity
- `primary_image_id` - Foreign key to images table

### Uploads Table
- `id` - Primary key
- `uuid` - Unique upload identifier
- `filename` - Generated filename
- `original_name` - Original filename
- `size` - File size in bytes
- `mime_type` - File MIME type
- `checksum` - SHA-256 checksum
- `status` - Upload status (pending, uploading, completed, failed)
- `total_chunks` - Total number of chunks
- `uploaded_chunks` - Number of uploaded chunks
- `metadata` - JSON metadata

### Images Table
- `id` - Primary key
- `upload_id` - Foreign key to uploads table
- `entity_type` - Polymorphic entity type (Product, etc.)
- `entity_id` - Polymorphic entity ID
- `variant` - Image variant (original, 256, 512, 1024)
- `path` - Storage path
- `width` - Image width
- `height` - Image height
- `size` - File size

### Import Logs Table
- `id` - Primary key
- `filename` - Imported filename
- `status` - Import status (pending, processing, completed, failed)
- `total_rows` - Total rows in CSV
- `imported` - Number of imported rows
- `updated` - Number of updated rows
- `invalid` - Number of invalid rows
- `duplicates` - Number of duplicate rows
- `errors` - JSON error details

## Key Implementation Details

### Upsert Logic
Products are upserted by SKU using Laravel's database transactions with pessimistic locking:
```php
Product::where('sku', $sku)->lockForUpdate()->first();
```

### Checksum Validation
- Each chunk is validated using SHA-256 checksum
- Final file checksum is verified before completion
- Mismatch blocks completion and triggers cleanup

### Resume Capability
Upload metadata tracks uploaded chunk indices, allowing resume after network failure:
```php
$metadata['uploaded_chunk_indices'] = [0, 1, 2, ...];
```

### Idempotent Operations
- Re-sending chunks doesn't corrupt data
- Re-attaching same upload to same entity is a no-op
- Duplicate SKUs within import are detected and skipped

### Concurrency Safety
- Database transactions for upserts
- Pessimistic locking on product updates
- Atomic chunk upload tracking

## Performance Considerations

- **Chunked uploads:** 1MB chunk size optimizes balance between requests and throughput
- **Queue processing:** Heavy image processing runs in background
- **Database indexing:** Applied on frequently queried columns (sku, category, uuid)
- **Memory management:** CSV processing uses streaming with periodic garbage collection
- **Lazy loading:** Product relationships loaded on demand

## File Structure

```
app/
├── Console/Commands/
│   └── GenerateMockData.php
├── Http/Controllers/Api/
│   ├── ProductController.php
│   ├── ProductImportController.php
│   └── UploadController.php
├── Models/
│   ├── Image.php
│   ├── ImportLog.php
│   ├── Product.php
│   └── Upload.php
└── Services/
    ├── ChunkedUploadService.php
    ├── ImageProcessingService.php
    └── ProductImportService.php

resources/js/
├── components/import/
│   ├── CSVImporter.tsx
│   ├── ImageUploader.tsx
│   └── ImportResults.tsx
└── pages/
    ├── import.tsx
    └── products.tsx

tests/Unit/
└── ProductImportServiceTest.php
```

## Troubleshooting

### Upload fails with "Checksum mismatch"
- Check network stability
- Verify file wasn't corrupted during transfer
- Try re-uploading

### CSV import shows all rows as invalid
- Verify CSV format matches requirements
- Check required columns are present: sku, name, price
- Ensure proper CSV encoding (UTF-8)

### Images not generating variants
- Ensure GD or Imagick PHP extension is installed
- Check storage permissions
- Review queue worker logs

### Queue jobs not processing
- Ensure queue worker is running: `php artisan queue:work`
- Check database connection for jobs table

## License

MIT License

## Support

For issues, questions, or contributions, please refer to the project repository.
