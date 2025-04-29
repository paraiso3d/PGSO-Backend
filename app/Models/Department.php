<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;
    protected $fillable = [
        'department_name',
        'acronym',
        'division_id',
        'head_id',
        'is_archived'
    ];
    public function head()
    {
        return $this->belongsTo(User::class, 'head_id');
    }

    public function divisions()
{
    return $this->hasMany(Division::class, 'department_id');
}
}
