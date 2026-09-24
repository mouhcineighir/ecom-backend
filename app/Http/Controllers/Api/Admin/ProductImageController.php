<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends Controller
{
    // Upload one or more images for a product
    public function store(Request $request, int $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);

        $validated = $request->validate([
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'], // 4MB per file
        ]);

        $nextSortOrder = (int) $product->images()->max('sort_order');
        $created = [];

        foreach ($validated['images'] as $file) {
            $nextSortOrder++;

            $path = $file->store('products', 'public');

            $created[] = $product->images()->create([
                'image' => $path,
                'sort_order' => $nextSortOrder,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Images uploaded successfully.',
            'data' => $created,
        ], 201);
    }

    // Delete one image
    public function destroy(int $productId, int $imageId): JsonResponse
    {
        $image = ProductImage::where('product_id', $productId)->findOrFail($imageId);

        Storage::disk('public')->delete($image->image);

        $image->delete();

        return response()->json([
            'success' => true,
            'message' => 'Image deleted successfully.',
        ]);
    }
}