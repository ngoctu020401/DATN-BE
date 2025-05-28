<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Jobs\SendOrderConfirmationMail;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\OrderItem;
use App\Models\PaymentOnline;
use App\Models\ProductVariation;
use App\Models\RefundRequest;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderClientController extends Controller
{

    public function store(Request $request)
    {
        //1: Validate dữ liệu đầu vào
        $request->validate([
            'cart_item_ids' => 'required|array',                  // Danh sách ID cart item phải là array
            'cart_item_ids.*' => 'integer|exists:cart_items,id',   // Các ID trong array phải tồn tại trong bảng cart_items
            'payment_method' => 'required|in:cod,vnpay',           // Chỉ chấp nhận COD hoặc VNPAY
            'shipping_address' => 'required|string',              // Địa chỉ giao hàng
            'shipping_name' => 'required|string|max:255',         // Tên người nhận
            'shipping_phone' => 'required|string|max:20',         // Số điện thoại nhận hàng
            'shipping_email' => 'nullable|email',                 // Email có thể có hoặc không
            'note' => 'nullable|string',                          // Ghi chú đơn hàng
            'discount_amount' => 'nullable',
            'voucher_code' => 'nullable|string|exists:vouchers,code' // Mã voucher (nếu có)
        ]);

        //2: Lấy thông tin user đang đăng nhập
        $user = auth('sanctum')->user();
        $userId = $user->id;

        //3: Lấy thông tin các cart item được chọn mua
        $cartItemIds = $request->cart_item_ids;
        $paymentMethod = $request->payment_method;

        $cartItems = CartItem::with('variation')
            ->where('cart_id', $user->cart->id)
            ->whereIn('id', $cartItemIds)
            ->get();

        // Nếu không tìm thấy sản phẩm hợp lệ, trả lỗi
        if ($cartItems->isEmpty()) {
            return response()->json(['message' => 'Không tìm thấy sản phẩm hợp lệ trong giỏ hàng'], 400);
        }

        //4: Tính tổng tiền đơn hàng
        $totalAmount = $cartItems->sum(function ($item) {
            return ($item->variation->sale_price ?? $item->variation->price) * $item->quantity;
        });

        //5: Kiểm tra và xử lý voucher nếu có
        $voucher = null;
        $discountAmount = $request->discount_amount ?? 0;

        if ($request->has('voucher_code')) {
            $voucher = Voucher::where('code', $request->voucher_code)
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('expiry_date')
                        ->orWhere('expiry_date', '>=', now());
                })
                ->where(function ($query) {
                    $query->whereNull('usage_limit')
                        ->orWhere('usage_limit', '>', 0);
                })
                ->first();

            if (!$voucher) {
                return response()->json(['message' => 'Mã voucher không hợp lệ hoặc đã hết hạn'], 400);
            }

            // Kiểm tra giá trị đơn hàng tối thiểu
            if ($voucher->min_product_price && $totalAmount < $voucher->min_product_price) {
                return response()->json([
                    'message' => 'Giá trị đơn hàng không đủ điều kiện áp dụng voucher',
                    'min_amount' => $voucher->min_product_price
                ], 400);
            }
        }

        //6: Tạo đơn hàng mới
        $order = Order::create([
            'user_id' => $userId,
            'order_code' => 'ORD' . time(),   // Mã đơn hàng dựa trên timestamp
            'total_amount' => $totalAmount,   // Tổng tiền đơn chưa ship
            'discount_amount' => $discountAmount, // Số tiền giảm giá từ request
            'final_amount' => $totalAmount + 30000 - $discountAmount, // Tổng thanh toán đã cộng phí ship và trừ giảm giá
            'payment_method' => $paymentMethod,
            'address' => $request->shipping_address,
            'name' => $request->shipping_name,
            'phone' => $request->shipping_phone,
            'email' => $request->shipping_email,
            'note' => $request->note,
            'order_status_id' => 1,            // Mặc định trạng thái đơn hàng: Đang chờ xác nhận
            'payment_status_id' => 1,          // Mặc định trạng thái thanh toán: Chưa thanh toán
            'shipping' => 30000,               // Phí ship cố định
            'voucher_id' => $voucher ? $voucher->id : null, // Lưu voucher ID nếu có
        ]);

        // Nếu có voucher, tăng số lần sử dụng và giảm số lượng còn lại
        if ($voucher) {
            $voucher->increment('times_used');
            if ($voucher->usage_limit) {
                $voucher->decrement('usage_limit');
            }
        }

        //7: Lưu từng sản phẩm trong đơn hàng và trừ tồn kho
        foreach ($cartItems as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_name' => $item->variation->product->name,
                'variation_id' => $item->variation_id,
                'product_price' => ($item->variation->sale_price ?? 0) > 0
                    ? $item->variation->sale_price
                    : $item->variation->price,
                'quantity' => $item->quantity,
                'image' => $item->variation->product->main_image,
                'variation' => $item->variation->getVariation()
            ]);

            // Trừ số lượng tồn kho tương ứng
            $item->variation->decrement('stock_quantity', $item->quantity);
        }

        //8: Nếu thanh toán qua VNPAY, tạo URL thanh toán và cập nhật vào đơn
        if ($paymentMethod === 'vnpay') {
            $paymentUrl = $this->createPaymentUrl($order);
            $order->update(['payment_url' => $paymentUrl]);
        }

        //9: Xóa những cart item đã thanh toán khỏi giỏ hàng
        CartItem::whereIn('id', $cartItemIds)->delete();

        //10: Ghi log lịch sử đơn hàng
        OrderHistory::create([
            'order_id' => $order->id,
            'order_status_id' => 1,
            'user_change' => 'system'
        ]);

        //11: Commit transaction sau khi mọi thao tác thành công
        DB::commit();

        //12: Trả về dữ liệu đơn hàng thành công cho client
        return response()->json([
            'message' => 'Tạo đơn hàng thành công',
            'order_code' => $order->order_code,
            'payment_url' => $order->payment_url ?? null
        ], 201);
    }

    //Xử lí thanh toán thành công hay thất bại
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
        // Kiểm tra xem có đúng khóa bảo mật khônbg
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

        if ($secureHash === $vnp_SecureHash) { ////Nếu Khóa bảo mật đúng
            if ($request['vnp_ResponseCode'] == '00') { // Nếu mã respon == 00 nghĩa là thanh toán thanh công
                // Giao dịch thành công, cập nhật trạng thái đơn hàng

                if ($order) {
                    $order->update(['payment_status_id' => 2]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Thanh toán thành công',
                ]);
            } else { // Thông báo thất bại
                return response()->json([
                    'success' => false,
                    'message' => 'Thanh toán thất bại',
                ]);
            }
        }
    }
    //Thanh toán online
    public function createPaymentUrl($order)
    {
        //Khai báo biến
        $vnp_TmnCode = 'OFRCXR48'; //đc cung cấp bởi vnpay
        $vnp_ReturnUrl = 'http://localhost:5173/thanks'; // trang trả về kết quả khi thanh toán
        $vnp_Url = 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'; // đc cung cấp bởi vnpay để thanh toán
        $vnp_HashSecret = 'XBB6GOAPO5O5ARJ5FF2JU658OGNMIQWZ'; // đc cung cấp bởi vnpay
        //
        $vnp_TxnRef = $order->order_code; // mã đơn hagf
        $vnp_OrderInfo = "Thanh toán hóa đơn " . $order->order_code; // ghi chú
        $vnp_OrderType = "100002";
        $vnp_Amount = $order->final_amount * 100; // tổng tiền đơn hàng
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
        $userId = $user->id;

        $query = Order::with(['refundRequest', 'items'])
            ->where('user_id', $userId);

        // Áp dụng bộ lọc theo status
        switch ($status) {
            case 'waiting_confirm':
                $query->where('order_status_id', 1); //  chờ xác nhận
                break;
            case 'waiting_payment':
                $query->where('payment_method', 'vnpay')->where('payment_status_id', 1); // chờ thanh toán// thanh toán onl nhưng ng dùng chưa thanh toán
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
        $user = auth('sanctum')->user();
        $userId = $user->id;

        $order = Order::with([
            'items',
            'items.variant',
            'status',
            'paymentStatus',
            'refundRequest',
            'reviews',
            'voucher'
        ])->where('user_id', $userId)->findOrFail($id);

        $refund = $order->refundRequest;

        return response()->json([
            'id' => $order->id,
            'code' => $order->order_code,
            'name' => $order->name,
            'phone' => $order->phone,
            'email' => $order->email,
            'address' => $order->address,
            'note' => $order->note,
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
            'discount_amount' => $order->discount_amount,
            'final_amount' => $order->final_amount,
            'shipping' => $order->shipping,
            'note' => $order->note,
            'cancel_reason' => $order->cancel_reason,
            'created_at' => $order->created_at->format('d-m-Y H:i'),
            'voucher' => $order->voucher ? [
                'id' => $order->voucher->id,
                'code' => $order->voucher->code,
                'name' => $order->voucher->name,
                'discount_percent' => $order->voucher->discount_percent,
                'amount' => $order->voucher->amount,
                'type' => $order->voucher->type,
            ] : null,

            // Sản phẩm trong đơn
            'items' => $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'variation_id' => $item->variation_id,
                    'product_price' => $item->product_price,
                    'quantity' => $item->quantity,
                    'image' => $item->image,
                    'variation' => $item->variation,
                    'review' => $item->review
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
                'order_id' => $refund->order_id,
                'user_id' => $refund->user_id,
                'type' => $refund->type,
                'amount' => $refund->amount,
                'reason' => $refund->reason,
                'images' => $refund->images, // Trả về array
                'status' => $refund->status,
                'reject_reason' => $refund->reject_reason,
                'bank_name' => $refund->bank_name,
                'bank_account_name' => $refund->bank_account_name,
                'bank_account_number' => $refund->bank_account_number,
                'approved_at' => optional($refund->approved_at)->format('d-m-Y H:i'),
                'refunded_at' => optional($refund->refunded_at)->format('d-m-Y H:i'),
                'proof_image_url' => $refund->refund_proof_image
                    ? asset('storage/' . $refund->refund_proof_image)
                    : null,
            ] : null,


        ]);
    }

    public function cancel(Request $request, $id)
    {
        $user = auth('sanctum')->user();
        $userId = $user->id;

        $request->validate([
            'cancel_reason' => 'required|string|max:255',
            'bank_name' => 'nullable|string|max:100',
            'bank_account_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:50',
        ]);

        $order = Order::where('user_id', $userId)
            ->whereIn('order_status_id', [1, 2])
            ->findOrFail($id);

        // Kiểm tra nếu đơn hàng đã thanh toán thì yêu cầu thông tin ngân hàng
        if ($order->payment_status_id == 2) {
            if (!$request->bank_name || !$request->bank_account_name || !$request->bank_account_number) {
                return response()->json([
                    'message' => 'Vui lòng cung cấp đầy đủ thông tin ngân hàng để hoàn tiền.',
                    'errors' => [
                        'bank_name' => 'Tên ngân hàng là bắt buộc khi đơn hàng đã thanh toán.',
                        'bank_account_name' => 'Tên tài khoản là bắt buộc khi đơn hàng đã thanh toán.',
                        'bank_account_number' => 'Số tài khoản là bắt buộc khi đơn hàng đã thanh toán.'
                    ]
                ], 422);
            }
        }

        // Huỷ đơn hàng
        $order->update([
            'order_status_id' => 6,
            'cancel_reason' => $request->cancel_reason,
        ]);
        // Cộng lại số lượng
        foreach ($order->items as $item) {
            if ($item->variation_id) {
                $variant = ProductVariation::find($item->variation_id);
                if ($variant) {
                    $variant->increment('stock_quantity', $item->quantity);
                }
            }
        }
        // Hoàn lại voucher nếu đơn hàng có sử dụng voucher
        if ($order->voucher_id) {
            $voucher = Voucher::find($order->voucher_id);
            if ($voucher) {
                $voucher->decrement('times_used');
                if ($voucher->usage_limit) {
                    $voucher->increment('usage_limit');
                }
            }
        }
        // Ghi lịch sử huỷ đơn
        OrderHistory::create([
            'user_change' => $user->role . ' - ' . $user->email,
            'order_id' => $order->id,
            'order_status_id' => 6,
        ]);
        // Trường hợp đã thanh toán online (VNPAY)
        $needRefund = $order->payment_method === 'vnpay' && in_array($order->payment_status_id, [2]); // 2 = đã thanh toán
        // Nếu cần hoàn tiền thủ công (vì đã thanh toán VNPAY)
        if ($needRefund && !$order->refundRequest) {
            RefundRequest::create([
                'order_id' => $order->id,
                'user_id' => $userId,
                'type' => 'cancel_before_shipping',
                'amount' => $order->final_amount,
                'reason' => 'Tự động tạo và duyệt yêu cầu hoàn tiền do khách huỷ đơn hàng đã thanh toán.',
                'status' => 'approved',
                'approved_at' => now(),
                'bank_name' => $request->bank_name,
                'bank_account_name' => $request->bank_account_name,
                'bank_account_number' => $request->bank_account_number,
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
        $user = auth('sanctum')->user();
        $userId = $user->id;

        $order = Order::where('user_id', $userId)
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
        $user = auth('sanctum')->user();
        $userId = $user->id;

        $request->validate([
            'reason' => 'required|string|max:255',
            'bank_name' => 'required|string|max:100',
            'bank_account_name' => 'required|string|max:100',
            'bank_account_number' => 'required|string|max:50',
            'images.*' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $order = Order::where('user_id', $userId)->findOrFail($orderId);
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
            'user_id' => $userId,
            'type' => 'return_after_received',
            'amount' => $order->final_amount,
            'reason' => $request->reason,
            'images' => $imagePaths,
            'status' => 'pending',
            'bank_name' => $request->bank_name,
            'bank_account_name' => $request->bank_account_name,
            'bank_account_number' => $request->bank_account_number,
        ]);
        $order->update(['order_status_id' => 7]);
        OrderHistory::create(
            [
                'user_change' => $user->role . ' - ' . $user->email,
                'order_id' => $order->id,
                'order_status_id' => 7,
            ]
        );
        return response()->json([
            'message' => 'Đã gửi yêu cầu hoàn tiền thành công.',
        ]);
    }

    // Hoàn tất đơn hàng
    public function complete($id)
    {
        $user = auth('sanctum')->user();
        $userId = $user->id;

        $order = Order::where('user_id', $userId)
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
            'user_change' => $user->role . ' - ' . $user->email
        ]);

        return response()->json([
            'message' => 'Đơn hàng đã được hoàn tất.',
        ]);
    }
}
