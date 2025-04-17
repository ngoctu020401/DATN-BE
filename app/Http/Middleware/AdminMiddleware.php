<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Kiểm tra người dùng đã đăng nhập chưa
        if (!$user) {
            return response()->json(['message' => 'Bạn chưa đăng nhập!'], 401);
        }

        // Nếu không phải admin hoặc staff, từ chối truy cập vào admin
        if (!in_array($user->role, ['admin'])) {
            return response()->json(['message' => 'Bạn không có quyền truy cập admin!'], 403);
        }
        return $next($request);
    }
}
