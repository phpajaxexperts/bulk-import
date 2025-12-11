<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ImportLog;
use App\Services\ImageProcessingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    public function __construct(
        private ImageProcessingService $imageService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 20);
        $search = $request->input('search');
        $category = $request->input('category');

        $query = Product::with('primaryImage');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($category) {
            $query->where('category', $category);
        }

        $products = $query->select([
            'id',
            'sku',
            'name',
            'description',
            'price',
            'category',
            'stock',
            'primary_image_id',
            'created_at'
        ])
            ->with([
                'primaryImage' => function ($query) {
                    $query->select('id', 'upload_id', 'variant', 'path');
                }
            ])
            ->orderBy('id', 'asc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $products->items(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $product = Product::with(['primaryImage', 'images'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $product,
        ]);
    }

    public function attachImage(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'upload_id' => 'required|integer|exists:uploads,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $this->imageService->attachToProduct(
                $id,
                $request->input('upload_id')
            );

            $product = Product::with(['primaryImage', 'images'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $product,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function importLogs(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 20);

        $logs = ImportLog::orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }
}
