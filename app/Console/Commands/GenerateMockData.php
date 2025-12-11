<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Faker\Factory as Faker;

class GenerateMockData extends Command
{
    protected $signature = 'mock:generate {--rows=10000 : Number of CSV rows to generate} {--images=100 : Number of test images to generate}';
    protected $description = 'Generate mock CSV data and test images for bulk import testing';

    public function handle()
    {
        $faker = Faker::create();
        $rows = $this->option('rows');
        $imageCount = $this->option('images');

        $this->info("Generating {$rows} CSV rows...");
        $this->generateCSV($faker, $rows, $imageCount);

        $this->info("Generating {$imageCount} test images...");
        $this->generateTestImages($imageCount);

        $this->info('Mock data generation completed!');
    }

    private function generateCSV($faker, int $rows, int $imageCount): void
    {
        $categories = ['Electronics', 'Clothing', 'Home & Garden', 'Sports', 'Books', 'Toys', 'Food & Beverage'];

        $filePath = storage_path('app/mock_products.csv');
        $file = fopen($filePath, 'w');

        // Write header
        fputcsv($file, ['sku', 'name', 'price', 'category', 'stock', 'description', 'image']);

        $progressBar = $this->output->createProgressBar($rows);
        $progressBar->start();

        for ($i = 1; $i <= $rows; $i++) {
            $sku = 'SKU-' . str_pad($i, 6, '0', STR_PAD_LEFT);
            $category = $faker->randomElement($categories);

            // Assign an image to the first N products (where N is imageCount)
            // We cycle through images if rows > imageCount, or just leave blank?
            // Let's assign images to the first $imageCount rows to match the generated images
            $image = ($i <= $imageCount)
                ? 'test_image_' . str_pad($i, 4, '0', STR_PAD_LEFT) . '.jpg'
                : '';

            fputcsv($file, [
                $sku,
                $faker->words(3, true) . ' ' . $category,
                $faker->randomFloat(2, 5, 999),
                $category,
                $faker->numberBetween(0, 1000),
                $faker->sentence(10),
                $image
            ]);

            if ($i % 1000 === 0) {
                $progressBar->advance(1000);
            }
        }

        $progressBar->finish();
        $this->newLine();

        fclose($file);

        $this->info("CSV file created at: {$filePath}");
        $fileSize = filesize($filePath);
        $this->info("File size: " . round($fileSize / 1024 / 1024, 2) . " MB");
    }

    private function generateTestImages(int $count): void
    {
        $imagesDir = storage_path('app/test_images');

        if (!is_dir($imagesDir)) {
            mkdir($imagesDir, 0755, true);
        }

        $progressBar = $this->output->createProgressBar($count);
        $progressBar->start();

        for ($i = 1; $i <= $count; $i++) {
            $width = rand(800, 2000);
            $height = rand(600, 1500);

            // Create a simple test image
            $image = imagecreatetruecolor($width, $height);

            // Random background color
            $bgColor = imagecolorallocate($image, rand(50, 200), rand(50, 200), rand(50, 200));
            imagefill($image, 0, 0, $bgColor);

            // Add some text
            $textColor = imagecolorallocate($image, 255, 255, 255);
            $text = "Test Image #{$i}";
            imagestring($image, 5, $width / 2 - 50, $height / 2, $text, $textColor);

            // Add some random shapes for variety
            for ($j = 0; $j < rand(5, 15); $j++) {
                $shapeColor = imagecolorallocate($image, rand(0, 255), rand(0, 255), rand(0, 255));
                $x1 = rand(0, $width);
                $y1 = rand(0, $height);
                $x2 = rand(0, $width);
                $y2 = rand(0, $height);

                if (rand(0, 1)) {
                    imagerectangle($image, $x1, $y1, $x2, $y2, $shapeColor);
                } else {
                    imageellipse($image, $x1, $y1, abs($x2 - $x1), abs($y2 - $y1), $shapeColor);
                }
            }

            $filename = $imagesDir . '/test_image_' . str_pad($i, 4, '0', STR_PAD_LEFT) . '.jpg';
            imagejpeg($image, $filename, 85);
            imagedestroy($image);

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        $this->info("Test images created in: {$imagesDir}");

        // Calculate total size
        $totalSize = 0;
        $files = glob($imagesDir . '/*.jpg');
        foreach ($files as $file) {
            $totalSize += filesize($file);
        }

        $this->info("Total images size: " . round($totalSize / 1024 / 1024, 2) . " MB");
    }
}
