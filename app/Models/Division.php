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
        'category_id'
    ];

    public function categories()
{
    return $this->belongsToMany(Category::class);
}
}
