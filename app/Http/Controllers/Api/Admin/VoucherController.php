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
            $validator = Validator::make(
                $request->all(),
                [
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
                ],
                [
                    'code.required' => 'Vui lòng nhập mã voucher.',
                    'code.unique' => 'Mã voucher đã tồn tại.',

                    'name.required' => 'Vui lòng nhập tên voucher.',
                    'name.string' => 'Tên voucher phải là chuỗi ký tự.',
                    'name.max' => 'Tên voucher không được vượt quá 255 ký tự.',

                    'description.string' => 'Mô tả phải là chuỗi văn bản.',

                    'discount_percent.integer' => 'Phần trăm giảm giá phải là số nguyên.',
                    'discount_percent.min' => 'Phần trăm giảm giá tối thiểu là 1%.',
                    'discount_percent.max' => 'Phần trăm giảm giá tối đa là 100%.',

                    'amount.integer' => 'Số tiền giảm phải là số nguyên.',
                    'amount.min' => 'Số tiền giảm giá tối thiểu là 1000đ.',

                    'max_discount_amount.integer' => 'Mức giảm tối đa phải là số nguyên.',
                    'max_discount_amount.min' => 'Mức giảm tối đa tối thiểu là 1000đ.',

                    'min_product_price.integer' => 'Giá trị đơn hàng tối thiểu phải là số nguyên.',
                    'min_product_price.min' => 'Giá trị đơn hàng tối thiểu không được nhỏ hơn 0.',

                    'usage_limit.integer' => 'Số lượt sử dụng phải là số nguyên.',
                    'usage_limit.min' => 'Số lượt sử dụng không được nhỏ hơn 0.',

                    'type.required' => 'Vui lòng chọn loại voucher.',
                    'type.in' => 'Loại voucher không hợp lệ. Chỉ chấp nhận "percent" hoặc "amount".',

                    'start_date.required' => 'Vui lòng nhập ngày bắt đầu.',
                    'start_date.date' => 'Ngày bắt đầu không hợp lệ.',

                    'expiry_date.date' => 'Ngày hết hạn không hợp lệ.',
                    'expiry_date.after_or_equal' => 'Ngày hết hạn phải bằng hoặc sau ngày bắt đầu.',

                    'is_active.boolean' => 'Trạng thái kích hoạt không hợp lệ (chỉ nhận true hoặc false).',
                ]
            );

            if ($validator->fails()) {
                $messages = collect($validator->errors()->all());

                return response()->json([
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $messages
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
    //
    public function update(Request $request, $id)
    {
        try {
            $voucher = Voucher::find($id);
            if (!$voucher) {
                return response()->json(['message' => 'Không tìm thấy voucher.'], 404);
            }

            $validator = Validator::make($request->all(), [
                'code' => 'required|unique:vouchers,code,' . $voucher->id,
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
                'name.string' => 'Tên voucher phải là chuỗi ký tự.',
                'name.max' => 'Tên voucher không được vượt quá 255 ký tự.',

                'description.string' => 'Mô tả phải là chuỗi văn bản.',

                'discount_percent.integer' => 'Phần trăm giảm phải là số nguyên.',
                'discount_percent.min' => 'Phần trăm giảm tối thiểu là 1%.',
                'discount_percent.max' => 'Phần trăm giảm tối đa là 100%.',

                'amount.integer' => 'Số tiền giảm phải là số nguyên.',
                'amount.min' => 'Số tiền giảm giá tối thiểu là 1000đ.',

                'max_discount_amount.integer' => 'Giảm tối đa phải là số nguyên.',
                'max_discount_amount.min' => 'Giảm tối đa tối thiểu là 1000đ.',

                'min_product_price.integer' => 'Giá trị đơn hàng tối thiểu phải là số nguyên.',
                'min_product_price.min' => 'Giá trị đơn hàng tối thiểu không được âm.',

                'usage_limit.integer' => 'Số lượt sử dụng phải là số nguyên.',
                'usage_limit.min' => 'Số lượt sử dụng không được nhỏ hơn 0.',

                'type.required' => 'Vui lòng chọn loại voucher.',
                'type.in' => 'Loại voucher không hợp lệ.',

                'start_date.required' => 'Vui lòng nhập ngày bắt đầu.',
                'start_date.date' => 'Ngày bắt đầu không hợp lệ.',

                'expiry_date.date' => 'Ngày hết hạn không hợp lệ.',
                'expiry_date.after_or_equal' => 'Ngày hết hạn phải sau hoặc bằng ngày bắt đầu.',

                'is_active.boolean' => 'Trạng thái kích hoạt không hợp lệ.',
            ]);

            if ($validator->fails()) {
                $messages = collect($validator->errors()->all());

                return response()->json([
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $messages
                ], 422);
            }


            $voucher->update($validator->validated());

            return response()->json([
                'message' => 'Cập nhật voucher thành công',
                'data' => $voucher
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi cập nhật voucher',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
