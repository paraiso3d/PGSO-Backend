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
        'is_archived',
        'user_id'
    ];

    public static function validateCategory($data)
{
    $validator = Validator::make($data, [
        'category_name' => ['required', 'string', 'unique:categories,category_name'],
        'division_id' => ['required','integer', 'exists:divisions,id'], 
        'is_archived' => ['nullable', 'in:0,1'],
        'team_leader' => ['required','integer', 'exists:users,id'], 
    ]);

        return $validator;
    }

    public static function updatevalidateCategory($data)
    {
        $validator = Validator::make($data, [
            'category_name' => ['sometimes', 'required', 'string'],
            'division_id' => ['sometimes', 'exists:divisions,id'], // Validate based on division_id
            'is_archived' => ['nullable', 'in:0,1'],
            'team_leader' => ['required','integer', 'exists:users,id'], 
        ]);

        return $validator;
    }

}


