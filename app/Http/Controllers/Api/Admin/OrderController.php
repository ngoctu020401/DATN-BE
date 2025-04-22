<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\OrderStatus;
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
    public function index()
    {
        $orders = Order::with('status', 'paymentStatus')->paginate(20);
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
            'code' => $order->order_code,
            'name' => $order->name,
            'phone' => $order->phone,
            'email' => $order->email,
            'address' => $order->address,
            'note' => $order->note,
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
                    'note' => $history->note,
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
        $data = $request->validate([
            'new_status_id' => 'required|exists:order_statuses,id',
            'cancel_reason' => 'nullable|string|max:255',
        ]);

        $order = Order::findOrFail($orderId);
        $nowStatusId = $order->order_status_id; // status id hiện tại
        $newStatusId = $data['new_status_id'];

        // Lấy next_status từ bảng order_statuses
        $currentStatus = OrderStatus::find($nowStatusId);
        $allowedNextStatus = json_decode($currentStatus->next_status, true);

        if (!in_array($newStatusId, $allowedNextStatus)) {
            return response()->json([
                'message' => 'Trạng thái không hợp lệ để chuyển tiếp từ trạng thái hiện tại.',
            ], 422);
        }

        // Nếu là trạng thái "Đã huỷ" thì bắt buộc phải có lý do
        if ($newStatusId == 6 && !$data['cancel_reason']) {
            return response()->json([
                'message' => 'Vui lòng nhập lý do huỷ đơn hàng.',
            ], 422);
        }

        // Cập nhật trạng thái + lý do huỷ 
        $order->order_status_id = $newStatusId;
        $order->cancel_reason = $newStatusId == 6 ? $data['cancel_reason'] : null;
        $order->save();

        // Ghi lịch sử trạng thái
        OrderHistory::create([
            'order_id' => $order->id,
            'order_status_id' => $newStatusId,
        ]);

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

        // Cập nhật trạng thái "refunded", lưu ảnh và thời gian
        $refund->update([
            'status' => 'refunded',
            'refunded_at' => now(),
            'refund_proof_image' => $path,
        ]);

        // Đồng thời cập nhật trạng thái đơn hàng nếu cần
        $refund->order->update(['order_status_id' => 8, 'payment_status_id' => 3]); // 8 = Hoàn tiền thành công

        return response()->json([
            'message' => 'Đã xác nhận hoàn tiền thành công.',
        ]);
    }
}
