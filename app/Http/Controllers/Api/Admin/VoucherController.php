<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\Request;

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
}
