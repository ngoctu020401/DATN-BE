<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\OrderStatus;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    //
    public function index()
    {
        $orders = Order::with('status', 'paymentStatus')->paginate(20);
        return response()->json($orders, 200);
    }
    public function show($id)
    {
        $order = Order::with([
            'items.product',          // Nếu có quan hệ với Product trong OrderItem
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
    $request->validate([
        'new_status_id' => 'required|exists:order_statuses,id',
    ]);

    $order = Order::findOrFail($orderId);
    $currentStatusId = $order->order_status_id;
    $newStatusId = $request->new_status_id;

    // Lấy next_status từ bảng order_statuses
    $currentStatus = OrderStatus::find($currentStatusId);
    $allowedNextStatus = json_decode($currentStatus->next_status, true);

    if (!in_array($newStatusId, $allowedNextStatus)) {
        return response()->json([
            'message' => 'Trạng thái không hợp lệ để chuyển tiếp từ trạng thái hiện tại.',
        ], 422);
    }

    // Cập nhật trạng thái mới
    $order->order_status_id = $newStatusId;
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

}
