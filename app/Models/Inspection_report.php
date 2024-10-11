<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

class Inspection_report extends Model
{
    use HasFactory;

    protected $fillable = ['description', 'recommendation','control_no','request_id','is_archived'];

    /**
     * Custom validation method for Inspection Report.
     *
     * @param array $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    public static function validateRequest(array $data)
    {
        // Define validation rules and messages
        $rules = [
            'description' => 'required|string|max:255',
            'recommendation' => 'required|string|max:255',
        ];

        return Validator::make($data, $rules);
    }
}
