<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductImage extends Model
{
    use HasFactory,SoftDeletes;
    protected $fillable = ['product_id', 'url'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
