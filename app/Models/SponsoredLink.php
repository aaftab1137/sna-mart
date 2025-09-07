<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SponsoredLink extends Model
{
    use HasFactory;
    protected $table = "sponsored_links";

    protected $fillable =['name', 'link', 'image'];
}
