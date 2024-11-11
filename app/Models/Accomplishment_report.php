<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Accomplishment_report extends Model
{
    use HasFactory;

    protected $fillable = [
        'control_request_id',
        'description',
        'date_started',
        'date_completed',
        'remarks',
        'division_id',
        'status',
        'user_id'
    ];

    protected $casts = [
        'date_started' => 'datetime',
        'date_completed' => 'datetime',
    ];
}
