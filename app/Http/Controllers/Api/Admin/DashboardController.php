<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Review;
use App\Models\Category;
use App\Models\Voucher;
use App\Models\RefundRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        try {
            // Validate thời gian đầu vào
            $request->validate([
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
            ]);

            // Chuẩn hóa thời gian
            $startDate = $request->has('start_date')
                ? Carbon::parse($request->get('start_date'))->startOfDay()
                : Carbon::now()->startOfMonth();

            $endDate = $request->has('end_date')
                ? Carbon::parse($request->get('end_date'))->endOfDay()
                : Carbon::now()->endOfDay();

            // -------- BẮT ĐẦU TÍNH TOÁN DỮ LIỆU --------

            // 1. Thống kê tổng quan
            $overview = [
                'total_orders' => Order::whereBetween('created_at', [$startDate, $endDate])->count(),
                'total_revenue' => Order::whereBetween('created_at', [$startDate, $endDate])
                    ->where('order_status_id', '!=', 6) // Không tính đơn hủy
                    ->sum('final_amount'),
                'total_users' => User::whereBetween('created_at', [$startDate, $endDate])->count(),
                'total_products' => Product::whereBetween('created_at', [$startDate, $endDate])->count(),
                'total_reviews' => Review::whereBetween('created_at', [$startDate, $endDate])->count(),
                'total_categories' => Category::count(),
                'total_vouchers' => Voucher::whereBetween('created_at', [$startDate, $endDate])->count(),
                'average_order_value' => $this->calculateAverageOrderValue($startDate, $endDate),
                'conversion_rate' => $this->calculateConversionRate($startDate, $endDate),
            ];

            // 2. Thống kê trạng thái đơn hàng
            $orderStatusStats = Order::whereBetween('created_at', [$startDate, $endDate])
                ->select('order_status_id', DB::raw('count(*) as total'))
                ->groupBy('order_status_id')
                ->with('status:id,name')
                ->get();

            // 3. Thống kê hủy và hoàn tiền
            $cancelAndRefundStats = Order::whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    DB::raw('COUNT(CASE WHEN order_status_id = 6 THEN 1 END) as total_cancelled'),
                    DB::raw('COUNT(CASE WHEN order_status_id = 8 THEN 1 END) as total_refunded'),
                    DB::raw('COUNT(*) as total_orders'),
                    DB::raw('SUM(CASE WHEN order_status_id = 6 THEN final_amount ELSE 0 END) as cancelled_amount'),
                    DB::raw('SUM(CASE WHEN order_status_id = 8 THEN final_amount ELSE 0 END) as refunded_amount')
                )
                ->first();

            $cancelAndRefundStats->cancel_rate = $cancelAndRefundStats->total_orders > 0
                ? round(($cancelAndRefundStats->total_cancelled / $cancelAndRefundStats->total_orders) * 100, 2)
                : 0;
            $cancelAndRefundStats->refund_rate = $cancelAndRefundStats->total_orders > 0
                ? round(($cancelAndRefundStats->total_refunded / $cancelAndRefundStats->total_orders) * 100, 2)
                : 0;

            // 4. Thống kê doanh thu theo ngày
            $revenueByDay = Order::whereBetween('created_at', [$startDate, $endDate])
                ->where('order_status_id', '!=', 6) // Không tính đơn hủy
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('SUM(final_amount) as total_revenue'),
                    DB::raw('COUNT(*) as total_orders'),
                    DB::raw('AVG(final_amount) as average_order_value')
                )
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // 5. Thống kê đơn hàng đang chờ
            $pendingOrders = Order::where('order_status_id', 1)
                ->select(
                    DB::raw('COUNT(*) as total'),
                    DB::raw('AVG(TIMESTAMPDIFF(HOUR, created_at, NOW())) as average_waiting_hours')
                )
                ->first();

            // 6. Thống kê phương thức thanh toán
            $totalOrders = Order::whereBetween('created_at', [$startDate, $endDate])->count();
            $paymentMethodStats = Order::whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    'payment_method',
                    DB::raw('count(*) as total'),
                    DB::raw('SUM(final_amount) as total_amount'),
                    DB::raw("ROUND(COUNT(*) * 100.0 / {$totalOrders}, 2) as percentage")
                )
                ->groupBy('payment_method')
                ->get();

            // 7. Thống kê đánh giá
            $reviewStats = Review::whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    DB::raw('AVG(rating) as average_rating'),
                    DB::raw('COUNT(*) as total_reviews'),
                    DB::raw('COUNT(CASE WHEN rating >= 4 THEN 1 END) as positive_reviews'),
                    DB::raw('COUNT(CASE WHEN rating <= 2 THEN 1 END) as negative_reviews')
                )
                ->first();

            // 8. Top sản phẩm bán chạy
            $topSellingProducts = Product::withCount(['orderItems as total_sold' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('order_items.created_at', [$startDate, $endDate]);
                }])
                ->withSum(['orderItems as total_revenue' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('order_items.created_at', [$startDate, $endDate]);
                }], 'product_price')
                ->orderByDesc('total_sold')
                ->limit(5)
                ->get();

            // 9. Thống kê voucher
            $voucherStats = Voucher::whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    DB::raw('COUNT(*) as total_vouchers'),
                    DB::raw('SUM(times_used) as total_usage'),
                    DB::raw('AVG(discount_percent) as avg_discount_percent'),
                    DB::raw('AVG(amount) as avg_discount_amount')
                )
                ->first();

            // 10. Thống kê hoàn tiền
            $refundStats = RefundRequest::whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    DB::raw('COUNT(*) as total_requests'),
                    DB::raw('COUNT(CASE WHEN status = "approved" THEN 1 END) as approved_requests'),
                    DB::raw('COUNT(CASE WHEN status = "rejected" THEN 1 END) as rejected_requests'),
                    DB::raw('SUM(CASE WHEN status = "approved" THEN amount ELSE 0 END) as total_refunded_amount')
                )
                ->first();

            // 11. Thống kê danh mục
            $categoryStats = Category::withCount(['products' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                }])
                ->with(['products' => function ($query) use ($startDate, $endDate) {
                    $query->withCount(['orderItems as total_sold' => function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('order_items.created_at', [$startDate, $endDate]);
                    }])
                    ->withSum(['orderItems as total_revenue' => function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('order_items.created_at', [$startDate, $endDate]);
                    }], 'product_price');
                }])
                ->get()
                ->map(function ($category) {
                    $category->total_sold = $category->products->sum('total_sold');
                    $category->total_revenue = $category->products->sum('total_revenue');
                    return $category;
                })
                ->sortByDesc('total_sold');

            // Kết quả trả về
            $data = [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),

                'overview' => $overview,
                'order_status_stats' => $orderStatusStats,
                'cancel_and_refund_stats' => $cancelAndRefundStats,
                'revenue_by_day' => $revenueByDay,
                'pending_orders' => $pendingOrders,
                'payment_method_stats' => $paymentMethodStats,
                'review_stats' => $reviewStats,
                'top_selling_products' => $topSellingProducts,
                'voucher_stats' => $voucherStats,
                'refund_stats' => $refundStats,
                'category_stats' => $categoryStats,
            ];

            return response()->json($data);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi lấy dữ liệu thống kê',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function calculateAverageOrderValue($startDate, $endDate)
    {
        $totalRevenue = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('order_status_id', '!=', 6) // Không tính đơn hủy
            ->sum('final_amount');
        $totalOrders = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('order_status_id', '!=', 6) // Không tính đơn hủy
            ->count();

        return $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0;
    }

    private function calculateConversionRate($startDate, $endDate)
    {
        $totalUsers = User::whereBetween('created_at', [$startDate, $endDate])->count();
        $totalOrders = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('order_status_id', '!=', 6) // Không tính đơn hủy
            ->count();

        return $totalUsers > 0 ? round(($totalOrders / $totalUsers) * 100, 2) : 0;
    }
}
