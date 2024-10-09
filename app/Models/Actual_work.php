<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

class Actual_work extends Model
{
    use HasFactory;

    protected $fillable = ['recommended_action', 'remarks','control_no','control_request_id','is_archived'];

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
            'recommended_action' => 'required|string|max:255',
            'remarks' => 'required|string|max:255',
        ];

        return Validator::make($data, $rules);
    }
}
