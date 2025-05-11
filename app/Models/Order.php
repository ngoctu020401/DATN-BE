<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'order_code',
        'email',
        'phone',
        'name',
        'address',
        'note',
        'cancel_reason',
        'total_amount',
        'shipping',
        'final_amount',
        'payment_url',
        'payment_method',
        'order_status_id',
        'payment_status_id',
        'closed_at',
    ];
    
    // Relationships
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function histories()
    {
        return $this->hasMany(OrderHistory::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function status()
    {
        return $this->belongsTo(OrderStatus::class, 'order_status_id');
    }

    public function paymentStatus()
    {
        return $this->belongsTo(PaymentStatus::class, 'payment_status_id');
    }
    public function refundRequest()
    {
        return $this->hasOne(RefundRequest::class);
    }
    public function paymentOnlines()
    {
        return $this->hasMany(RefundRequest::class);
    }
        public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}
