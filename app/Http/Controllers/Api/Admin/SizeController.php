<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Size;
use Illuminate\Http\Request;

class SizeController extends Controller
{
    //
    //
    public function index()
    {
        //
        try {
            //code...
            $sizes = Size::paginate(10);
            return response()->json($sizes, 200);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'message' => 'Lỗi',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

    // Chức năng thêm kích thước
    public function store(Request $request)
    {
        //
        try {
            //code...
            $data = $request->validate([
                'name' => 'required|string'
            ]);
            $size = Size::create($data);
            return response()->json([
                'message' => 'Bạn đã thêm kích thước thành công',
                'data' => $size
            ], 200);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'message' => 'Lỗi',
                'errors' => $th->getMessage()
            ], 500);
        }
    }
    //Show

    public function show(string $id)
    {
        //
        try {
            //code...
            $size = Size::find($id);
            if (!$size) {
                return response()->json([
                    'message' => 'Không tìm thấy kích thước'
                ], 500);
            }
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
    public function update(Request $request, string $id)
    {
        //
        try {
            //code...
            $data = $request->validate([
                'name' => 'required|string'
            ]);
            $size = Size::find($id);
            if (!$size) {
                return response()->json([
                    'message' => 'Không tìm thấy kích thước'
                ], 500);
            }
            $size->update($data);
            return response()->json([
                'message' => 'Bạn đã sửa kích thước thành công',
                'data' => $size
            ], 200);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'message' => 'Lỗi',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

   //
    public function destroy(string $id)
    {
        try {
            //code...
            $size = Size::findOrFail($id);
            // Xoá kích thước
            $size->delete();

            return response()->json(['message' => 'Đã xoá kích thước ']);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'message' => 'Lỗi',
                'errors' => $th->getMessage()
            ], 500);
        }
    }
}
