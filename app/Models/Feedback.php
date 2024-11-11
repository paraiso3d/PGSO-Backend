<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    use HasFactory;
    protected $fillable = [
        'request_id',
        'date_started',
        'date_completed',
        'final_remarks',
        'accomplishment_id',
        'rating',
        'feedback',
        'user_id'
    ];

    protected $casts = [
        'date_started' => 'datetime',
        'date_completed' => 'datetime',
    ];
}

