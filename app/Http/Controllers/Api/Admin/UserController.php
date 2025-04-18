<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserController extends Controller
{
    //
    public function index()
    {
        try {
            //code...
            $size = User::paginate(10);
            return response()->json($size, 200);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'message' => 'Lỗi',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

    //
    public function store(Request $request)
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
                'role' => 'nullable|string',
            ]);

            $data = $validated; // Clone để thao tác riêng

            // Handle avatar upload
            if ($request->hasFile('avatar')) {
                $filename = time() . '_' . Str::random(10) . '.' . $request->file('avatar')->getClientOriginalExtension();
                $avatarPath = $request->file('avatar')->storeAs('uploads', $filename, 'public');
                $data['avatar'] = $avatarPath;
            }
    
            $data['password'] = Hash::make($validated['password']);

            $validated['password'] = Hash::make($validated['password']);

            $user = User::create($data);
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
    public function show($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'Không tìm thấy người dùng'], 404);
        }

        return response()->json($user);
    }
    //
    public function update(Request $request, $id)
    {
        try {
            //code...
            $user = User::find($id);

            if (!$user) {
                return response()->json(['message' => 'Không tìm thấy người dùng'], 404);
            }
    
            // Không cho sửa email hoặc user có role admin
            if ($user->role === 'admin') {
                return response()->json(['message' => 'Không thể chỉnh sửa tài khoản admin'], 403);
            }
    
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string',
                'avatar' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'role' => 'nullable|string',
            ]);
    
            $data = $validated;
            
            if ($request->hasFile('avatar')) {
                if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                    Storage::disk('public')->delete($user->avatar);
                }
                $filename = time() . '_' . Str::random(10) . '.' . $request->file('avatar')->getClientOriginalExtension();
                $avatarPath = $request->file('avatar')->storeAs('uploads', $filename, 'public');
                $data['avatar'] = $avatarPath;
            }
            
            $user->update($data);
            Log::info('Dữ liệu update:', $validated);
            return response()->json([
                'message'=>'Bạn đã sửa thành công',
                'data'=>$request->all()
            ],200);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'errors'=>$th->getMessage()
            ]);
        }
 
    }
    //
    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'Không tìm thấy người dùng'], 404);
        }

        // Không cho xoá admin
        if ($user->role === 'admin') {
            return response()->json(['message' => 'Không thể xoá tài khoản admin'], 403);
        }

        // Xóa ảnh nếu có
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->delete();

        return response()->json(['message' => 'Đã xoá người dùng']);
    }
}
