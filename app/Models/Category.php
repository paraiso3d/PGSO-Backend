<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

class Category extends Model
{
    use HasFactory;

    public $timestamps = true; 
    protected $fillable = [
        'category_name',
        'description',
        'is_archived',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public static function validateCategory($data)
{
    $validator = Validator::make($data, [
        'category_name' => ['required', 'string', 'unique:categories,category_name'],
        'description' => ['required','string'], 
        'is_archived' => ['nullable', 'in:0,1'],
    ]);

        return $validator;
    }

    public static function updatevalidateCategory($data)
    {
        $validator = Validator::make($data, [
            'category_name' => ['sometimes', 'required', 'string'],
            'description' => ['sometimes', 'string'], // Validate based on division_id
            'is_archived' => ['nullable', 'in:0,1'],
        ]);

        return $validator;
    }
    public function personnel()
    {
        return $this->belongsToMany(User::class, 'category_personnel', 'category_id', 'personnel_id');
    }
    

}


