<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipPlan extends Model
{
    use HasFactory;

    protected $table = "membership_plans";
    protected $fillable = [
        'id',
        'name',
        'price',
        'duration',
        'benefit',
    ];

    
}
