<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Color;
use App\Models\ProductVariation;
use Illuminate\Http\Request;

class ColorController extends Controller
{
    //
    public function index()
    {
        //
        try {
            //code...
            $colors = Color::paginate(10); // phân trang 10 color / 1 trang
            return response()->json($colors, 200);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'message' => 'Lỗi',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

    // Chức năng thêm màu sắc
    public function store(Request $request)
    {
        //
        try {
            //code...
            $data = $request->validate([
                'name' => 'required|string'
            ]);
            $color = Color::create($data);
            return response()->json([
                'message' => 'Bạn đã thêm màu sắc thành công',
                'data' => $color
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
            $color = Color::find($id);
            if (!$color) {
                return response()->json([
                    'message' => 'Không tìm thấy màu sắc'
                ], 500);
            }
            return response()->json($color, 200);
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
            $color = Color::find($id);
            if (!$color) {
                return response()->json([
                    'message' => 'Không tìm thấy màu sắc'
                ], 500);
            }
            $color->update($data);
            return response()->json([
                'message' => 'Bạn đã sửa màu sắc thành công',
                'data' => $color
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
            $color = Color::findOrFail($id);
            if (!$color) {
                return response()->json([
                    'message' => 'Không tìm thấy màu sắc'
                ], 500);
            }
            // Kiểm tra xem có biến thể nào đang dùng màu sắc này không, nếu có thì không cho xóa
            $usedInVariations = ProductVariation::where('color_id', $id)->exists();
            if ($usedInVariations) {
                return response()->json([
                    'message' => 'Không thể xoá màu sắc vì đang được sử dụng trong sản phẩm!',
                ], 400);
            }
            // Xoá màu sắc
            $color->delete();

            return response()->json(['message' => 'Đã xoá màu sắc ']);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'message' => 'Lỗi',
                'errors' => $th->getMessage()
            ], 500);
        }
    }
}
