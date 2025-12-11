<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ImportLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ProductImportService
{
    private array $stats = [
        'total_rows' => 0,
        'imported' => 0,
        'updated' => 0,
        'invalid' => 0,
        'duplicates' => 0,
    ];

    private array $errors = [];
    private array $seenSkus = [];

    public function import(string $filePath, string $filename): ImportLog
    {
        $importLog = ImportLog::create([
            'filename' => $filename,
            'status' => 'processing',
        ]);

        try {
            $this->processCSV($filePath);

            $importLog->update([
                'status' => 'completed',
                'total_rows' => $this->stats['total_rows'],
                'imported' => $this->stats['imported'],
                'updated' => $this->stats['updated'],
                'invalid' => $this->stats['invalid'],
                'duplicates' => $this->stats['duplicates'],
                'errors' => $this->errors,
            ]);
        } catch (\Exception $e) {
            Log::error('Import failed: ' . $e->getMessage());

            $importLog->update([
                'status' => 'failed',
                'errors' => ['general' => $e->getMessage()],
            ]);
        }

        return $importLog;
    }

    private function processCSV(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new \Exception('CSV file not found');
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \Exception('Could not open CSV file');
        }

        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            throw new \Exception('CSV file is empty');
        }

        // Normalize headers (trim and lowercase)
        $headers = array_map(fn($h) => strtolower(trim($h)), $headers);

        $rowNumber = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            $this->stats['total_rows']++;

            // Convert row to associative array
            $data = array_combine($headers, $row);

            // Process the row
            $this->processRow($data, $rowNumber);

            // Prevent memory issues with large files
            if ($rowNumber % 1000 === 0) {
                gc_collect_cycles();
            }
        }

        fclose($handle);
    }

    private function processRow(array $data, int $rowNumber): void
    {
        // Validate required columns
        $validationResult = $this->validateRow($data, $rowNumber);

        if (!$validationResult['valid']) {
            $this->stats['invalid']++;
            $this->errors[] = [
                'row' => $rowNumber,
                'errors' => $validationResult['errors'],
            ];
            return;
        }

        $sku = trim($data['sku']);

        // Check for duplicates within the current import
        if (isset($this->seenSkus[$sku])) {
            $this->stats['duplicates']++;
            return;
        }

        $this->seenSkus[$sku] = true;

        // Upsert the product
        $this->upsertProduct($data);
    }

    private function validateRow(array $data, int $rowNumber): array
    {
        $requiredColumns = ['sku', 'name', 'price'];
        $errors = [];

        // Check for missing required columns
        foreach ($requiredColumns as $column) {
            if (!isset($data[$column]) || trim($data[$column]) === '') {
                $errors[] = "Missing required column: {$column}";
            }
        }

        if (!empty($errors)) {
            return ['valid' => false, 'errors' => $errors];
        }

        // Validate data types
        $validator = Validator::make($data, [
            'sku' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'category' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return [
                'valid' => false,
                'errors' => $validator->errors()->all(),
            ];
        }

        return ['valid' => true, 'errors' => []];
    }

    public function __construct(
        private ImageProcessingService $imageService
    ) {
    }

    public function upsertProduct(array $data): void
    {
        DB::transaction(function () use ($data) {
            $sku = trim($data['sku']);

            $productData = [
                'name' => trim($data['name']),
                'price' => $data['price'],
                'description' => $data['description'] ?? null,
                'category' => $data['category'] ?? null,
                'stock' => $data['stock'] ?? 0,
                'pending_image_name' => !empty($data['image']) ? trim($data['image']) : null,
            ];

            // Check if product exists
            $existingProduct = Product::where('sku', $sku)->lockForUpdate()->first();
            $product = null;

            if ($existingProduct) {
                // Update existing product
                $existingProduct->update($productData);
                $product = $existingProduct;
                $this->stats['updated']++;
            } else {
                // Create new product
                $product = Product::create(array_merge(['sku' => $sku], $productData));
                $this->stats['imported']++;
            }

            // Handle image linking if 'image' column exists
            if (!empty($data['image']) && $product) {
                $imageFilename = trim($data['image']);

                // Find upload by original filename
                // We search for exact match first
                $upload = \App\Models\Upload::where('original_name', $imageFilename)
                    ->where('status', 'completed')
                    ->first();

                if ($upload) {
                    try {
                        $this->imageService->attachToProduct($product->id, $upload->id);
                        // Clear pending flag since we successfully attached
                        $product->update(['pending_image_name' => null]);
                    } catch (\Exception $e) {
                        Log::warning("Failed to attach image {$imageFilename} to product {$sku}: " . $e->getMessage());
                    }
                }
            }
        });
    }

    public function getStats(): array
    {
        return $this->stats;
    }
}
