<?php

use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\ColorController;
use App\Http\Controllers\Api\Admin\SizeController;
use App\Http\Controllers\Api\Admin\UserController;
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
    });

