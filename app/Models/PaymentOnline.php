<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentOnline extends Model
{
    use HasFactory;
    protected $table = 'payment_online';

    protected $fillable = [
        'order_id',
        'amount',
        'vnp_transaction_no',
        'vnp_bank_code',
        'vnp_bank_tran_no',
        'vnp_pay_date',
        'vnp_card_type',
        'vnp_response_code',
    ];

    protected $casts = [
        'vnp_pay_date' => 'datetime',
    ];

    // Mối quan hệ: PaymentOnline thuộc về Order
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
