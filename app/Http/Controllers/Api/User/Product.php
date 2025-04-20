<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Product as ModelsProduct;
use Illuminate\Http\Request;

class Product extends Controller
{
    //
    public function productDetail($id){
        $product = ModelsProduct::with(['variations','variations.color','variations.size','images'])->where('id',$id)->first();
        return response()->json($product,200);
    }
}
