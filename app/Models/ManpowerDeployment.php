<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

class ManpowerDeployment extends Model
{
    use HasFactory;

    protected $fillable = ['first_name', 'last_name', 'rating'];

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
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'rating' => 'required|numeric|max:10',
        ];

        return Validator::make($data, $rules);
    }
}
