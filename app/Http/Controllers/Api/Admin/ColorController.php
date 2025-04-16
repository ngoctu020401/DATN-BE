<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Color;
use Illuminate\Http\Request;

class ColorController extends Controller
{
    //
    public function index()
    {
        //
        try {
            //code...
            $colors = Color::paginate(10);
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
            $category = Category::findOrFail($id);

            // Nếu đang cố xoá chính "Chưa phân loại" thì không cho
            if ($category->id == 1) {
                return response()->json(['message' => 'Không thể xoá màu sắc mặc định'], 400);
            }

            // Cập nhật tất cả sản phẩm về màu sắc mặc định
            $category->products()->update(['category_id' => 1]);

            // Xoá màu sắc
            $category->delete();

            return response()->json(['message' => 'Đã xoá màu sắc và chuyển sản phẩm màu sắc mặc định']);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'message' => 'Lỗi',
                'errors' => $th->getMessage()
            ], 500);
        }
    }
}
