<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    //
    public function index()
    {
        $user = auth('sanctum')->user(); // Lấy ra người dùng được gửi lên = token
        $cart = $user->cart ?? null; // Lấy giỏ hàng của người dùng đó
        if (!$cart) { // Nếu không lấy được giỏ hàng thì trả về 
            return response()->json([
                'items' => [],
                'message' => 'Giỏ hàng trống'
            ]);
        }
        $items = CartItem::with('variation.product', 'variation.color', 'variation.size')
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

        $user = auth('sanctum')->user(); // Lấy thông tin ng dùng từ token
        $cart = $user->cart ?? null; // Kiểm tra xem ng dùng có giỏ hàng chưa
        if(!$cart){ // Nếu chưa có thì tạo mới giỏ hàng
            $cart = Cart::create([
                'user_id'=>$user->id
            ]);
        }
        $variationId = (int) $request->input('variation_id'); // Lấy ra variation_id
        $quantity = (int) $request->input('quantity'); // Lấy ra số lượng được gửi lên

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

        $item = CartItem::with('variation')->findOrFail($request->cart_item_id);
        // Kiểm tra xem số lượng muốn update có lớn hơn số lượng tồn kho không
        if ($request->quantity > $item->variation->stock_quantity) {
            return response()->json([
                'message' => 'Số lượng vượt quá tồn kho hiện tại.'
            ], 400);
        }
        // Nếu không thì cập nhật lại số lượng
        $item->quantity = $request->quantity;
        $item->save();

        return response()->json(['message' => 'Cập nhật thành công', 'item' => $item]);
    }

    public function remove(Request $request)
    {
        $request->validate([
            'cart_item_id' => 'required|exists:cart_items,id'
        ]);

        CartItem::findOrFail($request->cart_item_id)->delete();
        return response()->json(['message' => 'Đã xóa sản phẩm khỏi giỏ hàng']);
    }



    public function checkoutData(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:cart_items,id'
        ]);

        $user = auth('sanctum')->user();
        $cart = $user->cart;
        $ids = $request->input('ids');
        $items = CartItem::with('variation.product', 'variation.color', 'variation.size')
            ->where('cart_id', $cart->id)
            ->whereIn('id', $ids)
            ->get();

        return response()->json(['items' => $items]);
    }
}
