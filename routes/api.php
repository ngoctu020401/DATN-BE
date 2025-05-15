<?php

use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\ColorController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\OrderController;
use App\Http\Controllers\Api\Admin\ProductController;
use App\Http\Controllers\Api\Admin\ReviewController;
use App\Http\Controllers\Api\Admin\SizeController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\VoucherController;
use App\Http\Controllers\Api\User\CartController;
use App\Http\Controllers\Api\User\HomeController;
use App\Http\Controllers\Api\User\OrderClientController;
use App\Http\Controllers\Api\User\Product;
use App\Http\Controllers\Api\User\ReviewClientController;
use App\Http\Controllers\Api\User\VoucherClientController;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
// Chức năng không cần đăng nhập
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/signup', [AuthController::class, 'signup']);
Route::get('/profile', [AuthController::class, 'profile']);
Route::put('/user/profile', [AuthController::class, 'updateProfile']);
Route::post('/user/change-password', [AuthController::class, 'changePassword']);
Route::get('/list-voucher', [VoucherClientController::class, 'getValidVouchers']);
//  Sản phẩm mới
Route::get('/new-products', [HomeController::class, 'newProdutcs']);

// Danh mục
Route::get('/categories', [HomeController::class, 'listCategory']);

// Danh sách tất cả sản phẩm (có lọc, sắp xếp)
Route::get('/products', [HomeController::class, 'allProduct']);

// Tìm kiếm sản phẩm
Route::get('/products/search', [HomeController::class, 'search']);

// Sản phẩm theo danh mục + bộ lọc
Route::get('/categories/{id}/products', [HomeController::class, 'productByCategory']);

