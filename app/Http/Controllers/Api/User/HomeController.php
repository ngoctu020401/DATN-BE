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
        $query = $this->filterProductQuery($query, $request);

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
    //
    protected function filterProductQuery($query, Request $request)
    {
        if ($request->filled('keyword')) {
            $query->where('name', 'like', '%' . $request->keyword . '%');
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('price_from')) {
            $query->whereHas('variationMinPrice', function ($q) use ($request) {
                $q->where('price', '>=', $request->price_from);
            });
        }

        if ($request->filled('price_to')) {
            $query->whereHas('variationMinPrice', function ($q) use ($request) {
                $q->where('price', '<=', $request->price_to);
            });
        }

        if ($request->filled('sort')) {
            switch ($request->sort) {
                case 'price_asc':
                    $query->withMin('variationMinPrice', 'price')->orderBy('variation_min_price_price');
                    break;
                case 'price_desc':
                    $query->withMin('variationMinPrice', 'price')->orderByDesc('variation_min_price_price');
                    break;
                default:
                    $query->orderBy('created_at', 'desc');
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query;
    }
    public function productByCategory(Request $request, $id)
    {
        $query = Product::with(['category', 'variationMinPrice'])->where('category_id', $id);
        $query = $this->filterProductQuery($query, $request);

        $listProducts = $query->get();
        return response()->json($listProducts, 200);
    }
}
