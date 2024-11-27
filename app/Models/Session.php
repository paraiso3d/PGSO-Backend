<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Session extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'sessions'; // Ensure this matches the database table
    protected $fillable = ['session_code', 'user_id', 'login_date', 'logout_date'];
}
