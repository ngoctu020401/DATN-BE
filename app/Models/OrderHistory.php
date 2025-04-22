<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderHistory extends Model
{
    use HasFactory;
    protected $fillable = [
        'order_id',
        'status',
        'note'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    public function status()
{
    return $this->belongsTo(OrderStatus::class, 'order_status_id');
}

}
