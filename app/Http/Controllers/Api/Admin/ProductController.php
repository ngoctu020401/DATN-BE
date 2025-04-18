<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


class ProductController extends Controller
{
    //
    public function index()
    {
        //
        try {
            //code...
            $produtcs = Product::with('category')->paginate(10);
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
    public function show($id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json([
                'message' => 'Không tìm thấy màu sắc'
            ], 500);
        }
        return response()->json($product, 200);
    }
    public function getVariants($id)
    {
        $variations = ProductVariation::where('product_id', $id)->get();
        return response()->json($variations, 200);
    }
    public function getImages($id)
    {
        $variations = ProductImage::where('product_id', $id)->get();
        return response()->json($variations, 200);
    }
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

    public function updateVariation(Request $request, $id)
    {
        $data = $request->validate([
            'price' => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
        ]);

        try {
            $variation = ProductVariation::findOrFail($id);

            $variation->update([
                'price'      => $data['price'],
                'sale_price' => $data['sale_price'] ?? $variation->sale_price,
            ]);

            return response()->json([
                'message' => 'Cập nhật biến thể thành công',
                'variation' => $variation,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Cập nhật biến thể thất bại',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    //
    public function addImages(Request $request, $id)
    {
        $request->validate([
            'images.*' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        $product = Product::findOrFail($id);

        $uploaded = [];

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $imageName = 'gallery_' . time() . '_' . Str::uuid() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('uploads', $imageName, 'public');

                $productImage = ProductImage::create([
                    'product_id' => $product->id,
                    'url' => $imagePath,
                ]);

                $uploaded[] = $productImage;
            }
        }

        return response()->json([
            'message' => 'Thêm ảnh phụ thành công',
            'images' => $uploaded
        ], 201);
    }

    public function addVariation(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'color_id' => 'required|exists:colors,id',
            'size_id' => 'required|exists:sizes,id',
            'price' => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
        ]);
        $exists = ProductVariation::where('product_id', $data['product_id'])
            ->where('color_id', $data['color_id'])
            ->where('size_id', $data['size_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Biến thể với màu và size này đã tồn tại cho sản phẩm.',
            ], 422);
        }

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
    //
    public function deleteImage($id)
    {
        try {
            $image = ProductImage::findOrFail($id);

            if ($image->url && Storage::disk('public')->exists($image->url)) {
                Storage::disk('public')->delete($image->url);
            }

            $image->delete();

            return response()->json([
                'message' => 'Xoá ảnh thành công'
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Xoá ảnh thất bại',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    //
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete(); // soft delete 

        return response()->json([
            'message' => 'Xoá sản phẩm thành công'
        ]);
    }
}
