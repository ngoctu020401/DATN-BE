<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


class ProductController extends Controller
{
    //
    public function index(Request $request)
    {
        try {
            $query = Product::with('category');

            // Tìm kiếm theo tên sản phẩm
            if ($request->has('name')) {
                $query->where('name', 'like', '%' . $request->name . '%');
            }

            // Lọc theo danh mục
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            // Lấy danh sách sản phẩm kèm thông tin danh mục, phân trang 10 sản phẩm/trang
            $products = $query->orderBy('created_at', 'desc')->paginate(20);
            return response()->json($products, 200);
        } catch (\Throwable $th) {
            // Trả về lỗi nếu có exception
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
            // Validate đầu vào
            $data = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'main_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
                'category_id' => 'nullable|exists:categories,id',
                'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
                'color_id' => 'required|exists:colors,id',
                'size_id' => 'required|exists:sizes,id',
                'price' => 'required|numeric|min:0',
                'sale_price' => 'nullable|numeric|lt:price',
                'stock_quantity' => 'nullable|numeric|min:0',
            ], [
                'sale_price.lt' => 'Giá khuyến mãi phải nhỏ hơn giá gốc.',
            ]);

            // Lưu ảnh chính
            $mainImage = $request->file('main_image');
            $mainImageName = 'main_' . time() . '_' . Str::uuid() . '.' . $mainImage->getClientOriginalExtension();// lưu ảnh vào public
            $mainImagePath = $mainImage->storeAs('uploads', $mainImageName, 'public'); // lấy ra link ảnh

