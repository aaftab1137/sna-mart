<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BoostingPlan extends Model
{
    use HasFactory;

    protected $table = "boosting_plans";
    protected $fillable = ['id','price','views_per_day'];
}
