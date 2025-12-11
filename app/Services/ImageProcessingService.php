<?php

namespace App\Services;

use App\Models\Upload;
use App\Models\Image;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageProcessingService
{
    private const VARIANTS = [
        '256' => 256,
        '512' => 512,
        '1024' => 1024,
    ];

    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    public function processUpload(Upload $upload): array
    {
        if (!$upload->isComplete()) {
            throw new \Exception('Upload is not complete');
        }

        $metadata = $upload->metadata ?? [];
        $sourcePath = $metadata['path'] ?? null;

        if (!$sourcePath || !Storage::exists($sourcePath)) {
            throw new \Exception('Source file not found');
        }

        $images = [];

        try {
            // Store original image
            $originalImage = $this->storeOriginal($upload, $sourcePath);
            $images[] = $originalImage;

            // Generate variants
            foreach (self::VARIANTS as $variant => $maxSize) {
                $variantImage = $this->generateVariant($upload, $sourcePath, $variant, $maxSize);
                $images[] = $variantImage;
            }

            return $images;
        } catch (\Exception $e) {
            Log::error('Image processing failed: ' . $e->getMessage());

            // Clean up any created images
            foreach ($images as $image) {
                if (Storage::exists($image->path)) {
                    Storage::delete($image->path);
                }
                $image->delete();
            }

            throw $e;
        }
    }

    private function storeOriginal(Upload $upload, string $sourcePath): Image
    {
        $fileContent = Storage::get($sourcePath);
        $image = $this->manager->read($fileContent);

        $dimensions = [
            'width' => $image->width(),
            'height' => $image->height(),
        ];

        // Copy original to public storage so it's accessible
        $publicPath = "images/{$upload->filename}";
        Storage::disk('public')->put($publicPath, $fileContent);

        return Image::create([
            'upload_id' => $upload->id,
            'variant' => 'original',
            'path' => $publicPath,
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'size' => Storage::size($sourcePath),
        ]);
    }

    private function generateVariant(
        Upload $upload,
        string $sourcePath,
        string $variant,
        int $maxSize
    ): Image {
        $fileContent = Storage::get($sourcePath);
        $image = $this->manager->read($fileContent);

        // Calculate new dimensions while maintaining aspect ratio
        $originalWidth = $image->width();
        $originalHeight = $image->height();

        $newDimensions = $this->calculateDimensions(
            $originalWidth,
            $originalHeight,
            $maxSize
        );

        // Resize image
        $image->scale(
            width: $newDimensions['width'],
            height: $newDimensions['height']
        );

        // Generate variant path
        $extension = pathinfo($upload->filename, PATHINFO_EXTENSION);
        $basename = pathinfo($upload->filename, PATHINFO_FILENAME);
        $variantPath = "images/{$basename}_{$variant}.{$extension}";

        // Save variant
        $encodedImage = $image->encode();
        Storage::disk('public')->put($variantPath, (string) $encodedImage);

        // Create image record
        return Image::create([
            'upload_id' => $upload->id,
            'variant' => $variant,
            'path' => $variantPath,
            'width' => $newDimensions['width'],
            'height' => $newDimensions['height'],
            'size' => Storage::disk('public')->size($variantPath),
        ]);
    }

    private function calculateDimensions(int $width, int $height, int $maxSize): array
    {
        // If both dimensions are smaller than max, keep original
        if ($width <= $maxSize && $height <= $maxSize) {
            return ['width' => $width, 'height' => $height];
        }

        $aspectRatio = $width / $height;

        if ($width > $height) {
            // Landscape
            $newWidth = $maxSize;
            $newHeight = (int) round($maxSize / $aspectRatio);
        } else {
            // Portrait or square
            $newHeight = $maxSize;
            $newWidth = (int) round($maxSize * $aspectRatio);
        }

        return ['width' => $newWidth, 'height' => $newHeight];
    }

    public function attachToProduct(int $productId, int $uploadId): bool
    {
        $product = Product::findOrFail($productId);
        $upload = Upload::findOrFail($uploadId);

        if (!$upload->isComplete()) {
            throw new \Exception('Upload is not complete');
        }

        // Check if already attached (idempotent)
        $existingAttachment = Image::where('upload_id', $uploadId)
            ->where('entity_type', Product::class)
            ->where('entity_id', $productId)
            ->exists();

        if ($existingAttachment) {
            // Already attached, this is a no-op
            return true;
        }

        // Process upload if not already processed
        $images = $upload->images()->count() === 0
            ? $this->processUpload($upload)
            : $upload->images;

        // Attach images to product
        foreach ($images as $image) {
            $image->update([
                'entity_type' => Product::class,
                'entity_id' => $productId,
            ]);
        }

        // Set primary image (use the 512px variant or original if not available)
        $primaryImage = $upload->images()
            ->where('variant', '512')
            ->first() ?? $upload->images()->where('variant', 'original')->first();

        if ($primaryImage) {
            $product->update(['primary_image_id' => $primaryImage->id]);
        }

        return true;
    }
    public function linkPendingProducts(Upload $upload): int
    {
        // Find products waiting for this image
        $products = Product::where('pending_image_name', $upload->original_name)->get();
        $count = 0;

        foreach ($products as $product) {
            try {
                $this->attachToProduct($product->id, $upload->id);

                // Clear the pending flag
                $product->update(['pending_image_name' => null]);
                $count++;
            } catch (\Exception $e) {
                Log::error("Failed to link pending product {$product->sku} to upload {$upload->id}: " . $e->getMessage());
            }
        }

        return $count;
    }
}
