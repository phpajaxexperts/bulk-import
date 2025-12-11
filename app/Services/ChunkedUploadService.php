<?php

namespace App\Services;

use App\Models\Upload;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChunkedUploadService
{
    private const CHUNK_SIZE = 1048576; // 1MB in bytes

    public function initializeUpload(
        string $filename,
        int $totalSize,
        string $mimeType,
        int $totalChunks
    ): Upload {
        return Upload::create([
            'uuid' => (string) Str::uuid(),
            'filename' => Str::random(40) . '.' . pathinfo($filename, PATHINFO_EXTENSION),
            'original_name' => $filename,
            'size' => $totalSize,
            'mime_type' => $mimeType,
            'status' => 'pending',
            'total_chunks' => $totalChunks,
            'uploaded_chunks' => 0,
            'metadata' => [
                'chunk_size' => self::CHUNK_SIZE,
            ],
        ]);
    }

    public function uploadChunk(
        string $uuid,
        int $chunkIndex,
        string $chunkData,
        string $checksum
    ): array {
        $upload = Upload::where('uuid', $uuid)->firstOrFail();

        // Validate checksum
        $calculatedChecksum = hash('sha256', $chunkData);
        if ($calculatedChecksum !== $checksum) {
            return [
                'success' => false,
                'message' => 'Checksum mismatch',
            ];
        }

        // Store chunk temporarily
        $chunkPath = "chunks/{$uuid}/{$chunkIndex}";
        Storage::put($chunkPath, $chunkData);

        // Debug: Log chunk info
        Log::info('Chunk Uploaded', [
            'uuid' => $uuid,
            'index' => $chunkIndex,
            'size' => strlen($chunkData),
            'path' => $chunkPath,
            'real_path' => Storage::path($chunkPath),
            'checksum' => $checksum
        ]);

        // Update upload record atomically
        DB::transaction(function () use ($upload, $chunkIndex) {
            $metadata = $upload->metadata ?? [];
            $uploadedChunks = $metadata['uploaded_chunk_indices'] ?? [];

            // Idempotent: if chunk already uploaded, don't increment counter
            if (!in_array($chunkIndex, $uploadedChunks)) {
                $uploadedChunks[] = $chunkIndex;
                $metadata['uploaded_chunk_indices'] = $uploadedChunks;

                $upload->update([
                    'metadata' => $metadata,
                    'status' => 'uploading',
                ]);

                $upload->incrementUploadedChunks();
            }
        });

        $upload->refresh();

        return [
            'success' => true,
            'uploaded_chunks' => $upload->uploaded_chunks,
            'total_chunks' => $upload->total_chunks,
            'is_complete' => $upload->uploaded_chunks >= $upload->total_chunks,
        ];
    }

    public function completeUpload(string $uuid, string $finalChecksum): array
    {
        $upload = Upload::where('uuid', $uuid)->firstOrFail();

        if ($upload->uploaded_chunks < $upload->total_chunks) {
            return [
                'success' => false,
                'message' => 'Not all chunks uploaded',
            ];
        }

        try {
            // Assemble chunks
            $finalPath = $this->assembleChunks($upload);

            // Verify final checksum
            $fileContent = Storage::get($finalPath);
            $calculatedChecksum = hash('sha256', $fileContent);
            $fileSize = strlen($fileContent);

            Log::info('Final Checksum Verification', [
                'uuid' => $uuid,
                'expected' => $finalChecksum,
                'calculated' => $calculatedChecksum,
                'file_size' => $fileSize,
                'expected_size' => $upload->size,
                'match' => $calculatedChecksum === $finalChecksum,
                'final_path' => $finalPath,
                'real_final_path' => Storage::path($finalPath)
            ]);

            if ($calculatedChecksum !== $finalChecksum) {
                // Clean up
                Storage::delete($finalPath);
                $this->cleanupChunks($upload->uuid);

                return [
                    'success' => false,
                    'message' => "Final checksum mismatch. Server: {$calculatedChecksum}, Client: {$finalChecksum}",
                ];
            }

            // Update upload record
            $upload->update([
                'status' => 'completed',
                'checksum' => $finalChecksum,
                'metadata' => array_merge($upload->metadata ?? [], [
                    'path' => $finalPath,
                ]),
            ]);

            // Clean up chunks
            $this->cleanupChunks($upload->uuid);

            return [
                'success' => true,
                'upload' => $upload,
                'path' => $finalPath,
            ];
        } catch (\Exception $e) {
            Log::error('Upload completion failed: ' . $e->getMessage());

            $upload->update(['status' => 'failed']);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function assembleChunks(Upload $upload): string
    {
        // Use Storage facade for consistency
        $finalPath = "uploads/{$upload->filename}";

        // Ensure we start with an empty file
        Storage::put($finalPath, '');
        $absoluteFinalPath = Storage::path($finalPath);

        Log::info('Assembling chunks', [
            'uuid' => $upload->uuid,
            'total_chunks' => $upload->total_chunks,
            'final_path' => $finalPath,
            'absolute_path' => $absoluteFinalPath
        ]);

        // Open final file for writing in binary mode
        $finalFile = fopen($absoluteFinalPath, 'wb');
        if ($finalFile === false) {
            throw new \Exception('Could not open final file for writing');
        }

        try {
            // Append each chunk in order
            for ($i = 0; $i < $upload->total_chunks; $i++) {
                $chunkPath = "chunks/{$upload->uuid}/{$i}";

                if (!Storage::exists($chunkPath)) {
                    throw new \Exception("Chunk {$i} not found");
                }

                $chunkContent = Storage::get($chunkPath);

                Log::info('Appending chunk', [
                    'index' => $i,
                    'size' => strlen($chunkContent)
                ]);

                fwrite($finalFile, $chunkContent);
            }
        } finally {
            fclose($finalFile);
        }

        return $finalPath;
    }

    private function cleanupChunks(string $uuid): void
    {
        $chunkDirectory = "chunks/{$uuid}";

        if (Storage::exists($chunkDirectory)) {
            Storage::deleteDirectory($chunkDirectory);
        }
    }

    public function getUploadStatus(string $uuid): ?Upload
    {
        return Upload::where('uuid', $uuid)->first();
    }

    public function resumeUpload(string $uuid): array
    {
        $upload = Upload::where('uuid', $uuid)->first();

        if (!$upload) {
            return [
                'exists' => false,
            ];
        }

        $metadata = $upload->metadata ?? [];
        $uploadedChunkIndices = $metadata['uploaded_chunk_indices'] ?? [];

        return [
            'exists' => true,
            'uploaded_chunks' => $upload->uploaded_chunks,
            'total_chunks' => $upload->total_chunks,
            'uploaded_chunk_indices' => $uploadedChunkIndices,
            'status' => $upload->status,
        ];
    }
}
