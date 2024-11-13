<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;
    protected $fillable = [
        'location_name',
        'note',
        'is_archived'
    ];

    protected $attributes = [
        'is_archived' => '0',  // Default value
    ];
}
