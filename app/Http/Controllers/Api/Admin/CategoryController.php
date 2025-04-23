<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    //
    public function index()
    {
        //
        try {
            //code...
            $categories = Category::orderBy('created_at', 'desc')->paginate(10);
            return response()->json($categories, 200);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'message' => 'Lỗi',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

    // Chức năng thêm danh mục
    public function store(Request $request)
    {
        //
        try {
            //code...
            $data = $request->validate([
                'name' => 'required|string'
            ]);
            $category = Category::create($data);
            return response()->json([
                'message' => 'Bạn đã thêm danh mục thành công',
                'data' => $category
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
            $category = Category::find($id);
            if (!$category) {
                return response()->json([
                    'message' => 'Không tìm thấy danh mục'
                ], 500);
            }
            return response()->json($category, 200);
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
            $category = Category::find($id);
            if (!$category) {
                return response()->json([
                    'message' => 'Không tìm thấy danh mục'
                ], 500);
            }
            $category->update($data);
            return response()->json([
                'message' => 'Bạn đã thêm danh mục thành công',
                'data' => $category
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
                return response()->json(['message' => 'Không thể xoá danh mục mặc định'], 400);
            }

            // Cập nhật tất cả sản phẩm về danh mục mặc định
            $category->products()->update(['category_id' => 1]);

            // Xoá danh mục
            $category->delete();

            return response()->json(['message' => 'Đã xoá danh mục và chuyển sản phẩm danh mục mặc định']);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'message' => 'Lỗi',
                'errors' => $th->getMessage()
            ], 500);
        }
    }
}
