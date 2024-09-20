<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class division_name extends Model
{
    use HasFactory;
    protected $fillable = ["div_name","note"];
}
