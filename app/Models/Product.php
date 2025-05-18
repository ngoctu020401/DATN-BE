<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['name', 'description', 'main_image', 'category_id', 'is_active'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function variations()
    {
        return $this->hasMany(ProductVariation::class);
    }
    public function variationMinPrice()
    {
        return $this->hasOne(ProductVariation::class)->orderBy('price');
    }

    public function orderItems()
    {
        return $this->hasManyThrough(OrderItem::class, ProductVariation::class);
    }
}
