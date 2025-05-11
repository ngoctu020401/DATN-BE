<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReviewController extends Controller
{
    //
    public function index()
    {
        try {
            $reviews = Review::orderByDesc('created_at')->paginate(20);
            return response()->json($reviews);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi lấy danh sách đánh giá',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    //
    public function show($id)
    {
        try {
            $review = Review::find($id);
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
}
