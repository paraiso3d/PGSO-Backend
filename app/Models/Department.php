<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;
    protected $fillable = [
        'department_name',
        'division_id',
        'head_id',
        'is_archived'
    ];
    public function divisions()
    {
        return $this->belongsToMany(Division::class, 'department_division');
    }
    public function head()
    {
        return $this->belongsTo(User::class, 'head_id');
    }
}
