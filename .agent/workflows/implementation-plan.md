---
description: Bulk Import + Chunked Image Upload Implementation Plan
---

# Implementation Plan: Bulk Import + Chunked Drag-and-Drop Image Upload

## Domain Selection
**Selected Domain: Products (unique by SKU)**

## Architecture Overview

### Backend (Laravel)
1. **Database Schema**
   - `products` table (id, sku, name, description, price, category, stock, primary_image_id, timestamps)
   - `uploads` table (id, uuid, filename, original_name, size, mime_type, checksum, status, total_chunks, uploaded_chunks, metadata, timestamps)
   - `images` table (id, upload_id, entity_type, entity_id, variant, path, width, height, size, timestamps)
   - `import_logs` table (id, filename, status, total_rows, imported, updated, invalid, duplicates, errors, timestamps)

2. **API Endpoints**
   - POST `/api/products/import` - Bulk CSV import
   - POST `/api/uploads/init` - Initialize chunked upload
   - POST `/api/uploads/{uuid}/chunk` - Upload chunk
   - POST `/api/uploads/{uuid}/complete` - Complete upload
   - GET `/api/uploads/{uuid}/status` - Get upload status
   - POST `/api/products/{id}/attach-image` - Attach image to product
   - GET `/api/products` - List products with pagination
   - GET `/api/import-logs` - Get import history

3. **Services**
   - `ProductImportService` - Handle CSV parsing and upsert logic
   - `ChunkedUploadService` - Manage chunked uploads
   - `ImageProcessingService` - Generate image variants (256px, 512px, 1024px)
   - `ChecksumService` - Validate file integrity

4. **Jobs (Queued)**
   - `ProcessProductImport` - Process CSV import in background
   - `GenerateImageVariants` - Create image variants
   - `CleanupIncompleteUploads` - Remove abandoned uploads

5. **Validation Rules**
   - CSV: sku (required, unique), name (required), price (required, numeric), category, description, stock (numeric)
   - Images: max size, allowed mime types (jpg, png, webp)
   - Chunks: checksum validation, sequence validation

### Frontend (React + TypeScript)
1. **Pages**
   - Import page with CSV upload and image drag-and-drop
   - Products listing with filters and pagination
   - Import history/logs

2. **Components**
   - `CSVImporter` - CSV file upload with validation
   - `ChunkedImageUploader` - Drag-and-drop with chunked upload
   - `UploadProgress` - Real-time progress tracking
   - `ResultSummary` - Display import results
   - `ProductGrid` - Display imported products

3. **State Management**
   - Upload queue management
   - Progress tracking
   - Error handling
   - Resume capability

## Key Features Implementation

### 1. CSV Import with Upsert
- Parse CSV rows
- Validate required columns (sku, name, price)
- Upsert by SKU (unique key)
- Track: total, imported, updated, invalid, duplicates
- Invalid rows don't stop import
- Concurrency-safe with database transactions

### 2. Chunked Image Upload
- Split files into 1MB chunks
- Calculate SHA-256 checksum for each chunk
- Support resume on network failure
- Validate checksums before assembly
- Re-sending chunks = idempotent (no corruption)

### 3. Image Variant Generation
- Generate 3 variants: 256px, 512px, 1024px (max width/height)
- Maintain aspect ratio
- Use Intervention Image library
- Store metadata (dimensions, size)
- Queue processing for performance

### 4. Image Linking
- Link one primary image per product
- Re-attaching same upload = no-op
- Update product.primary_image_id
- Cascade delete handling

### 5. Concurrency Safety
- Database transactions for upserts
- Pessimistic locking where needed
- Atomic chunk upload tracking
- Unique constraints on SKU and upload UUID

## Testing Strategy
1. **Unit Tests**
   - ProductImportService::upsert validation
   - ChecksumService validation
   - ImageProcessingService variant generation
   
2. **Feature Tests** (Optional)
   - CSV import flow
   - Chunked upload flow
   - Image attachment flow

## Mock Data
- Generate 10,000+ product CSV rows
- Create script to generate test images
- Sample data for testing edge cases

## Performance Considerations
- Queue jobs for heavy processing
- Chunk size optimization (1MB default)
- Database indexing on frequently queried columns
- Lazy loading for product images
- Frontend debouncing and throttling
