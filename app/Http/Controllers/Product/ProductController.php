<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductHasImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Exception;

class ProductController extends Controller
{
    /**
     * Common list of all customizable relations.
     */
    private array $customRelations = [
        'skin_tones', 'hairs', 'noses', 'eyes', 'mouths',
        'dresses', 'crowns', 'base_cards', 'beards',
        'trading_fronts', 'trading_backs'
    ];

    /**
     * Display a listing of products with pagination.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $products = Product::with(array_merge(['category', 'images'], $this->customRelations))
                ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
                ->when($request->type, fn($q) => $q->where('type', $request->type))
                ->when(!is_null($request->status), fn($q) => $q->where('status', $request->status === 'true'))
                ->when($request->search, fn($q) => $q->where('name', 'LIKE', "%{$request->search}%"))
                ->latest()
                ->paginate($request->get('per_page', 15));

            $products->getCollection()->transform(function ($p) {
                $p->image = $p->image ? 'storage/'.$p->image : null;

                $p->gallery_images = $p->images->map(fn($img) => [
                    'id' => $img->id,
                    'url' => $img->image ? 'storage/'.$img->image : null,
                    'alt' => $img->alt ?? null,
                ])->toArray();

                $customizations = [];
                foreach ($this->customRelations as $relation) {
                    $customizations[$relation] = $p->{$relation}?->map(fn($item) => [
                        'id' => $item->id,
                        'name' => $item->name,
                        'image' => $item->image ? 'storage/'.$item->image : null,
                    ])->toArray() ?? [];
                }
                $p->customizations = $customizations;

                return $p;
            });

            return $this->successResponse('Products fetched successfully', $products);
        } catch (Exception $e) {
            \Log::error('Product index failed: ' . $e->getMessage());
            return $this->errorResponse('Failed to fetch products.');
        }
    }

    /**
     * Store a newly created product.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateProductData($request);

            DB::beginTransaction();

            $productData = $this->prepareProductData($validated);
            $product = Product::create($productData);

            // Main image
            if (!empty($validated['image'])) {
                $mainImagePath = $this->saveBase64Image($validated['image'], 'products/main');
                $product->update(['image' => $mainImagePath]);
            }

            // Gallery images
            if (!empty($validated['images'])) {
                $this->saveGalleryImages($product, $validated['images']);
            }

            // Handle customizations
            if (in_array(strtolower($product->type), ['customizable', 'trading'])) {
                $this->handleCustomizations($product, $request);
            }

            DB::commit();

            return $this->successResponse(
                'Product created successfully',
                $this->formatSingleProduct($product->load(['category', 'images'])),
                201
            );
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            DB::rollBack();
            \Log::error('Product create failed: ' . $e->getMessage());
            return $this->errorResponse('Failed to create product.');
        }
    }

    /**
     * Show product details.
     */
    public function show($slug): JsonResponse
    {
        try {
            $product = Product::with(array_merge(['category', 'images'], $this->customRelations))
                ->where('slug', $slug)
                ->firstOrFail();

            return $this->successResponse('Product fetched successfully', $this->formatSingleProduct($product));
        } catch (Exception $e) {
            return $this->errorResponse('Product not found', 404);
        }
    }

    /**
     * Update product.
     */
    public function update(Request $request, $id): JsonResponse
    {
        if (!Gate::allows('update-products')) {
            return $this->unauthorizedResponse();
        }

        try {
            $product = Product::findOrFail($id);
            $validated = $this->validateProductData($request, $product->id);

            DB::beginTransaction();

            $productData = $this->prepareProductData($validated);
            $product->update($productData);

            // Main image
            if (!empty($validated['image'])) {
                if ($product->image) Storage::disk('public')->delete($product->image);
                $mainImagePath = $this->saveBase64Image($validated['image'], 'products/main');
                $product->update(['image' => $mainImagePath]);
            }

            // Gallery images
            if (isset($validated['images'])) {
                foreach ($product->images as $image) {
                    Storage::disk('public')->delete($image->image);
                }
                $product->images()->delete();

                if (!empty($validated['images'])) {
                    $this->saveGalleryImages($product, $validated['images']);
                }
            }

            // Customizations
            if (strtolower($product->type) === 'customizable') {
                $this->handleCustomizations($product, $request, true);
            }

            DB::commit();

            return $this->successResponse(
                'Product updated successfully',
                $this->formatSingleProduct($product->load(['category', 'images']))
            );
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            DB::rollBack();
            \Log::error('Product update failed: ' . $e->getMessage());
            return $this->errorResponse('Failed to update product.');
        }
    }

    /**
     * Delete product.
     */
    public function destroy($id): JsonResponse
    {
        try {
            $product = Product::with(array_merge(['images'], $this->customRelations))
                ->findOrFail($id);

            DB::beginTransaction();

            if ($product->image) Storage::disk('public')->delete($product->image);
            foreach ($product->images as $img) Storage::disk('public')->delete($img->image);

            foreach ($this->customRelations as $relation) {
                foreach ($product->{$relation} as $item) {
                    if ($item->image) Storage::disk('public')->delete($item->image);
                }
                $product->{$relation}()->delete();
            }

            $product->delete();

            DB::commit();

            return $this->successResponse('Product deleted successfully');
        } catch (Exception $e) {
            DB::rollBack();
            \Log::error('Product delete failed: ' . $e->getMessage());
            return $this->errorResponse('Failed to delete product.');
        }
    }

