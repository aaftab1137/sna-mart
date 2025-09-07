<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionSetting extends Model
{
    use HasFactory;
    protected $fillable = ['is_paid', 'product_upload_limit'];
}