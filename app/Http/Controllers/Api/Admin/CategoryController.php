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
            $categories = Category::paginate(10);
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
}
