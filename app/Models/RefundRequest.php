<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefundRequest extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'type',
        'amount',
        'reason',
        'images',
        'status',
        'reject_reason',
        'bank_name',
        'bank_account_name',
        'bank_account_number',
        'approved_at',
        'refunded_at',
        'refund_proof_image'
    ];

    protected $casts = [
        'images' => 'array',
        'approved_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
