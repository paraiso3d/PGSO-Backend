<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Manpower extends Model
{
    use HasFactory;
    protected $guard=[];
    protected $fillable = [
        'first_name',
        'last_name',
        'is_archived'
    ];

    protected $attributes = [
        'is_archived' => 'A',  // Default value
    ];
}
