<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Division extends Model
{
    use HasFactory;
    protected $guard = [];

    protected $fillable = [
        'div_name',
        'note',
        'is_archived',
        'category_id',
        'user_id'
    ];

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function categories()
    {
        return $this->hasMany(Category::class, 'division_id'); // Ensure 'division_id' matches the foreign key in your categories table
    }

    public function teamLeader()
    {
        return $this->belongsTo(User::class, 'user_id'); // Assuming user_id is the foreign key in divisions table
    }

}
