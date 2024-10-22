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
        $validator = Validator::make($data, [
            'category_name' => ['required', 'string'],
            'division_id' => ['required', 'exists:divisions,id'], // Validate based on division_id
            'is_archived' => ['nullable', 'in:A,I']
        ]);

        return $validator;
    }

    public static function updatevalidateCategory($data)
    {
        $validator = Validator::make($data, [
            'category_name' => ['sometimes', 'required', 'string'],
            'division_id' => ['sometimes', 'exists:divisions,id'], // Validate based on division_id
            'is_archived' => ['nullable', 'in:A,I']
        ]);

        return $validator;
    }

    public function divisions()
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    public function requests()
    {
        return $this->belongsTo(Requests::class);
    }
}


