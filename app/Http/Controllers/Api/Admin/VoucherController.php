<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VoucherController extends Controller
{
    //
    public function index(Request $request)
    {
        $vouchers = Voucher::orderByDesc('created_at')->paginate(10);
        return response()->json($vouchers);
    }
    //
    public function show($id)
    {
        $voucher = Voucher::find($id);
        if (!$voucher) {
            return response()->json(['message' => 'Không tìm thấy voucher.'], 404);
        }
        return response()->json($voucher);
    }
    //
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'code' => 'required|unique:vouchers,code',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'discount_percent' => 'nullable|integer|min:1|max:100',
                'amount' => 'nullable|integer|min:1000',
                'max_discount_amount' => 'nullable|integer|min:1000',
                'min_product_price' => 'nullable|integer|min:0',
                'usage_limit' => 'nullable|integer|min:0',
                'type' => 'required|in:percent,amount',
                'start_date' => 'required|date',
                'expiry_date' => 'nullable|date|after_or_equal:start_date',
                'is_active' => 'boolean'
            ], [
                'code.required' => 'Vui lòng nhập mã voucher.',
                'code.unique' => 'Mã voucher đã tồn tại.',
                'name.required' => 'Vui lòng nhập tên voucher.',
                'type.required' => 'Vui lòng chọn loại voucher.',
                'discount_percent.max' => 'Phần trăm giảm tối đa là 100%.',
                'amount.min' => 'Số tiền giảm giá tối thiểu là 1000đ.',
                'expiry_date.after_or_equal' => 'Ngày hết hạn phải sau hoặc bằng ngày bắt đầu.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();
            $data['times_used'] = 0;

            $voucher = Voucher::create($data);

            return response()->json([
                'message' => 'Tạo voucher thành công',
                'data' => $voucher
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi tạo voucher',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    //
    public function destroy($id)
    {
        try {
            $voucher = Voucher::find($id);
            if (!$voucher) {
                return response()->json(['message' => 'Không tìm thấy voucher.'], 404);
            }

            $voucher->delete();

            return response()->json(['message' => 'Xóa voucher thành công']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi xóa voucher',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
