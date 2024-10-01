<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

class Category extends Model
{
    use HasFactory;
    protected $fillable = [
        'category_name', 
        'division',
        'is_archived'
    ];


    public static function validateCategory($data)
    {
        $division = Division::pluck('div_name')->toArray();
        

        $validator = Validator::make($data, [
            'category_name' => ['required', 'array'],
            'category_name.*' => ['required', 'string'],  
            'division' => ['required', 'in:' . implode(',', $division)],
            'is_archived' => ['nullable','in: A, I']
        ]);

        return $validator;
    }
}