    /**
     * Validation rules.
     */
    private function validateProductData(Request $request, $productId = null): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:products,slug,' . ($productId ?? 'NULL') . ',id',
            'type' => 'required|string|in:simple,customizable,trading,Simple,Customizable,Trading',
            'price' => 'required|numeric|min:0',
            'status' => 'required|in:active,inactive',
            'offer_price' => 'nullable|numeric|min:0|lt:price',
            'category_id' => 'required|exists:categories,id',
            'short_description' => 'nullable|string',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'required|string',
        ];

        if (in_array($request->type, ['Customizable', 'Trading', 'customizable', 'trading'])) {
            foreach ($this->customRelations as $field) {
                $rules[$field] = 'sometimes|array';
                $rules[$field . '.*'] = 'sometimes|string';
            }
        }

        return $request->validate($rules);
    }

    /**
     * Prepare product data.
     */
    private function prepareProductData(array $validated): array
    {
        return [
            'name' => $validated['name'],
            'slug' => !empty($validated['slug'])
                ? $validated['slug'] . '-' . rand(1000, 9999)
                : Str::slug($validated['name']) . '-' . rand(1000, 9999),
            'type' => strtolower($validated['type']),
            'price' => $validated['price'],
            'status' => $validated['status'],
            'category_id' => $validated['category_id'],
            'short_description' => $validated['short_description'] ?? null,
            'description' => $validated['description'] ?? null,
            'offer_price' => $validated['offer_price'] ?? null,
        ];
    }

    /**
     * Save gallery images.
     */
    private function saveGalleryImages(Product $product, array $images): void
    {
        foreach ($images as $img) {
            if (!empty($img)) {
                $path = $this->saveBase64Image($img, 'products/gallery');
                ProductHasImage::create([
                    'product_id' => $product->id,
                    'image' => $path,
                ]);
            }
        }
    }

    /**
     * Handle customizable/trading options.
     */
    private function handleCustomizations(Product $product, Request $request, bool $isUpdate = false): void
    {
        foreach ($this->customRelations as $relation) {
            if ($request->has($relation) && is_array($request->$relation)) {
                if ($isUpdate) $product->{$relation}()->delete();

                foreach ($request->$relation as $index => $base64) {
                    $path = $this->saveBase64Image($base64, "products/customizations/{$relation}");
                    $product->{$relation}()->create([
                        'name' => ucfirst($relation) . ' ' . ($index + 1),
                        'product_id' => $product->id,
                        'image' => $path,
                    ]);
                }
            }
        }
    }

    /**
     * Save base64 image and return path.
     */
    private function saveBase64Image(string $base64Image, string $folder): string
    {
    if(preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
            $imageData = substr($base64Image, strpos($base64Image, ',') + 1);
            $extension = strtolower($type[1]);
        } else {
            $imageData = $base64Image;
            $extension = 'png';
        }

        $decoded = base64_decode(str_replace(' ', '+', $imageData));
        if ($decoded === false) throw new Exception('Failed to decode base64 image');

        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) $extension = 'png';

        $fileName = time() . '_' . uniqid() . '.' . $extension;
        $filePath = $folder . '/' . $fileName;

        if (!Storage::disk('public')->put($filePath, $decoded))
            throw new Exception('Failed to save image to storage');

        return $filePath;
    }

    /**
     * Format single product for API response.
     */
    private function formatSingleProduct($product): array
    {
        $data = [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'type' => $product->type,
            'price' => $product->price,
            'offer_price' => $product->offer_price,
            'final_price' => $product->offer_price ?? $product->price,
            'discount_percentage' => $product->offer_price
                ? round((($product->price - $product->offer_price) / $product->price) * 100, 2)
                : 0,
            'status' => $product->status,
            'short_description' => $product->short_description,
            'description' => $product->description,
            'created_at' => $product->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $product->updated_at->format('Y-m-d H:i:s'),
            'image' => $product->image ? 'storage/'.$product->image  : null,
        ];

        if ($product->relationLoaded('images')) {
            $data['gallery_images'] = $product->images->map(fn($img) => [
                'id' => $img->id,
                'url' =>  'storage/'.$img->image,
                'alt' => $product->name,
            ])->toArray();
        }

        if ($product->relationLoaded('category') && $product->category) {
            $data['category'] = [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug ?? null,
            ];
        }

        foreach ($this->customRelations as $relation) {
            if ($product->relationLoaded($relation)) {
                $data['customizations'][$relation] = $product->{$relation}->map(fn($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'image' => $item->image ? 'storage/'.$item->image : null,
                ])->toArray();
            }
        }

        return $data;
    }

    /* ---------- Unified Response Helpers ---------- */

    private function successResponse(string $message, $data = null, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'status' => $status, 'message' => $message, 'data' => $data], $status);
    }

    private function errorResponse(string $message, int $status = 500): JsonResponse
    {
        return response()->json(['success' => false, 'status' => $status, 'message' => $message], $status);
    }

    private function validationErrorResponse(ValidationException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'status' => 422,
            'message' => 'Validation failed',
            'errors' => $e->errors(),
        ], 422);
    }

    private function unauthorizedResponse(): JsonResponse
    {
        return $this->errorResponse('Unauthorized access', 401);
    }
}
