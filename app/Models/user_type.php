<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class user_type extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'description',
        'is_archived'
    ] ;
    protected $attributes = [
        'is_archived' => 'A',  // Default value
    ];
}
