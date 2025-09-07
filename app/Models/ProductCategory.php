<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{
    use HasFactory;
    protected  $table = "product_categories";
    protected $fillable = [
        'category_name',
        'parent_id	'
    ];

    // Get Subcategories
    public function subcategories()
    {
        return $this->hasMany(ProductCategory::class, 'parent_id');
    }

    // Get Parent Category
    public function parent()
    {
        return $this->belongsTo(ProductCategory::class, 'parent_id');
    }
}
