<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'user_id',
        'category_id',
        'sell_or_rent',
        'title',
        'subtitle',
        'price',
        'available_sizes',
        'available_colors',
        'additional_description',
        'product_image',
        'location',
        'product_type',
        'is_sponsored',
        'sponsored_untill',
        'lat',
        'long',
    ];

    protected $casts = [
        'available_sizes' => 'array',
        'available_colors' => 'array',
    ];

    public function users()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function views()
    {
        return $this->hasMany(ProductView::class, 'product_id');
    }
}
