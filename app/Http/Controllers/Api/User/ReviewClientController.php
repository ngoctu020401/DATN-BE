<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ProductVariation;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ReviewClientController extends Controller
{
    //
    public function store(Request $request)
    {
        try {
            $user = auth('sanctum')->user();;
            if (!$user) {
                return response()->json(['message' => 'Bạn cần đăng nhập để đánh giá.'], 401);
            }

            $validator = Validator::make($request->all(), [
                'order_id' => 'required|exists:orders,id',
                'order_item_id' => 'required|exists:order_items,id',
                'variation_id' => 'required',
                'rating' => 'required|integer|min:1|max:5',
                'content' => 'required|string|max:2000',
                'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048'
            ], [
                'order_id.required' => 'Thiếu mã đơn hàng.',
                'order_id.exists' => 'Đơn hàng không tồn tại.',
                'order_item_id.required' => 'Thiếu sản phẩm trong đơn.',
                'order_item_id.exists' => 'Sản phẩm trong đơn không tồn tại.',
                'variation_id.required' => 'Thiếu mã sản phẩm.',
                'rating.required' => 'Vui lòng chọn số sao.',
                'rating.min' => 'Số sao tối thiểu là 1.',
                'rating.max' => 'Số sao tối đa là 5.',
                'content.required' => 'Vui lòng nhập nội dung đánh giá.',
                'content.max' => 'Nội dung không được vượt quá 2000 ký tự.',
                'images.*.image' => 'Tệp tải lên phải là hình ảnh.',
                'images.*.mimes' => 'Ảnh chỉ chấp nhận jpeg, png, jpg, gif.',
                'images.*.max' => 'Mỗi ảnh không được vượt quá 2MB.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()->all()
                ], 422);
            }

            $images = [];
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $imageName = 'review_' . time() . '_' . Str::uuid() . '.' . $image->getClientOriginalExtension();
                    $imagePath = $image->storeAs('uploads', $imageName, 'public');
                    $images[] = 'storage/' . $imagePath;
                }
            }

            $data = $validator->validated();
            $variation = ProductVariation::find($data['variation_id']);
            $data['product_id'] = $variation->product_id;
            $data['user_id'] = $user->id;
            $data['images'] = $images;
            $data['is_active'] = true;
            $data['is_updated'] = false;

            $review = Review::create($data);

            return response()->json([
                'message' => 'Gửi đánh giá thành công',
                'data' => $review
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi gửi đánh giá',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    //
    public function update(Request $request, $id)
    {
        try {
             $user = auth('sanctum')->user();
            if (!$user) {
                return response()->json(['message' => 'Bạn cần đăng nhập để sửa đánh giá.'], 401);
            }

            $review = Review::find($id);
            if (!$review || $review->user_id !== $user->id) {
                return response()->json(['message' => 'Không tìm thấy hoặc không có quyền'], 403);
            }

            if ($review->is_updated) {
                return response()->json(['message' => 'Bạn chỉ được sửa đánh giá một lần'], 403);
            }

            $validator = Validator::make($request->all(), [
                'rating' => 'required|integer|min:1|max:5',
                'content' => 'required|string|max:2000',
                'keep_images' => 'nullable|array',
                'keep_images.*' => 'string',
                'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()->all()
                ], 422);
            }

            // 1. Giữ lại ảnh cũ theo yêu cầu
            $keepImages = $request->input('keep_images', []);
            $newImages = [];

            // 2. Upload ảnh mới (nếu có)
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $imageName = 'review_' . time() . '_' . Str::uuid() . '.' . $image->getClientOriginalExtension();
                    $imagePath = $image->storeAs('uploads', $imageName, 'public');
                    $newImages[] = 'storage/' . $imagePath;
                }
            }

            // 3. Gộp ảnh cũ còn giữ + ảnh mới
            $allImages = array_merge($keepImages, $newImages);

            // 4. Cập nhật
            $review->update([
                'rating' => $request->rating,
                'content' => $request->content,
                'images' => $allImages,
                'is_updated' => true
            ]);

            return response()->json([
                'message' => 'Cập nhật đánh giá thành công',
                'data' => $review
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi cập nhật đánh giá',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    //
    public function getProductReviews($productId)
    {
        try {
            $reviews = Review::with(['user', 'orderItem'])
                ->where('product_id', $productId)
                ->where('is_active', true)
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            // Tính toán thống kê đánh giá
            $stats = [
                'total' => Review::where('product_id', $productId)->where('is_active', true)->count(),
                'average_rating' => Review::where('product_id', $productId)
                    ->where('is_active', true)
                    ->avg('rating'),
                'rating_counts' => [
                    5 => Review::where('product_id', $productId)->where('rating', 5)->where('is_active', true)->count(),
                    4 => Review::where('product_id', $productId)->where('rating', 4)->where('is_active', true)->count(),
                    3 => Review::where('product_id', $productId)->where('rating', 3)->where('is_active', true)->count(),
                    2 => Review::where('product_id', $productId)->where('rating', 2)->where('is_active', true)->count(),
                    1 => Review::where('product_id', $productId)->where('rating', 1)->where('is_active', true)->count(),
                ]
            ];

            return response()->json([
                'stats' => $stats,
                'reviews' => $reviews->items(),
                'pagination' => [
                    'total' => $reviews->total(),
                    'per_page' => $reviews->perPage(),
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi lấy danh sách đánh giá',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
