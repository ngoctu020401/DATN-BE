<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


// Lưu ảnh chính với tên duy nhất
$mainImage = $request->file('main_image');
$mainImageName = 'main_' . time() . '_' . Str::uuid() . '.' . $mainImage->getClientOriginalExtension();
$mainImagePath = $mainImage->storeAs('uploads', $mainImageName, 'public');


class ProductController extends Controller
{
    //
    public function index()
    {
        //
        try {
            //code...
            $produtcs = Product::paginate(10);
            return response()->json($produtcs, 200);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'message' => 'Lỗi',
                'errors' => $th->getMessage()
            ], 500);
        }
    }
    //
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'main_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
                'category_id' => 'nullable|exists:categories,id',
                'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
                'variations' => 'required|array',
                'variations.*.color_id' => 'required|exists:colors,id',
                'variations.*.size_id' => 'required|exists:sizes,id',
                'variations.*.price' => 'required|numeric|min:0',
                'variations.*.sale_price' => 'nullable|numeric|min:0',
            ]);

            // Lưu ảnh chính với tên duy nhất
            $mainImage = $request->file('main_image');
            $mainImageName = 'main_' . time() . '_' . Str::uuid() . '.' . $mainImage->getClientOriginalExtension();
            $mainImagePath = $mainImage->storeAs('uploads', $mainImageName, 'public');

            // Tạo sản phẩm
            $product = Product::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'main_image' => $mainImagePath,
                'category_id' => $data['category_id'] ?? 1,
                'is_active' => true,
            ]);

            // Lưu biến thể
            foreach ($data['variations'] as $variation) {
                ProductVariation::create(array_merge($variation, [
                    'product_id' => $product->id
                ]));
            }

            // Lưu các ảnh phụ nếu có
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $imageName = 'gallery_' . time() . '_' . Str::uuid() . '.' . $image->getClientOriginalExtension();
                    $imagePath = $image->storeAs('uploads', $imageName, 'public');

                    ProductImage::create([
                        'product_id' => $product->id,
                        'url' => $imagePath,
                    ]);
                }
            }

            return response()->json([
                'message' => 'Thêm sản phẩm thành công',
                'product' => $product
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi thêm sản phẩm',
                'errors' => $th->getMessage(),
            ], 500);
        }
    }
    //

    //
    public function update(Request $request, $id)
    {
        try {
            $product = Product::with(['images', 'variations'])->findOrFail($id);

            $data = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'main_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'category_id' => 'nullable|exists:categories,id',
                'new_images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048', // ảnh mới nếu có
                'delete_image_ids' => 'nullable|array', // mảng ID ảnh cần xoá
                'delete_image_ids.*' => 'integer|exists:product_images,id',
                'variations' => 'nullable|array',
                'variations.*.id' => 'required|exists:product_variations,id',
                'variations.*.price' => 'nullable|numeric|min:0',
                'variations.*.sale_price' => 'nullable|numeric|min:0',

            ]);

            //  Cập nhật ảnh chính nếu có
            if ($request->hasFile('main_image')) {
                if ($product->main_image && Storage::disk('public')->exists($product->main_image)) {
                    Storage::disk('public')->delete($product->main_image);
                }

                $mainImage = $request->file('main_image');
                $mainImageName = 'main_' . time() . '_' . Str::uuid() . '.' . $mainImage->getClientOriginalExtension();
                $mainImagePath = $mainImage->storeAs('uploads', $mainImageName, 'public');
                $product->main_image = $mainImagePath;
            }

            //  Cập nhật thông tin chung
            $product->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'category_id' => $data['category_id'] ?? $product->category_id,
            ]);

            //  Xoá các ảnh phụ nếu có chỉ định
            if (!empty($data['delete_image_ids'])) {
                $imagesToDelete = ProductImage::whereIn('id', $data['delete_image_ids'])
                    ->where('product_id', $product->id)
                    ->get();

                foreach ($imagesToDelete as $img) {
                    if ($img->url && Storage::disk('public')->exists($img->url)) {
                        Storage::disk('public')->delete($img->url);
                    }
                    $img->delete();
                }
            }
            if ($request->has('variations')) {
                foreach ($request->input('variations') as $variationData) {
                    if (isset($variationData['id'])) {
                        $variation = ProductVariation::where('id', $variationData['id'])
                            ->where('product_id', $product->id)
                            ->first();

                        if ($variation) {
                            $variation->update([
                                'price'      => $variationData['price'] ?? $variation->price,
                                'sale_price' => $variationData['sale_price'] ?? $variation->sale_price,
                            ]);
                        }
                    }
                }
            }

            //  Upload thêm ảnh phụ nếu có
            if ($request->hasFile('new_images')) {
                foreach ($request->file('new_images') as $image) {
                    $imageName = 'gallery_' . time() . '_' . Str::uuid() . '.' . $image->getClientOriginalExtension();
                    $imagePath = $image->storeAs('uploads', $imageName, 'public');

                    ProductImage::create([
                        'product_id' => $product->id,
                        'url' => $imagePath,
                    ]);
                }
            }

            return response()->json([
                'message' => 'Cập nhật sản phẩm thành công',
                'product' => $product->fresh(['images', 'variations'])
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi cập nhật sản phẩm',
                'errors' => $th->getMessage(),
            ], 500);
        }
    }
    //
    public function addVariation(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'color_id' => 'required|exists:colors,id',
            'size_id' => 'required|exists:sizes,id',
            'price' => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
        ]);

        try {
            $variation = ProductVariation::create($data);

            return response()->json([
                'message' => 'Thêm biến thể thành công',
                'variation' => $variation,
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi thêm biến thể',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
    //
    public function deleteVariation($id)
    {
        try {
            $variation = ProductVariation::findOrFail($id);
            $variation->delete(); // Soft delete

            return response()->json([
                'message' => 'Xóa biến thể thành công',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Không thể xóa biến thể',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}
