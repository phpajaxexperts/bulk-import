<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductImportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ProductImportController extends Controller
{
    public function __construct(
        private ProductImportService $importService
    ) {
    }

    public function import(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt|max:102400', // Max 100MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $filePath = null;

        try {
            $file = $request->file('file');
            // Store in public disk (storage/app/public)
            $filePath = $file->store('temp', 'public');
            $filename = $file->getClientOriginalName();
            $fullPath = storage_path('app/public/' . $filePath);

            // Debug: Log the file path
            \Log::info('CSV Upload Debug', [
                'filePath' => $filePath,
                'fullPath' => $fullPath,
                'fileExists' => file_exists($fullPath),
                'isReadable' => is_readable($fullPath),
            ]);

            // Verify file exists before processing
            if (!file_exists($fullPath)) {
                throw new \Exception('Uploaded file not found at: ' . $fullPath);
            }

            // Process import (file is still on disk)
            $importLog = $this->importService->import(
                $fullPath,
                $filename
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'import_log_id' => $importLog->id,
                    'status' => $importLog->status,
                    'statistics' => [
                        'total_rows' => $importLog->total_rows,
                        'imported' => $importLog->imported,
                        'updated' => $importLog->updated,
                        'invalid' => $importLog->invalid,
                        'duplicates' => $importLog->duplicates,
                    ],
                    'errors' => $importLog->errors,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('CSV Import Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        } finally {
            // Clean up temp file in finally block (always executes)
            if ($filePath && Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);
            }
        }
    }
}
