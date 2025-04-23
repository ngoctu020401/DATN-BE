<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    //
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();
        if (!$user->is_active) {
            return response()->json([
                'message' => 'Tài khoản của bạn đã bị khóa',
                'reason' => $user->inactive_reason,
            ], 403);
        }

        // Kiểm tra mật khẩu
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Email hoặc mật khẩu không chính xác!'], 401);
        }

        // Xóa token cũ nếu người dùng đăng nhập lại (tránh trùng lặp token)
        $user->tokens()->delete();

        // Nếu chọn "Remember Me", token sẽ có thời gian sống dài hơn
        $tokenExpiration = $request->remember ? now()->addWeeks(2) : now()->addHours(2);

        // Tạo token đăng nhập
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Đăng nhập thành công!',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user(); // Lấy user gửi lên từ token trong header
        //Kiểm tra xem user có tồn tại không
        if ($user) {
            $user->currentAccessToken()->delete();  //Nếu có thì xóa token
            return response()->json(['message' => 'Đăng xuất thành công!'], 200);
        }
        //Nếu không trả về lỗi
        return response()->json(['message' => 'Không thể đăng xuất, vui lòng thử lại!'], 400);
    }

    //
    public function signup(Request $request)
    {
        try {
            DB::beginTransaction();
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|min:8|confirmed',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string',
                'avatar' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            ]);

            // Handle avatar upload
            if ($request->hasFile('avatar')) {
                $filename = time() . '_' . Str::random(10) . '.' . $request->file('avatar')->getClientOriginalExtension();
                $avatarPath = $request->file('avatar')->storeAs('avatars', $filename, 'public');
                $validated['avatar'] = $avatarPath;
            }

            $validated['password'] = Hash::make($validated['password']);
            $validated['role'] = 'user';
            $user = User::create($validated);
            DB::commit();
            return response()->json($user, 201);
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            return response()->json([
                'message' => 'Lỗi',
                'errors' => $th->getMessage()
            ], 500);
        }
    }
    //
    public function profile()
    {
        $user = auth('sanctum')->user();
        return response()->json($user, 200);
    }
    public function updateProfile(Request $request)
    {
        $user = auth('sanctum')->user();
        $user = User::findOrFail($user->id);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // Xử lý avatar mới nếu có
        if ($request->hasFile('avatar')) {
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            $avatar = $request->file('avatar');
            $filename = 'avatar_' . time() . '.' . $avatar->getClientOriginalExtension();
            $path = $avatar->storeAs('uploads', $filename, 'public');

            $data['avatar'] = $path;
        }

        $user->update($data);

        return response()->json([
            'message' => 'Cập nhật thông tin thành công.',
            'user' => $user->fresh(),
        ]);
    }
    //
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:6|confirmed',
        ], [
            'new_password.confirmed' => 'Xác nhận mật khẩu không khớp.',
        ]);

        // Ép kiểu về model
        $user = auth('sanctum')->user();
        $user = User::findOrFail($user->id);

        // So sánh mật khẩu cũ
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Mật khẩu hiện tại không đúng.'
            ], 422);
        }

        // Cập nhật mật khẩu mới
        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json([
            'message' => 'Đổi mật khẩu thành công.'
        ]);
    }
}
