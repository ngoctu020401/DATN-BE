<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['product_id', 'color_id', 'size_id', 'price', 'sale_price', 'stock_quantity'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function color()
    {
        return $this->belongsTo(Color::class);
    }

    public function size()
    {
        return $this->belongsTo(Size::class);
    }
    public function getVariation(){
        return [
            'Kích thước' => $this->size->name ?? null,
            'Màu sắc' => $this->color->name ?? null
        ];
    }
}
