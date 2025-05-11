<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;
    protected $table = 'reviews';

    protected $fillable = [
        'order_id',
        'order_item_id',
        'product_id',
        'user_id',
        'customer_name',
        'customer_mail',
        'rating',
        'content',
        'images',
        'reply',
        'is_active',
        'hidden_reason',
        'is_updated',
        'reply_at',
    ];

    protected $casts = [
        'images' => 'array',
        'is_active' => 'boolean',
        'is_updated' => 'boolean',
        'reply_at' => 'datetime',
    ];

    // (Optional) Quan hệ: review thuộc về 1 sản phẩm
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
