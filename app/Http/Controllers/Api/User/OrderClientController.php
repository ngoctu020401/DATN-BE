<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentOnline;
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

}