            // Tạo sản phẩm
            $product = Product::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'main_image' => $mainImagePath,
                'category_id' => $data['category_id'] ?? 1,
                'is_active' => true,
            ]);

            // Thêm biến thể đầu tiên
            ProductVariation::create([
                'product_id' => $product->id,
                'color_id' => $data['color_id'],
                'size_id' => $data['size_id'],
                'price' => $data['price'],
                'sale_price' => $data['sale_price'] ?? null,
                'stock_quantity' => $data['stock_quantity'] ?? 0,
            ]);

            // Lưu các ảnh phụ (nếu có)
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
                'message' => 'Không tìm thấy sản phẩm'
            ], 500);
        }
        return response()->json($product, 200);
    }
    public function getVariants($id) // id product
    {
        $variations = ProductVariation::where('product_id', $id)->with(['color', 'size'])->paginate(10); //tìm tất cả các biến thẻ có trong sản phẩm đó vagf in ra
        return response()->json($variations, 200);
    }
    public function getImages($id)
    {
        $variations = ProductImage::where('product_id', $id)->paginate(10);
        return response()->json($variations, 200);
    }
    // Update sản phẩm
    public function update(Request $request, $id)
    {
        try {
            $product = Product::with(['images', 'variations'])->findOrFail($id);

            $data = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'main_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'category_id' => 'nullable|exists:categories,id',
            ]);

            // Cập nhật ảnh chính nếu có
            if ($request->hasFile('main_image')) {
                $mainImage = $request->file('main_image');
                $mainImageName = 'main_' . time() . '_' . Str::uuid() . '.' . $mainImage->getClientOriginalExtension();
                $mainImagePath = $mainImage->storeAs('uploads', $mainImageName, 'public');
                $product->main_image = $mainImagePath;
            }

            // Cập nhật thông tin sản phẩm
            $product->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'category_id' => $data['category_id'] ?? $product->category_id,
            ]);

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

    // Sửa biến thể

    public function updateVariation(Request $request, $id)
    {
        $data = $request->validate([
            'price' => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|lt:price',
            'stock_quantity' => 'nullable|numeric|min:0',
        ], [
            'sale_price.lt' => 'Giá khuyến mãi phải nhỏ hơn giá gốc.',
        ]);


        try {
            $variation = ProductVariation::findOrFail($id);

            $variation->update([
                'price'      => $data['price'],
                'sale_price' => $data['sale_price'] ?? $variation->sale_price,
                'stock_quantity' => $data['stock_quantity'] ?? $variation->stock_quantity,
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
            'sale_price' => 'nullable|numeric|lt:price',
            'stock_quantity' => 'nullable|numeric|min:0',
        ], [
            'sale_price.lt' => 'Giá khuyến mãi phải nhỏ hơn giá gốc.',
        ]);

        $exists = ProductVariation::where('product_id', $data['product_id'])
            ->where('color_id', $data['color_id'])
            ->where('size_id', $data['size_id'])
            ->exists(); // KIểm tra xem biến thể đã tồn tại hay chưa, nếu tồn tại trả về true

        if ($exists) { // Nếu đã tồn tại báo lỗi
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
            //  Tìm biến thể theo ID, nếu không có sẽ tự throw 404
            $variation = ProductVariation::findOrFail($id);

            //  Các trạng thái đơn hàng được xem là "đã hoàn tất", không ảnh hưởng tới việc xóa biến thể
            $orderStatusExcludes = [5, 6, 8]; // 5: Hoàn thành, 6: Đã huỷ, 8: Hoàn tiền thành công

            //  Kiểm tra xem biến thể này có nằm trong đơn hàng nào chưa hoàn tất không *(// 5: Hoàn thành, 6: Đã huỷ, 8: Hoàn tiền thành công. Không phải 3 trạng thái này) // Các trạng thái khác ngoài 3 trạng thái
            $inActiveOrder = OrderItem::where('variation_id', $id)
                ->whereHas('order', function ($query) use ($orderStatusExcludes) { // Kiểm tra order
                    $query->whereNotIn('order_status_id', $orderStatusExcludes); // không nằm trong
                })
                ->exists(); // Nếu có thì trả về true, nghĩa là biến thể đó nằm trong 1 đơn hàng đang xử lí

            //  Nếu biến thể đang được dùng trong đơn hàng đang xử lý → không cho xóa
            if ($inActiveOrder) { // Nếu có thì không cho xóa
                return response()->json([
                    'message' => 'Không thể xoá biến thể vì đang được sử dụng trong đơn hàng đang xử lý.',
                ], 422);
            }

            //  Không bị ràng buộc → tiến hành xóa (soft delete)
            $variation->delete();

            return response()->json([
                'message' => 'Xoá biến thể thành công',
            ], 200);
        } catch (\Throwable $th) {
            //  Lỗi bất ngờ → trả về thông báo lỗi
            return response()->json([
                'message' => 'Không thể xoá biến thể',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    //
    public function deleteImage($id)
    {
        try {
            $image = ProductImage::findOrFail($id);

            // Xoá file vật lý nếu tồn tại
            if ($image->url && Storage::disk('public')->exists($image->url)) {
                Storage::disk('public')->delete($image->url);
            }

            $image->forceDelete(); // Xoá record DB

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
        //  Lấy sản phẩm theo ID và load cả biến thể + hình ảnh liên quan
        $product = Product::with(['variations', 'images'])->findOrFail($id);

        //  Xác định các trạng thái đơn hàng được xem là đã hoàn tất hoặc không còn xử lý nữa
        $excludedStatuses = [5, 6, 8]; // 5: Hoàn thành, 6: Đã huỷ, 8: Hoàn tiền thành công

        //  Duyệt qua từng biến thể của sản phẩm
        foreach ($product->variations as $variation) {
            // Kiểm tra xem biến thể này có trong đơn hàng nào đang xử lý không
            $inActiveOrder = OrderItem::where('variation_id', $variation->id)
                ->whereHas('order', function ($query) use ($excludedStatuses) {
                    // Chỉ lấy những đơn hàng KHÔNG nằm trong trạng thái đã hoàn tất
                    $query->whereNotIn('order_status_id', $excludedStatuses);
                })
                ->exists(); // true nếu tìm thấy → biến thể đang được sử dụng

            // Nếu biến thể đang nằm trong đơn hàng đang xử lý → không cho xoá
            if ($inActiveOrder) {
                return response()->json([
                    'message' => 'Không thể xoá sản phẩm vì có biến thể đang được sử dụng trong đơn hàng đang xử lý.',
                ], 422); // Trả về lỗi 422 (Unprocessable Entity)
            }
        }

        //  Nếu không có ràng buộc đơn hàng → tiến hành xoá

        //  Soft delete sản phẩm (đánh dấu deleted_at)
        $product->delete();

        //  Soft delete toàn bộ ảnh sản phẩm
        foreach ($product->images as $image) {
            $image->delete();
        }

        //  Soft delete toàn bộ biến thể sản phẩm
        foreach ($product->variations as $variation) {
            $variation->delete();
        }

        // Trả về phản hồi thành công
        return response()->json([
            'message' => 'Xoá sản phẩm thành công',
        ]);
    }

}
