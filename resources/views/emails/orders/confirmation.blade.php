@component('mail::message')
# Cảm ơn bạn đã đặt hàng tại {{ config('app.name') }}

**Mã đơn hàng:** {{ $order->order_code }}  
**Tên khách hàng:** {{ $order->name }}  
**SĐT:** {{ $order->phone }}  
**Email:** {{ $order->email }}  
**Địa chỉ giao hàng:** {{ $order->address }}  
**Phương thức thanh toán:** {{ $order->payment_method }}  
**Tổng tiền:** {{ number_format($order->total_amount, 0, ',', '.') }} VNĐ  
**Phí vận chuyển:** {{ number_format($order->shipping, 0, ',', '.') }} VNĐ  
**Thanh toán:** {{ number_format($order->final_amount, 0, ',', '.') }} VNĐ

@isset($order->note)
**Ghi chú:** {{ $order->note }}
@endisset

@component('mail::button', ['url' => config('app.url')])
Xem thêm sản phẩm
@endcomponent

Cảm ơn bạn đã tin tưởng và ủng hộ!

{{ config('app.name') }}
@endcomponent
