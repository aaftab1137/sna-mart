<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BoostingProduct extends Model
{
    use HasFactory;

    protected $table = "boosting_products";

    protected $fillable = ['user_id', 'product_id', 'boosting_plan_id', 'days','total_price','boosted_until'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function boostingPlan()
    {
        return $this->belongsTo(BoostingPlan::class);
    }
}
