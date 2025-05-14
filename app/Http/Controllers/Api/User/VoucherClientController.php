<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VoucherClientController extends Controller
{
    //
    public function applyVoucher(Request $request)
    {
        try {
            // Xác nhận dữ liệu đầu vào
            $validatedData = $request->validate([
                'voucher_code' => 'required|string|exists:vouchers,code', // Mã voucher
                'total_amount' => 'required|numeric|min:0', // Giá trị đơn hàng dự kiến
            ]);

            // Kiểm tra token xác thực
            $user = auth('sanctum')->user();
            $userId = $user ? $user->id : null; // Lấy ID của người dùng (nếu đã đăng nhập)

            // Lấy thông tin voucher
            $voucher = Voucher::where('code', $validatedData['voucher_code'])->first();


            // Kiểm tra hạn sử dụng
            if (!$voucher->expiry_date || Carbon::parse($voucher->expiry_date)->isBefore(now())) {
                return response()->json(['message' => 'Voucher đã hết hạn'], 400);
            }
            if (!$voucher->start_date || Carbon::parse($voucher->start_date)->isAfter(now())) {
                return response()->json(['message' => 'Voucher không khả dụng'], 400);
            }

            // Kiểm tra số lượt sử dụng còn lại
            if ($voucher->usage_limit && $voucher->usage_limit = 0) {
                return response()->json(['message' => 'Voucher đã hết lượt sử dụng'], 400);
            }

            // Kiểm tra giá trị tối thiểu
            if ($voucher->min_product_price && $validatedData['total_amount'] < $voucher->min_product_price) {
                return response()->json(['message' => 'Giá trị đơn hàng không đủ điều kiện áp dụng voucher'], 400);
            }

            // Tính giảm giá
            $discount = $voucher->type == 'percent'
                ? min(($validatedData['total_amount'] * $voucher->discount_percent) / 100, $voucher->max_discount_amount ?? PHP_INT_MAX)
                : $voucher->amount;

            // Tính tổng giá trị sau giảm
            $finalAmount = max(0, $validatedData['total_amount'] - $discount);

            // Trả về kết quả
            return response()->json([
                'message' => 'Voucher áp dụng thành công',
                'discount' => $discount,
                'final_total' => $finalAmount,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi áp dụng voucher',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
    //
    public function getValidVouchers(Request $request)
    {
        $amount = (int) $request->input('amount');

        $now = Carbon::now();

        $vouchers = Voucher::query()
            ->where('is_active', true)
            ->where('start_date', '<=', $now)
            ->where(function ($query) use ($now) {
                $query->whereNull('expiry_date')
                    ->orWhere('expiry_date', '>=', $now);
            })
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereNull('usage_limit')
                        ->orWhere('usage_limit', '<=', 0);
                })->orWhereColumn('times_used', '<', 'usage_limit');
            })
            ->where(function ($query) use ($amount) {
                $query->whereNull('min_product_price')
                    ->orWhere('min_product_price', '<=', $amount);
            })
            ->orderByDesc('created_at')
            ->get();

        return response()->json($vouchers, 200);
    }
}
