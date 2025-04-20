<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentOnline;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderClientController extends Controller
{

    public function store(Request $request)
    {
        $request->validate([
            'cart_item_ids' => 'required|array',
            'cart_item_ids.*' => 'integer|exists:cart_items,id',
            'payment_method' => 'required|in:cod,vnpay',
            'shipping_address' => 'required|string',
            'shipping_name' => 'required|string|max:255',
            'shipping_phone' => 'required|string|max:20',
            'shipping_email' => 'nullable|email',
            'note' => 'nullable|string'
        ]);

        $user = Auth::user();
        $cartItemIds = $request->cart_item_ids;
        $paymentMethod = $request->payment_method;

        $cartItems = CartItem::with('productVariation')
            ->where('cart_id', $user->cart->id)
            ->whereIn('id', $cartItemIds)
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['message' => 'Không tìm thấy sản phẩm hợp lệ trong giỏ hàng.'], 400);
        }

        $totalAmount = 0;
        foreach ($cartItems as $item) {
            if ($item->quantity > $item->productVariation->stock_quantity) {
                return response()->json([
                    'message' => "Sản phẩm {$item->productVariation->name} không đủ hàng tồn."], 400);
            }
            $totalAmount += $item->quantity * $item->productVariation->price;
        }

        DB::beginTransaction();
        try {
            $order = Order::create([
                'user_id' => $user->id,
                'code' => 'ORD' . time(),
                'total_amount' => $totalAmount,
                'final_amount' => $totalAmount, // có thể trừ giảm giá sau
                'payment_method' => $paymentMethod,
                'shipping_address' => $request->shipping_address,
                'shipping_name' => $request->shipping_name,
                'shipping_phone' => $request->shipping_phone,
                'shipping_email' => $request->shipping_email,
                'note' => $request->note,
                'order_status_id' => 1,
                'payment_status_id' => 1,
            ]);

            foreach ($cartItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->productVariation->product_id,
                    'variation_id' => $item->variation_id,
                    'price' => $item->productVariation->price,
                    'quantity' => $item->quantity,
                ]);

                $item->productVariation->decrement('stock_quantity', $item->quantity);
            }

            if ($paymentMethod === 'vnpay') {
                $paymentUrl = '123';
                $order->update(['payment_url' => $paymentUrl]);
            }

            // Xóa cart items đã đặt
            CartItem::whereIn('id', $cartItemIds)->delete();

            DB::commit();

            return response()->json([
                'message' => 'Tạo đơn hàng thành công',
                'order_code' => $order->code,
                'payment_url' => $order->payment_url ?? null
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Tạo đơn hàng thất bại',
                'error' => $th->getMessage()
            ], 500);
        }
    }
    //Xử lí thanh toán
    public function vnpayReturn(Request $request)
    {

        $vnp_HashSecret = "9X1HLVJCZ6U4VRCTEAJBSRDGJDDANXPW";
        $vnp_SecureHash = $request['vnp_SecureHash'];
        $inputData = array();
        foreach ($_GET as $key => $value) {
            if (substr($key, 0, 4) == "vnp_") {
                $inputData[$key] = $value;
            }
        }

        unset($inputData['vnp_SecureHash']);
        ksort($inputData);
        $i = 0;
        $hashData = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashData = $hashData . '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashData = $hashData . urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
        }

        $secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);

        if ($secureHash === $vnp_SecureHash) {
            if ($request['vnp_ResponseCode'] == '00') {
                // Giao dịch thành công, cập nhật trạng thái đơn hàng
                $order = Order::where('order_code', $request['vnp_TxnRef'])->first();
                if ($order) {
                    $order->update(['status_payment' => 2]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Thanh toán thành công',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Thanh toán thất bại',
                ]);
            }
        }
    }
    //Thanh toán online
    public function createPaymentUrl($order,$expireInMinutes)
    {
        //Khai báo biến
        $vnp_TmnCode = 'OFRCXR48';
        $vnp_ReturnUrl = '';
        $vnp_Url = 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html';
        $vnp_HashSecret = 'XBB6GOAPO5O5ARJ5FF2JU658OGNMIQWZ'; 
        //
        $vnp_TxnRef = $order->code;
        $vnp_OrderInfo = "Thanh toán hóa đơn " . $order->order_code;
        $vnp_OrderType = "100002";
        $vnp_Amount = $order->final_amount  * 100;
        $vnp_Locale = "VN";
        $vnp_IpAddr = request()->ip();
        $createDate = Carbon::now(); // thời điểm khởi tạo
        $expireDate = $createDate->copy()->addMinutes($expireInMinutes);
        $inputData = [
            "vnp_Version" => "2.1.0",
            "vnp_TmnCode" => $vnp_TmnCode,
            "vnp_Amount" => $vnp_Amount,
            "vnp_Command" => "pay",
            "vnp_CreateDate" => date('YmdHis'),
            "vnp_CurrCode" => "VND",
            "vnp_IpAddr" => $vnp_IpAddr,
            "vnp_Locale" => $vnp_Locale,
            "vnp_OrderInfo" => $vnp_OrderInfo,
            "vnp_OrderType" => 'other',
            "vnp_ReturnUrl" => $vnp_ReturnUrl,
            "vnp_TxnRef" => $vnp_TxnRef,
            "vnp_CreateDate" => $createDate->format('YmdHis'),
            "vnp_ExpireDate" => $expireDate->format('YmdHis'),
        ];

        ksort($inputData);
        $query = "";
        $hashdata = "";
        foreach ($inputData as $key => $value) {
            $hashdata .= ($hashdata ? '&' : '') . urlencode($key) . "=" . urlencode($value);
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }

        $vnpSecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
        $query .= 'vnp_SecureHash=' . $vnpSecureHash;

        return $vnp_Url . "?" . $query;
    }
}
