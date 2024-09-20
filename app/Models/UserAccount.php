<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\HasApiTokens;

class UserAccount extends Model
{
    use HasApiTokens, Notifiable, HasFactory;

    protected $fillable = [
        'firstname',
        'middleinital',
        'lastname',
        'email',
        'usertype',
        'office_college',
        'password',
    ];

    /**
     * Validate usertype and office_college.
     */
    public static function validateUserAccount($data)
    {
        $usertypes = UserType::pluck('name')->toArray();
        $offices = CollegeOffice::pluck('officename')->toArray();

        $validator = Validator::make($data, [
            'firstname' => ['required', 'string'],
            'middleinital' => ['required', 'string', 'max:2'],
            'lastname' => ['required', 'string'],
            'email' => ['required', 'email', 'unique:user_accounts,email'],
            'usertype' => ['required', 'in:' . implode(',', $usertypes)],
            'office_college' => ['required', 'in:' . implode(',', $offices)],
            'password' => ['required', 'string', 'min:8'],
        ]);

        return $validator;
    }
}
