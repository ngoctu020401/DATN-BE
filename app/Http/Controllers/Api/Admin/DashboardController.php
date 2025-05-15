<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Review;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        try {
            // Lấy thời gian từ request hoặc mặc định là tháng hiện tại
            $startDate = $request->get('start_date', Carbon::now()->startOfMonth());
            $endDate = $request->get('end_date', Carbon::now()->endOfMonth());

            // Cache key dựa trên thời gian
            $cacheKey = "dashboard_stats_{$startDate}_{$endDate}";

            // Kiểm tra cache trước khi thực hiện query
            if (Cache::has($cacheKey)) {
                return response()->json(Cache::get($cacheKey));
            }

            // Thống kê tổng quan với eager loading
            $overview = [
                'total_orders' => Order::whereBetween('created_at', [$startDate, $endDate])->count(),
                'total_revenue' => Order::whereBetween('created_at', [$startDate, $endDate])
                    ->where('order_status_id', '!=', 6)
                    ->sum('final_amount'),
                'total_users' => User::whereBetween('created_at', [$startDate, $endDate])->count(),
                'total_products' => Product::whereBetween('created_at', [$startDate, $endDate])->count(),
                'total_reviews' => Review::whereBetween('created_at', [$startDate, $endDate])->count(),
                'average_order_value' => $this->calculateAverageOrderValue($startDate, $endDate),
                'conversion_rate' => $this->calculateConversionRate($startDate, $endDate),
            ];

            // Thống kê đơn hàng theo trạng thái với tên trạng thái
            $orderStatusStats = Order::whereBetween('created_at', [$startDate, $endDate])
                ->select('order_status_id', DB::raw('count(*) as total'))
                ->groupBy('order_status_id')
                ->with('status:id,name')
                ->get();

            // Tính tỷ lệ hủy và hoàn với thông tin chi tiết
            $cancelAndRefundStats = Order::whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    DB::raw('COUNT(CASE WHEN order_status_id = 6 THEN 1 END) as total_cancelled'),
                    DB::raw('COUNT(CASE WHEN order_status_id = 8 THEN 1 END) as total_refunded'),
                    DB::raw('COUNT(*) as total_orders'),
                    DB::raw('SUM(CASE WHEN order_status_id = 6 THEN final_amount ELSE 0 END) as cancelled_amount'),
                    DB::raw('SUM(CASE WHEN order_status_id = 8 THEN final_amount ELSE 0 END) as refunded_amount')
                )
                ->first();

            // Tính tỷ lệ phần trăm
            $cancelAndRefundStats->cancel_rate = $cancelAndRefundStats->total_orders > 0
                ? round(($cancelAndRefundStats->total_cancelled / $cancelAndRefundStats->total_orders) * 100, 2)
                : 0;
            $cancelAndRefundStats->refund_rate = $cancelAndRefundStats->total_orders > 0
                ? round(($cancelAndRefundStats->total_refunded / $cancelAndRefundStats->total_orders) * 100, 2)
                : 0;

            // Thống kê doanh thu theo ngày với thông tin chi tiết
            $revenueByDay = Order::whereBetween('created_at', [$startDate, $endDate])
                ->where('order_status_id', '!=', 6)
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('SUM(final_amount) as total_revenue'),
                    DB::raw('COUNT(*) as total_orders'),
                    DB::raw('AVG(final_amount) as average_order_value')
                )
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // Thống kê đơn hàng chờ xác nhận với thời gian chờ
            $pendingOrders = Order::where('order_status_id', 1)
                ->select(
                    DB::raw('COUNT(*) as total'),
                    DB::raw('AVG(TIMESTAMPDIFF(HOUR, created_at, NOW())) as average_waiting_hours')
                )
                ->first();

            // Thống kê phương thức thanh toán với tỷ lệ
            $paymentMethodStats = Order::whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    'payment_method',
                    DB::raw('count(*) as total'),
                    DB::raw('SUM(final_amount) as total_amount'),
                    DB::raw('ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM orders WHERE created_at BETWEEN ? AND ?), 2) as percentage')
                )
                ->setBindings([$startDate, $endDate])
                ->groupBy('payment_method')
                ->get();

            // Thống kê đánh giá chi tiết
            $reviewStats = Review::whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    DB::raw('AVG(rating) as average_rating'),
                    DB::raw('COUNT(*) as total_reviews'),
                    DB::raw('COUNT(CASE WHEN rating >= 4 THEN 1 END) as positive_reviews'),
                    DB::raw('COUNT(CASE WHEN rating <= 2 THEN 1 END) as negative_reviews')
                )
                ->first();

            // Thống kê sản phẩm bán chạy
            $topSellingProducts = Product::withCount(['orderItems as total_sold' => function($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                }])
                ->withSum(['orderItems as total_revenue' => function($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                }], 'price')
                ->orderByDesc('total_sold')
                ->limit(5)
                ->get();

            $data = [
                'overview' => $overview,
                'order_status_stats' => $orderStatusStats,
                'cancel_and_refund_stats' => $cancelAndRefundStats,
                'revenue_by_day' => $revenueByDay,
                'pending_orders' => $pendingOrders,
                'payment_method_stats' => $paymentMethodStats,
                'review_stats' => $reviewStats,
                'top_selling_products' => $topSellingProducts,
            ];

            // Cache kết quả trong 1 giờ
            Cache::put($cacheKey, $data, 3600);

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
            ->where('order_status_id', '!=', 6)
            ->sum('final_amount');
        $totalOrders = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('order_status_id', '!=', 6)
            ->count();

        return $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0;
    }

    private function calculateConversionRate($startDate, $endDate)
    {
        $totalUsers = User::whereBetween('created_at', [$startDate, $endDate])->count();
        $totalOrders = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('order_status_id', '!=', 6)
            ->count();

        return $totalUsers > 0 ? round(($totalOrders / $totalUsers) * 100, 2) : 0;
    }
}
