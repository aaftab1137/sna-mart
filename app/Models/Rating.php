<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    use HasFactory;
    protected $table = "ratings";

    protected $fillable = [
        'id',
        'user_id',
        'rating',
        'feedback',
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