// Chi tiết sản phẩm
Route::get('/product-detail/{id}', [Product::class, 'productDetail']);
//
Route::middleware('auth:sanctum')->group(function () {

    //Chức năng cần đăng nhập
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index']); // Lấy giỏ hàng
        Route::post('/add', [CartController::class, 'add']); // Thêm sản phẩm vào giỏ
        Route::put('/update', [CartController::class, 'update']); // Cập nhật số lượng
        Route::delete('/remove', [CartController::class, 'remove']); // Xoá sản phẩm khỏi giỏ
        Route::post('/checkout-data', [CartController::class, 'checkoutData']); // Lấy dữ liệu sản phẩm đã chọn để checkout
    });
    //Mã giảm giá
    Route::post('/apply-voucher', [VoucherClientController::class, 'applyVoucher']);
    //Đanh gia
    Route::prefix('reviews')->group(function () {
        Route::post('/', [ReviewClientController::class, 'store']);
        Route::put('{id}', [ReviewClientController::class, 'update']);
    });

    Route::prefix('orders')->group(function () {
        Route::get('/history', [OrderClientController::class, 'getUserOrderHistory']); // lịch sử đơn
        Route::get('/{id}', [OrderClientController::class, 'show']); // chi tiết đơn
        Route::post('/{id}/cancel', [OrderClientController::class, 'cancel']); // huỷ đơn
        Route::post('/{id}/complete', [OrderClientController::class, 'complete']); // hoàn tất
        Route::post('/{id}/retry-payment', [OrderClientController::class, 'retryPayment']); // thanh toán lại
        Route::post('/{id}/refund-request', [OrderClientController::class, 'requestRefund']); // yêu cầu hoàn tiền
    });
    //
    Route::post('/checkout', [OrderClientController::class, 'store']);

    // VNPAY callback xử lý kết quả thanh toán
    Route::get('/vnpay/return', [OrderClientController::class, 'vnpayReturn']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::prefix('admin')->middleware('admin')->group(function () { // Chức năng cần là tài khoản admin
        // Kích thước (Size)
        Route::get('sizes', [SizeController::class, 'index']);
        Route::post('sizes', [SizeController::class, 'store']);
        Route::get('sizes/{id}', [SizeController::class, 'show']);
        Route::put('sizes/{id}', [SizeController::class, 'update']);
        Route::delete('sizes/{id}', [SizeController::class, 'destroy']);

        // Màu sắc (Color)
        Route::get('colors', [ColorController::class, 'index']);
        Route::post('colors', [ColorController::class, 'store']);
        Route::get('colors/{id}', [ColorController::class, 'show']);
        Route::put('colors/{id}', [ColorController::class, 'update']);
        Route::delete('colors/{id}', [ColorController::class, 'destroy']);

        // Danh mục (Category)
        Route::get('categories', [CategoryController::class, 'index']);
        Route::post('categories', [CategoryController::class, 'store']);
        Route::get('categories/{id}', [CategoryController::class, 'show']);
        Route::put('categories/{id}', [CategoryController::class, 'update']);
        Route::delete('categories/{id}', [CategoryController::class, 'destroy']);

        //Đơn hàng
        Route::prefix('orders')->group(function () {
            Route::get('/', [OrderController::class, 'index']); // danh sách đơn hàng
            Route::get('/status', [OrderController::class, 'orderStatus']); // danh sách đơn hàng
            Route::get('{id}', [OrderController::class, 'show']); // chi tiết đơn hàng
            Route::post('{id}/change-status', [OrderController::class, 'changeStatus']); // cập nhật trạng thái

            Route::post('refunds/{id}/approve', [OrderController::class, 'approveRefund']); // duyệt hoàn tiền
            Route::post('refunds/{id}/reject', [OrderController::class, 'rejectRefund']); // từ chối hoàn tiền
            Route::post('refunds/{id}/refunded', [OrderController::class, 'markAsRefunded']); // xác nhận đã hoàn tiền
        });

        // Người dùng
        Route::get('users', [UserController::class, 'index']);        // Danh sách người dùng (có phân trang)
        Route::post('users', [UserController::class, 'store']);       // Tạo người dùng mới
        Route::get('users/{id}', [UserController::class, 'show']);    // Xem chi tiết người dùng
        Route::put('users/{id}', [UserController::class, 'update']);  // Cập nhật người dùng
        Route::delete('users/{id}', [UserController::class, 'destroy']); // Xoá người dùng
        Route::prefix('products')->group(function () {
            // Sản phẩm chính
            Route::get('/', [ProductController::class, 'index']);             // Danh sách sản phẩm
            Route::get('/{id}', [ProductController::class, 'show']);          // Chi tiết sản phẩm
            Route::post('/', [ProductController::class, 'store']);            // Thêm sản phẩm
            Route::put('/{id}', [ProductController::class, 'update']);        // Cập nhật sản phẩm
            Route::delete('/{id}', [ProductController::class, 'destroy']);    // Xoá sản phẩm

            // Ảnh sản phẩm
            Route::get('/{id}/images', [ProductController::class, 'getImages']);            // Danh sách ảnh phụ
            Route::post('/{id}/images', [ProductController::class, 'addImages']);           // Thêm ảnh phụ
            Route::delete('/image/{id}', [ProductController::class, 'deleteImage']);        // Xoá ảnh phụ

            // Biến thể sản phẩm
            Route::get('/{id}/variants', [ProductController::class, 'getVariants']);        // Danh sách biến thể
            Route::post('/variation', [ProductController::class, 'addVariation']);          // Thêm biến thể
            Route::put('/variation/{id}', [ProductController::class, 'updateVariation']);   // Sửa biến thể
            Route::delete('/variation/{id}', [ProductController::class, 'deleteVariation']); // Xoá biến thể
        });
        //
        Route::prefix('vouchers')->group(function () {
            Route::get('/', [VoucherController::class, 'index']);     // Danh sách voucher (có phân trang)
            Route::post('/', [VoucherController::class, 'store']);     // Tạo mới voucher
            Route::get('{id}', [VoucherController::class, 'show']);    // Xem chi tiết voucher
            Route::put('{id}', [VoucherController::class, 'update']);  // Cập nhật voucher
            Route::delete('{id}', [VoucherController::class, 'destroy']); // Xóa voucher
        });
        //
        Route::get('/dashboard', [DashboardController::class, 'index']);
        //
        Route::prefix('reviews')->group(function () {
            Route::get('/', [ReviewController::class, 'index']);         // Danh sách đánh giá
            Route::get('{id}', [ReviewController::class, 'show']);       // Chi tiết đánh giá
            Route::post('{id}/reply', [ReviewController::class, 'reply']); // Trả lời đánh giá
            Route::post('{id}/block', [ReviewController::class, 'block']); // Ẩn/hiện đánh giá
        });
    });
});
