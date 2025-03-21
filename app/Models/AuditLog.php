<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'timestamp',
        'full_name',
        'email',
        'role',
        'action_performed',
        'status_before',
        'status_after',
    ];
}

