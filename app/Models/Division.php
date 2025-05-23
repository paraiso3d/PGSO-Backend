<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use function PHPSTORM_META\map;

class Division extends Model
{
    use HasFactory;

    
    protected $table = 'divisions'; // Ensure this is correct
    protected $guard = [];

    protected $fillable = [
        'division_name',
        'office_location',
        'staff_id',
        'department_id',
        'is_archived',
    ];

    public function category()
{
    return $this->belongsTo(Category::class);
}

public function staff()
{
    return $this->belongsTo(User::class, 'staff_id');
}

public function department()
{
    return $this->belongsTo(Department::class, 'department_id');
}



}
