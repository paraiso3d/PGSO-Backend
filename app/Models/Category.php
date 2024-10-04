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
        'division_id',
        'is_archived'
    ];


    public static function validateCategory($data)
    {
        $division = Division::pluck('div_name')->toArray();


        $validator = Validator::make($data, [
            'category_name' => ['required', 'string'],
            'division' => ['required', !empty($division) ? 'in:' . implode(',', $division) : ''],
           'division_id' => 'exists:divisions,division_id',
            'is_archived' => ['nullable','in: A, I']
        ]);


        return $validator;
    }

    public static function updatevalidateCategory($data)
    {
        $division = Division::pluck('div_name')->toArray();


        $validator = Validator::make($data, [
            'category_name' => ['sometimes','required', 'string'], 
            'division' => ['sometimes', 'in:' . implode(',', $division)],
            'division_id' => 'exists:divisions,division_id',
            'is_archived' => ['nullable','in: A, I']
        ]);


        return $validator;
    }
}


