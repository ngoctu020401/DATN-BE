<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'discount_percent',
        'amount',
        'max_discount_amount',
        'min_product_price',
        'usage_limit',
        'times_used',
        'is_active',
        'type',
        'expiry_date',
        'start_date',
    ];

    protected $casts = [
        'discount_percent' => 'integer',
        'amount' => 'integer',
        'max_discount_amount' => 'integer',
        'min_product_price' => 'integer',
        'usage_limit' => 'integer',
        'times_used' => 'integer',
        'is_active' => 'boolean',
        'start_date' => 'datetime',
        'expiry_date' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                     ->where(function ($q) {
                         $q->whereNull('expiry_date')
                           ->orWhere('expiry_date', '>=', now());
                     });
    }

    public function scopeAvailable($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('usage_limit')
              ->orWhereRaw('times_used < usage_limit');
        });
    }
}
