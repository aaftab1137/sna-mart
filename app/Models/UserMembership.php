<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserMembership extends Model
{
    use HasFactory;

    protected $table = "user_memberships";

    protected $fillable = [
        'id',
        'user_id',
        'plan_id',
        'price',
        'expires_at'
    ];

    public function plans(){

        return $this->belongsTo(MembershipPlan::class,'plan_id');
    }
    public function users(){

        return $this->belongsTo(User::class,'user_id');
    }
}
