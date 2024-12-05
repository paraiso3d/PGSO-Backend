<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $table = 'roles';

    protected $fillable = [
        'role_name',
        'description',
        'is_archived'
    ] ;
    protected $attributes = [
        'is_archived' => '0',  // Default value
    ];
}
