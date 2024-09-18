<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CollegeOffice extends Model
{
    use HasFactory;

    protected $fillable = [
        'officename',
        'abbreviation',
        'officetype',
    ];

}

