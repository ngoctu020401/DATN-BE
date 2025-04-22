<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\OrderItem;
use App\Models\PaymentOnline;
use App\Models\RefundRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

        $user = auth('sanctum')->user();
        $cartItemIds = $request->cart_item_ids;
        $paymentMethod = $request->payment_method;

        $cartItems = CartItem::with('variation')
            ->where('cart_id', $user->cart->id)
            ->whereIn('id', $cartItemIds)
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['message' => 'Không tìm thấy sản phẩm hợp lệ trong giỏ hàng.'], 400);
        }

        $totalAmount = 0;
        foreach ($cartItems as $item) {
            if ($item->quantity > $item->variation->stock_quantity) {
                return response()->json([
                    'message' => "Sản phẩm {$item->variation->name} không đủ hàng tồn."
                ], 400);
            }
            $totalAmount += $item->quantity * $item->variation->price;
        }

        DB::beginTransaction();
        try {
            $order = Order::create([
                'user_id' => $user->id,
                'order_code' => 'ORD' . time(),
                'total_amount' => $totalAmount,
                'final_amount' => $totalAmount, // có thể trừ giảm giá sau
                'payment_method' => $paymentMethod,
                'address' => $request->shipping_address,
                'name' => $request->shipping_name,
                'phone' => $request->shipping_phone,
                'email' => $request->shipping_email,
                'note' => $request->note,
                'order_status_id' => 1,
                'payment_status_id' => 1,
                'shipping' => 30000
            ]);

            foreach ($cartItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_name' => $item->variation->product->name,
                    'variation_id' => $item->variation_id,
                    'product_price' => $item->variation->price,
                    'quantity' => $item->quantity,
                    'image' => $item->variation->product->main_image,
                    'variation' => $item->variation->getVariation()
                ]);

                $item->variation->decrement('stock_quantity', $item->quantity);
            }

            if ($paymentMethod === 'vnpay') {
                $paymentUrl = $this->createPaymentUrl($order, 60);
                $order->update(['payment_url' => $paymentUrl]);
            }

            // Xóa cart items đã đặt
            CartItem::whereIn('id', $cartItemIds)->delete();
            OrderHistory::create(
                [
                    'order_id' => $order->id,
                    'order_status_id' => 1
                ]
            );
            DB::commit();

            return response()->json([
                'message' => 'Tạo đơn hàng thành công',
                'order_code' => $order->order_code,
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

        $vnp_HashSecret = "XBB6GOAPO5O5ARJ5FF2JU658OGNMIQWZ";
        $vnp_SecureHash = $request['vnp_SecureHash'];
        $order = Order::where('order_code', $request['vnp_TxnRef'])->first();
        PaymentOnline::create([
            'order_id' => $order->id,
            'amount' => $request->input('vnp_Amount') / 100,
            'vnp_transaction_no' => $request->input('vnp_TransactionNo'),
            'vnp_bank_code' => $request->input('vnp_BankCode'),
            'vnp_bank_tran_no' => $request->input('vnp_BankTranNo'),
            'vnp_pay_date' => Carbon::createFromFormat('YmdHis', $request->input('vnp_PayDate')),
            'vnp_card_type' => $request->input('vnp_CardType'),
            'vnp_response_code' => $request->input('vnp_ResponseCode'),
        ]);
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

                if ($order) {
                    $order->update(['payment_status_id' => 2]);
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
    public function createPaymentUrl($order, $expireInMinutes)
    {
        //Khai báo biến
        $vnp_TmnCode = 'OFRCXR48';
        $vnp_ReturnUrl = 'http://localhost:5173/thanks';
        $vnp_Url = 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html';
        $vnp_HashSecret = 'XBB6GOAPO5O5ARJ5FF2JU658OGNMIQWZ';
        //
        $vnp_TxnRef = $order->order_code;
        $vnp_OrderInfo = "Thanh toán hóa đơn " . $order->order_code;
        $vnp_OrderType = "100002";
        $vnp_Amount = $order->final_amount * 100;
        $vnp_Locale = "VN";
        $vnp_IpAddr = $_SERVER['REMOTE_ADDR'];
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
            "vnp_OrderType" => $vnp_OrderType,
            "vnp_ReturnUrl" => $vnp_ReturnUrl,
            "vnp_TxnRef" => $vnp_TxnRef
        ];
        ksort($inputData);
        $query = "";
        $i = 0;
        $hashdata = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashdata .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }
        $vnp_Url = $vnp_Url . "?" . $query;
        if (isset($vnp_HashSecret)) {
            $vnpSecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
            $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
        }
        return $vnp_Url;
    }
    //LỊch sử đơn hàng
    public function getUserOrderHistory(Request $request)
    {
        $status = $request->input('status', 'all');
        $user = auth('sanctum')->user();
        $userId = $user->id ;

        $query = Order::with([ 'refundRequest', 'items'])
            ->where('user_id', $userId);

        // Áp dụng bộ lọc theo status
        switch ($status) {
            case 'waiting_confirm':
                $query->where('order_status_id', 1); //  chờ xác nhận
                break;
            case 'waiting_payment':
                $query->where('payment_method', 'vnpay')->where('payment_status_id', 1); // chờ thanh toán
                break;
            case 'confirmed':
                $query->where('order_status_id', 2); // Đã xác nhận
                break;
            case 'shipping':
                $query->where('order_status_id', 3); // đang giao
                break;
            case 'shipped':
                $query->where('order_status_id', 4); // đã giao
                break;
            case 'completed':
                $query->where('order_status_id', 5); // hoàn tất
                break;
            case 'refunding': // Hoàn hàng trả tiền
                $query->where(function ($q) {
                    $q->whereIn('order_status_id', [7, 8])
                        ->orWhereHas('refundRequest', fn($qr) => $qr->whereIn('status', ['pending', 'approved']));
                });
                break;
            case 'cancelled': // đã hủy
                $query->where('order_status_id', 6);
                break;
            default:
                break;
        }

        $orders = $query->latest()->get();

        return response()->json([
            'data' => $orders->map(function ($order) {
                $firstItem = $order->items->first();
                $totalItems = $order->items->count();
                $extraItemsCount = max($totalItems - 1, 0);

                return [
                    'id' => $order->id,
                    'order_code' => $order->order_code,
                    'final_amount' => $order->final_amount,
                    'status_id' => $order->status->name,
                    'payment_method' => $order->payment_method,
                    'created_at' => $order->created_at->format('d-m-Y H:i'),
                    'first_item' => $firstItem ? [
                        'product_name' => $firstItem->product_name,
                        'quantity' => $firstItem->quantity,
                        'image' => $firstItem->image ?? null,
                    ] : null,
                    'extra_items_count' => $extraItemsCount,
                ];
            }),
        ]);
    }
    //Chi tiết đơn hàng
    public function show($id)
    {
        $user = auth()->user();

        $order = Order::with([
            'items.product',
            'status',
            'paymentStatus',
            'refundRequest',
        ])->where('user_id', $user->id)->findOrFail($id);

        $refund = $order->refundRequest;

        return response()->json([
            'id' => $order->id,
            'code' => $order->order_code,
            'status' => [
                'id' => $order->status->id ?? null,
                'name' => $order->status->name ?? null,
            ],
            'payment_status' => [
                'id' => $order->paymentStatus->id ?? null,
                'name' => $order->paymentStatus->name ?? null,
            ],
            'payment_method' => $order->payment_method,
            'total_amount' => $order->total_amount,
            'final_amount' => $order->final_amount,
            'shipping' => $order->shipping,
            'note' => $order->note,
            'cancel_reason' => $order->cancel_reason,
            'created_at' => $order->created_at->format('d-m-Y H:i'),

            // Sản phẩm trong đơn
            'items' => $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'variation_id' => $item->variation_id,
                    'product_price' => $item->product_price,
                    'quantity' => $item->quantity,
                    'image' => $item->image,
                    'variation' => $item->variation
                ];
            }),
            // lịch sử đơn hàng 
            'histories' => $order->histories->map(function ($history) {
                return [
                    'id' => $history->id,
                    'status' => $history->status->name ?? 'Không xác định',
                    'note' => $history->note,
                    'created_at' => $history->created_at->format('d-m-Y H:i'),
                ];
            }),
            // Yêu cầu hoàn tiền nếu có
            'refund_request' => $refund ? [
                'id' => $refund->id,
                'type' => $refund->type,
                'amount' => $refund->amount,
                'reason' => $refund->reason,
                'status' => $refund->status,
                'reject_reason' => $refund->status === 'rejected' ? $refund->reject_reason : null,
                'approved_at' => $refund->approved_at?->format('d-m-Y H:i'),
                'refunded_at' => $refund->refunded_at?->format('d-m-Y H:i'),
                'proof_image_url' => $refund->refund_proof_image ? asset('storage/' . $refund->refund_proof_image) : null,
            ] : null,
        ]);
    }

    public function cancel(Request $request, $id)
    {
        $user = auth()->user();

        $request->validate([
            'cancel_reason' => 'required|string|max:255',
        ]);

        $order = Order::where('user_id', $user->id)
            ->whereIn('order_status_id', [1, 2])
            ->findOrFail($id);

        // Trường hợp đã thanh toán online (VNPAY)
        $needRefund = $order->payment_method === 'vnpay' && in_array($order->payment_status_id, [2]); // 2 = đã thanh toán

        // Huỷ đơn hàng
        $order->update([
            'order_status_id' => 6,
            'cancel_reason' => $request->cancel_reason,
        ]);

        // Ghi lịch sử huỷ đơn
        OrderHistory::create([
            'order_id' => $order->id,
            'order_status_id' => 6,
            'note' => 'Khách hàng huỷ đơn: ' . $request->cancel_reason,
        ]);

        // Nếu cần hoàn tiền thủ công (vì đã thanh toán VNPAY)
        if ($needRefund && !$order->refundRequest) {
            RefundRequest::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'type' => 'cancel_before_shipping',
                'amount' => $order->final_amount,
                'reason' => 'Tự động tạo và duyệt yêu cầu hoàn tiền do khách huỷ đơn hàng đã thanh toán.',
                'status' => 'approved',
                'approved_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Đơn hàng đã được huỷ thành công.',
            'refund_created' => $needRefund,
        ]);
    }


    //  Thanh toán lại
    public function retryPayment($id)
    {
        $user = auth()->user();

        $order = Order::where('user_id', $user->id)
            ->where('payment_method', 'vnpay')
            ->whereIn('payment_status_id', [1]) // chưa thanh toán
            ->findOrFail($id);

        // Kiểm tra xem link đã tạo quá 60 phút chưa
        $expired = $order->created_at->diffInSeconds(now()) > 3600;

        if ($expired || !$order->payment_url) {
            $newUrl = $this->createPaymentUrl($order, 60);

            $order->update([
                'payment_url' => $newUrl,
            ]);
        }

        return response()->json([
            'message' => 'Lấy link thanh toán thành công.',
            'payment_url' => $order->payment_url,
            'expired' => $expired,
        ]);
    }

    // Yêu cầu hoàn tiền trả hàng
    public function requestRefund(Request $request, $orderId)
    {
        $user = auth()->user();

        $request->validate([
            'type' => 'required|in:cancel_before_shipping,return_after_received',
            'amount' => 'required|numeric|min:0',
            'reason' => 'required|string|max:255',
            'bank_name' => 'required|string|max:100',
            'bank_account_name' => 'required|string|max:100',
            'bank_account_number' => 'required|string|max:50',
            'images.*' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $order = Order::where('user_id', $user->id)->findOrFail($orderId);
        //
        if ($order->order_status_id !== 4) {
            return response()->json([
                'message' => 'Chỉ đơn hàng đã giao mới được yêu cầu hoàn tiền.',
            ], 422);
        }
        // Kiểm tra đã có yêu cầu hoàn tiền chưa
        if ($order->refundRequest) {
            return response()->json([
                'message' => 'Đơn hàng đã có yêu cầu hoàn tiền.',
            ], 422);
        }
        // Xử lý ảnh minh chứng
        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $filename = 'refund_' . now()->format('Ymd_His') . '_' . Str::uuid() . '.' . $image->getClientOriginalExtension();
                $path = $image->storeAs('uploads', $filename, 'public');
                $imagePaths[] = $path;
            }
        }

        // Tạo yêu cầu hoàn tiền
        RefundRequest::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'type' => $request->type,
            'amount' => $request->amount,
            'reason' => $request->reason,
            'images' => $imagePaths,
            'status' => 'pending',
            'bank_name' => $request->bank_name,
            'bank_account_name' => $request->bank_account_name,
            'bank_account_number' => $request->bank_account_number,
        ]);

        return response()->json([
            'message' => 'Đã gửi yêu cầu hoàn tiền thành công.',
        ]);
    }

    // Hoàn tất đơn hàng
    public function complete($id)
    {
        $user = auth()->user();

        $order = Order::where('user_id', $user->id)
            ->where('order_status_id', 4) // chỉ cho phép hoàn tất khi đã giao
            ->find($id);
        if (!$order) {
            return response()->json([
                'message' => 'Không thể hoàn tất đơn hàng. Đơn không tồn tại hoặc không ở trạng thái cho phép.',
            ], 404);
        }
        $order->update([
            'order_status_id' => 5, 
            'closed_at' => now()
        ]);

        OrderHistory::create([
            'order_id' => $order->id,
            'order_status_id' => 5,
        ]);

        return response()->json([
            'message' => 'Đơn hàng đã được hoàn tất.',
        ]);
    }
}
