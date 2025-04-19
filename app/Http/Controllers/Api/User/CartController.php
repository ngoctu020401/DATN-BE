<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\ProductVariation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    //
    public function index()
    {
        $user = Auth::user(); // Lấy ra người dùng được gửi lên = token
        $cart = $user->cart; // Lấy giỏ hàng của người dùng đó

        $items = CartItem::with('productVariation.product', 'productVariation.color', 'productVariation.size')
            ->where('cart_id', $cart->id)
            ->get(); // Lấy ra  các item có trong giỏ hàng

        return response()->json(['items' => $items]); // trả về dữ liệu
    }

    public function add(Request $request)
    {
        $request->validate([
            'variation_id' => 'required|exists:product_variations,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $user = Auth::guard('sanctum')->user();
        $cart = $user->cart;

        $variationId = (int) $request->input('variation_id');
        $quantity = (int) $request->input('quantity');

        $variant = ProductVariation::findOrFail($variationId);

        // Kiểm tra tồn kho trước khi thêm
        $item = CartItem::where('cart_id', $cart->id)
            ->where('variation_id', $variationId)
            ->first(); 

        $totalQuantity = $item ? $item->quantity + $quantity : $quantity; // Nếu có item thì lấy số lượng cũ + số lượng mới| Nếu không có thì bằng số lượng gửi lên
        
        if ($totalQuantity > $variant->stock_quantity) { // Kiểm tra xem số lượng có vượt quá tồn kho không
            return response()->json([
                'message' => 'Không thể thêm sản phẩm. Số lượng vượt quá tồn kho hiện tại.'
            ], 400);
        }

        if ($item) { // Nếu có item thì cập nhật số lượng
            $item->quantity = $totalQuantity;
            $item->save();
        } else { // Nếu không có thì tạo mới
            $item = CartItem::create([
                'cart_id' => $cart->id,
                'variation_id' => $variationId,
                'quantity' => $quantity,
            ]);
        }

        return response()->json([
            'message' => 'Đã thêm vào giỏ hàng',
            'item' => $item
        ]);
    }


    public function update(Request $request)
    {
        $request->validate([
            'cart_item_id' => 'required|exists:cart_items,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $item = CartItem::with('productVariation')->findOrFail($request->cart_item_id);
        // Kiểm tra xem số lượng muốn update có lớn hơn số lượng tồn kho không
        if ($request->quantity > $item->productVariation->stock_quantity) {
            return response()->json([
                'message' => 'Số lượng vượt quá tồn kho hiện tại.'
            ], 400);
        }
        // Nếu không thì cập nhật lại số lượng
        $item->quantity = $request->quantity;
        $item->save();

        return response()->json(['message' => 'Cập nhật thành công', 'item' => $item]);
    }
}
