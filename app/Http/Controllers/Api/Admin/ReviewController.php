<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReviewController extends Controller
{
    //
public function index(Request $request)
{
    try {
        $query = Review::with(['product', 'user']);

        // VALIDATE CÁC FILTER ĐẦU VÀO
        $validator = Validator::make($request->all(), [
            'has_reply' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'rating'    => 'nullable|integer|in:1,2,3,4,5',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Tham số lọc không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        // LỌC THEO TRẠNG THÁI PHẢN HỒI
        if ($request->filled('has_reply')) {
            $hasReply = filter_var($request->has_reply, FILTER_VALIDATE_BOOLEAN);
            $query->when($hasReply, fn($q) => $q->whereNotNull('reply'))
                  ->unless($hasReply, fn($q) => $q->whereNull('reply'));
        }

        // LỌC THEO TRẠNG THÁI DUYỆT
        if ($request->filled('is_active')) {
            $isActive = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }

        // LỌC THEO SỐ SAO
        if ($request->filled('rating')) {
            $query->where('rating', (int)$request->rating);
        }

        // SẮP XẾP MỚI NHẤT
        $query->latest('created_at');

        // PHÂN TRANG
        $reviews = $query->paginate(20);

        return response()->json($reviews);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Lỗi khi lấy danh sách đánh giá',
            'error' => config('app.debug') ? $e->getMessage() : 'Đã có lỗi xảy ra'
        ], 500);
    }
}
    //
    public function show($id)
    {
        try {
            $review = Review::with('product')->find($id);
            if (!$review) {
                return response()->json(['message' => 'Không tìm thấy đánh giá'], 404);
            }
            return response()->json($review);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi lấy chi tiết đánh giá',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    //
    public function reply(Request $request, $id)
    {
        try {
            $review = Review::find($id);
            if (!$review) {
                return response()->json(['message' => 'Không tìm thấy đánh giá'], 404);
            }

            $validator = Validator::make($request->all(), [
                'reply' => 'required|string|max:1000'
            ], [
                'reply.required' => 'Vui lòng nhập nội dung phản hồi.',
                'reply.string' => 'Phản hồi phải là chuỗi.',
                'reply.max' => 'Phản hồi không được vượt quá 1000 ký tự.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()->all()
                ], 422);
            }

            $review->update([
                'reply' => $request->reply,
                'reply_at' => now()
            ]);

            return response()->json([
                'message' => 'Phản hồi thành công',
                'data' => $review
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi phản hồi đánh giá',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    //
     public function block(Request $request, $id)
    {
        try {
            $review = Review::find($id);
            if (!$review) {
                return response()->json(['message' => 'Không tìm thấy đánh giá'], 404);
            }

            $validator = Validator::make($request->all(), [
                'is_active' => 'required|boolean',
                'hidden_reason' => 'nullable|string|max:255'
            ], [
                'is_active.required' => 'Trạng thái là bắt buộc.',
                'is_active.boolean' => 'Trạng thái không hợp lệ.',
                'hidden_reason.max' => 'Lý do không được vượt quá 255 ký tự.'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()->all()
                ], 422);
            }

            $review->update([
                'is_active' => $request->is_active,
                'hidden_reason' => $request->is_active ? null : $request->hidden_reason
            ]);

            return response()->json([
                'message' => $request->is_active ? 'Đã hiển thị lại đánh giá' : 'Đã ẩn đánh giá thành công',
                'data' => $review
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi cập nhật trạng thái đánh giá',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
