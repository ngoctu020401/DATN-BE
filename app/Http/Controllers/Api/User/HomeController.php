<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    //
    public function newProdutcs()
    {
        $latestProducts = Product::with([
            'category',
            'variationMinPrice'
        ])
            ->orderBy('created_at', 'desc')
            ->take(8)
            ->get();
        return response()->json($latestProducts, 200);
    }
    public function listCategory()
    {
        $categories = Category::where('id', '!=', 1)->get();
        return response()->json($categories, 200);
    }
    // 
    public function allProduct(Request $request)
    {
        $query = Product::with(['category', 'variationMinPrice']);


        $listProducts = $query->get();
        return response()->json($listProducts, 200);
    }

    //
    public function search(Request $request)
    {
        $keyword = $request->keyword;

        $products = Product::where('name', 'like', '%' . $keyword . '%')
            ->with(['images', 'variationMinPrice'])
            ->paginate(10);

        return response()->json($products, 200);
    }
    public function productByCategory(Request $request, $id)
    {
        $query = Product::with(['category', 'variationMinPrice'])->where('category_id', $id);
        $listProducts = $query->get();
        return response()->json($listProducts, 200);
    }
}
