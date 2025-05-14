<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\OrderStatus;
use App\Models\ProductVariation;
use App\Models\RefundRequest;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    ////
    public function orderStatus()
    {
        $status = OrderStatus::all();
        return response()->json($status, 200);
    }
    //
    public function index(Request $request)
    {
        $query = Order::with('status', 'paymentStatus');

        // Lọc theo trạng thái đơn hàng
        if ($request->has('status_id')) {
            $query->where('order_status_id', $request->status_id);
        }

        // Tìm kiếm theo mã đơn hàng
        if ($request->has('order_code')) {
            $query->where('order_code', 'like', '%' . $request->order_code . '%');
        }

        // Tìm kiếm theo tên khách hàng
        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        // Tìm kiếm theo số điện thoại
        if ($request->has('phone')) {
            $query->where('phone', 'like', '%' . $request->phone . '%');
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(20);
        return response()->json($orders, 200);
    }
    public function show($id)
    {
        $order = Order::with([
            'items',          // Nếu có quan hệ với Product trong OrderItem
            'user',
            'status',
            'paymentStatus',
            'histories.status'        // Nếu lịch sử có quan hệ với status
        ])->findOrFail($id);

        return response()->json([
            'id' => $order->id,
            'user_id' => $order->user_id,
            'order_code' => $order->order_code,
            'email' => $order->email,
            'phone' => $order->phone,
            'name' => $order->name,
            'address' => $order->address,
            'note' => $order->note,
            'cancel_reason' => $order->cancel_reason,
            'total_amount' => $order->total_amount,
            'shipping' => $order->shipping,
            'final_amount' => $order->final_amount,
            'payment_url' => $order->payment_url,
            'payment_method' => $order->payment_method,
            'refund' => $order->refundRequest,
            'status' => [
                'id' => $order->status->id ?? null,
                'name' => $order->status->name ?? 'Không xác định',
            ],
            'payment_status' => [
                'id' => $order->paymentStatus->id ?? null,
                'name' => $order->paymentStatus->name ?? 'Không xác định',
            ],
            'payment_method' => $order->payment_method,
            'payment_url' => $order->payment_url,
            'shipping' => $order->shipping,
            'amounts' => [
                'total' => $order->total_amount,
                'final' => $order->final_amount,
            ],
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
            'histories' => $order->histories->map(function ($history) {
                return [
                    'id' => $history->id,
                    'status' => $history->status->name ?? 'Không xác định',
                    'user_change' => $history->user_change,
                    'created_at' => $history->created_at->format('d-m-Y H:i'),
                ];
            }),
            'created_at' => $order->created_at->format('d-m-Y H:i'),
            'updated_at' => $order->updated_at->format('d-m-Y H:i'),
        ], 200);
    }
    //
    public function changeStatus(Request $request, $orderId)
    {
        $user = auth('sanctum')->user();
        //  Validate dữ liệu đầu vào
        $data = $request->validate([
            'new_status_id' => 'required|exists:order_statuses,id',
            'cancel_reason' => 'nullable|string|max:255',
        ]);

        //  Lấy đơn hàng
        $order = Order::findOrFail($orderId);
        $nowStatusId = $order->order_status_id;
        $newStatusId = $data['new_status_id'];

        //  Lấy danh sách trạng thái kế tiếp được phép từ bảng order_statuses
        $currentStatus = OrderStatus::find($nowStatusId);
        $allowedNextStatus = json_decode($currentStatus->next_status, true);

        //  Nếu trạng thái mới không nằm trong danh sách được phép → báo lỗi
        if (!in_array($newStatusId, $allowedNextStatus)) { // inarray hoạt động theo kiểu kiểm tra xem giá trị truyền vào có = giá trị nào trong mảng hay không
            return response()->json([ // (3,[3])
                'message' => 'Trạng thái không hợp lệ để chuyển tiếp từ trạng thái hiện tại.',
            ], 422);
        }

        //  Nếu chuyển sang trạng thái "Đã huỷ" thì bắt buộc phải có lý do
        if ($newStatusId == 6 && !$data['cancel_reason']) {
            return response()->json([
                'message' => 'Vui lòng nhập lý do huỷ đơn hàng.',
            ], 422);
        }
        //Cộng lại số lượng
        if ($newStatusId == 6) {
            foreach ($order->items as $item) {
                if ($item->variation_id) { // Nếu có variation id thì mới cộng vào kho
                    $variant = ProductVariation::find($item->variation_id);
                    if ($variant) {
                        $variant->increment('stock_quantity', $item->quantity);
                    }
                }
            }
        }

        //  Nếu đơn hàng dùng VNPAY mà chưa thanh toán thì không cho chuyển sang "Đã xác nhận"
        if ($newStatusId == 2 && $order->payment_method == 'vnpay' && $order->payment_status_id == 1) {
            return response()->json([
                'message' => 'Đơn hàng chưa thanh toán',
            ], 422);
        }

        //  Ngăn chuyển thẳng từ Đã giao sang "Hoàn tất" (5) hoặc "Yêu cầu hoàn tiền" (7) vì admin không có quyền này
        if (in_array($newStatusId, [5, 7, 8])) {
            return response()->json([
                'message' => 'Không thể chuyển trạng thái không đúng luồng',
            ], 422);
        }

        //  Nếu giao hàng thành công (trạng thái 4) & phương thức thanh toán là COD → cập nhật là đã thanh toán
        if ($newStatusId == 4 && $order->payment_method == 'cod') {
            $order->payment_status_id = 2; //  Ghi nhớ: dùng "=" chứ không phải "=="
        }

        //  Cập nhật trạng thái mới và lý do huỷ (nếu có)
        $order->order_status_id = $newStatusId;
        $order->cancel_reason = $newStatusId == 6 ? $data['cancel_reason'] : null;
        $order->save();

        //  Ghi vào bảng lịch sử trạng thái
        OrderHistory::create([
            'order_id' => $order->id,
            'order_status_id' => $newStatusId,
            'user_change' => $user->role . ' - ' . $user->email
        ]);

        //  Trả về phản hồi
        return response()->json([
            'message' => 'Cập nhật trạng thái thành công.',
            'new_status' => $order->status->name ?? null,
        ]);
    }

    /**
     * Admin duyệt yêu cầu hoàn tiền
     */
    public function approveRefund($id)
    {
        // Chỉ tìm các yêu cầu đang ở trạng thái "pending"
        $refund = RefundRequest::where('status', 'pending')->findOrFail($id);

        // Cập nhật trạng thái sang "approved" và thời gian duyệt
        $refund->status = 'approved';
        $refund->approved_at = now();
        $refund->save();

        return response()->json([
            'message' => 'Đã duyệt yêu cầu hoàn tiền.',
            'status' => $refund->status,
        ]);
    }

    /**
     * Admin từ chối yêu cầu hoàn tiền
     */
    public function rejectRefund(Request $request, $id)
    {
        // Bắt buộc phải nhập lý do từ chối
        $request->validate([
            'reject_reason' => 'required|string|max:255',
        ]);

        // Chỉ từ chối yêu cầu ở trạng thái "pending"
        $refund = RefundRequest::where('status', 'pending')->findOrFail($id);

        // Cập nhật trạng thái "rejected" và lưu lý do
        $refund->status = 'rejected';
        $refund->reject_reason = $request->reject_reason;
        $refund->save();

        return response()->json([
            'message' => 'Đã từ chối yêu cầu hoàn tiền'
        ], 200);
    }

    /**
     * Admin xác nhận đã hoàn tiền và upload ảnh minh chứng
     */
    public function markAsRefunded(Request $request, $id)
    {
        try {
            //code...
            $user = auth('sanctum')->user();
            // Yêu cầu phải upload ảnh minh chứng (jpg/jpeg/png/pdf)
            $request->validate([
                'refund_proof_image' => 'required|image|mimes:jpg,jpeg,png,pdf|max:2048',
            ]);

            // Chỉ xử lý yêu cầu đã được duyệt (approved)
            $refund = RefundRequest::where('status', 'approved')->findOrFail($id);

            // Xử lý file upload và đặt tên file theo thời gian hiện tại
            $file = $request->file('refund_proof_image');
            $filename = 'refund_' . now()->format('Ymd_His') . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('uploads', $filename, 'public');
            // Cộng lại kho hàng
            if ($refund->type == 'return_after_received') { // nẾU ĐƠN HÀNG LÀ HOÀN TRẢ SAU KHI GIAO
                foreach ($refund->order->items as $item) {
                    if ($item->variation_id) {
                        $variant = ProductVariation::find($item->variation_id);
                        if ($variant) {
                            $variant->increment('stock_quantity', $item->quantity);
                        }
                    }
                }
            }

            // Cập nhật trạng thái "refunded", lưu ảnh và thời gian
            $refund->update([
                'status' => 'refunded',
                'refunded_at' => now(),
                'refund_proof_image' => $path,
            ]);

            // Đồng thời cập nhật trạng thái đơn hàng nếu cần
            $refund->order->update(['order_status_id' => 8, 'payment_status_id' => 3]); // 8 = Hoàn tiền thành công
            OrderHistory::create([
                'order_id' => $refund->order->id,
                'order_status_id' => 8,
                'user_change' => $user->role . ' - ' . $user->email
            ]);
            return response()->json([
                'message' => 'Đã xác nhận hoàn tiền thành công.',
            ]);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'message' => 'Lỗi',
            ], 422);
        }
    }
}
