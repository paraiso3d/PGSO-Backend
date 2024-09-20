<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class list_of_category extends Model
{
    use HasFactory;
    protected $fillable = ["category_name","division"];
}
