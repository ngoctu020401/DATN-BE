<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariation;
use Illuminate\Http\Request;
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
}
