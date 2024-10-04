<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Guard;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Control_Request extends Model
{
    use HasFactory;

    protected $table = 'control__requests';

    protected $fillable = [
        'request_id', 'control_no', 'description', 'officename', 'location_name', 'overtime',
        'area', 'fiscal_year', 'file_path', 'remarks', 'categories', 'status'
    ];
}
