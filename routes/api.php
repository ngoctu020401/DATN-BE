<?php

use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\ColorController;
use App\Http\Controllers\Api\Admin\ProductController;
use App\Http\Controllers\Api\Admin\SizeController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\User\HomeController;
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
//Chức năng cần đăng nhập
Route::post('/logout', [AuthController::class, 'logout']);
Route::prefix('admin')->group(function () { // Chức năng cần là tài khoản admin
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
});
