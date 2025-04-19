<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    //
    public function index(){
        $user = Auth::user();
        $cart = $user->cart;

        $items = CartItem::with('productVariation.product', 'productVariation.color', 'productVariation.size')
            ->where('cart_id', $cart->id)
            ->get();

        return response()->json(['items' => $items]);
    }
       
    public function add(Request $request)
    {
        $request->validate([
            'variation_id' => 'required|exists:product_variations,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $user = Auth::user();
        $cart = $user->cart;

        $variationId = (int) $request->input('variation_id');
        $quantity = (int) $request->input('quantity');

        $existingItem = CartItem::where('cart_id', $cart->id)
            ->where('variation_id', $variationId)
            ->first();

        if ($existingItem) {
            $existingItem->quantity += $quantity;
            $existingItem->save();
        } else {
            $existingItem = CartItem::create([
                'cart_id' => $cart->id,
                'variation_id' => $variationId,
                'quantity' => $quantity,
            ]);
        }

        return response()->json([
            'message' => 'Đã thêm vào giỏ hàng',
            'item' => $existingItem
        ]);
    }
}
