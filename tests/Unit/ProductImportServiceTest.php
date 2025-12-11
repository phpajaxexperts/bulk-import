<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ProductImportService;
use App\Models\Product;
use App\Models\ImportLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductImportService $service;
    private $imageService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->imageService = $this->createMock(\App\Services\ImageProcessingService::class);
        $this->service = new ProductImportService($this->imageService);
    }

    /** @test */
    public function it_can_upsert_new_product()
    {
        $data = [
            'sku' => 'TEST-001',
            'name' => 'Test Product',
            'price' => '99.99',
            'category' => 'Electronics',
            'description' => 'A test product',
            'stock' => '10',
        ];

        $this->service->upsertProduct($data);

        $this->assertDatabaseHas('products', [
            'sku' => 'TEST-001',
            'name' => 'Test Product',
            'price' => 99.99,
            'category' => 'Electronics',
            'stock' => 10,
        ]);

        $stats = $this->service->getStats();
        $this->assertEquals(1, $stats['imported']);
        $this->assertEquals(0, $stats['updated']);
    }

    /** @test */
    public function it_can_update_existing_product()
    {
        // Create initial product
        Product::create([
            'sku' => 'TEST-001',
            'name' => 'Old Product Name',
            'price' => 50.00,
            'category' => 'Old Category',
            'stock' => 5,
        ]);

        // Upsert with updated data
        $data = [
            'sku' => 'TEST-001',
            'name' => 'Updated Product Name',
            'price' => '99.99',
            'category' => 'New Category',
            'description' => 'Updated description',
            'stock' => '20',
        ];

        $this->service->upsertProduct($data);

        // Verify update
        $this->assertDatabaseHas('products', [
            'sku' => 'TEST-001',
            'name' => 'Updated Product Name',
            'price' => 99.99,
            'category' => 'New Category',
            'stock' => 20,
        ]);

        // Should only have one product with this SKU
        $this->assertEquals(1, Product::where('sku', 'TEST-001')->count());

        $stats = $this->service->getStats();
        $this->assertEquals(0, $stats['imported']);
        $this->assertEquals(1, $stats['updated']);
    }

    /** @test */
    public function it_processes_csv_import_correctly()
    {
        // Create a test CSV file
        $csvContent = "sku,name,price,category,stock,description\n";
        $csvContent .= "PROD-001,Product One,10.50,Category A,100,Description 1\n";
        $csvContent .= "PROD-002,Product Two,20.00,Category B,50,Description 2\n";
        $csvContent .= "PROD-003,Product Three,30.99,Category A,75,Description 3\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_');
        file_put_contents($tempFile, $csvContent);

        // Import
        $importLog = $this->service->import($tempFile, 'test-import.csv');

        // Verify import log
        $this->assertEquals('completed', $importLog->status);
        $this->assertEquals(3, $importLog->total_rows);
        $this->assertEquals(3, $importLog->imported);
        $this->assertEquals(0, $importLog->updated);
        $this->assertEquals(0, $importLog->invalid);
        $this->assertEquals(0, $importLog->duplicates);

        // Verify products were created
        $this->assertEquals(3, Product::count());
        $this->assertDatabaseHas('products', ['sku' => 'PROD-001', 'name' => 'Product One']);
        $this->assertDatabaseHas('products', ['sku' => 'PROD-002', 'name' => 'Product Two']);
        $this->assertDatabaseHas('products', ['sku' => 'PROD-003', 'name' => 'Product Three']);

        // Clean up
        unlink($tempFile);
    }

    /** @test */
    public function it_handles_invalid_rows()
    {
        // Create a test CSV with invalid rows (missing required fields)
        $csvContent = "sku,name,price,category,stock,description\n";
        $csvContent .= "PROD-001,Product One,10.50,Category A,100,Description 1\n";
        $csvContent .= ",Missing SKU,20.00,Category B,50,Description 2\n"; // Missing SKU
        $csvContent .= "PROD-003,,30.99,Category A,75,Description 3\n"; // Missing name
        $csvContent .= "PROD-004,Product Four,,Category A,75,Description 4\n"; // Missing price

        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_');
        file_put_contents($tempFile, $csvContent);

        // Import
        $importLog = $this->service->import($tempFile, 'test-import-invalid.csv');

        // Verify
        $this->assertEquals('completed', $importLog->status);
        $this->assertEquals(4, $importLog->total_rows);
        $this->assertEquals(1, $importLog->imported); // Only one valid row
        $this->assertEquals(3, $importLog->invalid); // Three invalid rows
        $this->assertNotEmpty($importLog->errors);

        // Clean up
        unlink($tempFile);
    }

    /** @test */
    public function it_handles_duplicate_skus_in_same_import()
    {
        // Create a test CSV with duplicate SKUs
        $csvContent = "sku,name,price,category,stock,description\n";
        $csvContent .= "PROD-001,Product One,10.50,Category A,100,Description 1\n";
        $csvContent .= "PROD-001,Duplicate Product,20.00,Category B,50,Description 2\n";
        $csvContent .= "PROD-002,Product Two,30.99,Category A,75,Description 3\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_');
        file_put_contents($tempFile, $csvContent);

        // Import
        $importLog = $this->service->import($tempFile, 'test-import-duplicates.csv');

        // Verify
        $this->assertEquals('completed', $importLog->status);
        $this->assertEquals(3, $importLog->total_rows);
        $this->assertEquals(2, $importLog->imported); // Two unique products
        $this->assertEquals(1, $importLog->duplicates); // One duplicate

        // Clean up
        unlink($tempFile);
    }

    /** @test */
    public function it_updates_existing_products_on_re_import()
    {
        // Create initial products
        Product::create([
            'sku' => 'PROD-001',
            'name' => 'Old Name',
            'price' => 10.00,
            'category' => 'Old Category',
            'stock' => 50,
        ]);

        // Create a CSV with updated data
        $csvContent = "sku,name,price,category,stock,description\n";
        $csvContent .= "PROD-001,Updated Name,15.50,New Category,100,Updated Description\n";
        $csvContent .= "PROD-002,New Product,25.00,Category B,75,New Product Description\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_');
        file_put_contents($tempFile, $csvContent);

        // Import
        $importLog = $this->service->import($tempFile, 'test-import-update.csv');

        // Verify
        $this->assertEquals('completed', $importLog->status);
        $this->assertEquals(2, $importLog->total_rows);
        $this->assertEquals(1, $importLog->imported); // One new product
        $this->assertEquals(1, $importLog->updated); // One updated product

        // Verify the updated product
        $this->assertDatabaseHas('products', [
            'sku' => 'PROD-001',
            'name' => 'Updated Name',
            'price' => 15.50,
        ]);

        // Verify the new product
        $this->assertDatabaseHas('products', [
            'sku' => 'PROD-002',
            'name' => 'New Product',
        ]);

        // Clean up
        unlink($tempFile);
    }
}
