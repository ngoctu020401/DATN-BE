<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;
    protected $fillable = [
        'order_id',
        'product_name',
        'variation_id',
        'product_price',
        'quantity',
        'image',
        'variation'
    ];

    protected $casts = [
        'variation' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariation::class, 'variation_id');
    }
        public function review()
    {
        return $this->belongsTo(Review::class);
    }
}
