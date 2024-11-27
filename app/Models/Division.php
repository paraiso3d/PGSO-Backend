<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Division extends Model
{
    use HasFactory;

    
    protected $table = 'divisions'; // Ensure this is correct
    protected $guard = [];

    protected $fillable = [
        'division_name',
        'description',
        'is_archived',
        'category_id',
        'user_id'
    ];


}
