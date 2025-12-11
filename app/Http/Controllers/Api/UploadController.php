<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChunkedUploadService;
use App\Services\ImageProcessingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class UploadController extends Controller
{
    public function __construct(
        private ChunkedUploadService $uploadService,
        private ImageProcessingService $imageService
    ) {
    }

    public function init(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'filename' => 'required|string',
            'total_size' => 'required|integer|min:1',
            'mime_type' => 'required|string',
            'total_chunks' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $upload = $this->uploadService->initializeUpload(
                $request->input('filename'),
                $request->input('total_size'),
                $request->input('mime_type'),
                $request->input('total_chunks')
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'uuid' => $upload->uuid,
                    'upload_id' => $upload->id,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function uploadChunk(Request $request, string $uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'chunk_index' => 'required|integer|min:0',
            'chunk_data' => 'required|string',
            'checksum' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Decode base64 chunk data
            $chunkData = base64_decode($request->input('chunk_data'));

            $result = $this->uploadService->uploadChunk(
                $uuid,
                $request->input('chunk_index'),
                $chunkData,
                $request->input('checksum')
            );

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function complete(Request $request, string $uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'checksum' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->uploadService->completeUpload(
                $uuid,
                $request->input('checksum')
            );

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            // Process image variants
            $images = collect($this->imageService->processUpload($result['upload']));

            // Link any pending products
            $linkedCount = $this->imageService->linkPendingProducts($result['upload']);

            return response()->json([
                'success' => true,
                'data' => [
                    'upload_id' => $result['upload']->id,
                    'uuid' => $uuid,
                    'variants' => $images->map(fn($img) => [
                        'id' => $img->id,
                        'variant' => $img->variant,
                        'width' => $img->width,
                        'height' => $img->height,
                        'size' => $img->size,
                        'url' => $img->url,
                    ]),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function status(string $uuid): JsonResponse
    {
        try {
            $upload = $this->uploadService->getUploadStatus($uuid);

            if (!$upload) {
                return response()->json([
                    'success' => false,
                    'message' => 'Upload not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'uuid' => $upload->uuid,
                    'status' => $upload->status,
                    'uploaded_chunks' => $upload->uploaded_chunks,
                    'total_chunks' => $upload->total_chunks,
                    'progress' => $upload->total_chunks > 0
                        ? round(($upload->uploaded_chunks / $upload->total_chunks) * 100, 2)
                        : 0,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function resume(string $uuid): JsonResponse
    {
        try {
            $resumeData = $this->uploadService->resumeUpload($uuid);

            if (!$resumeData['exists']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Upload not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $resumeData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
