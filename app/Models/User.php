<?php

namespace App\Models;


use Illuminate\Support\Facades\Validator;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'middle_initial',
        'last_name',
        'email',
        'office',
        'designation',
        'user_type',
        'password',
        'isarchive'
    ];


    protected $attributes = [
        'isarchive' => 'A',  // Default value
    ];
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public static function validateUserAccount($data)
    {
        $user_type = user_type::pluck('name')->toArray();
        $office = Office::pluck('acronym')->toArray();

        $validator = Validator::make($data, [
            'first_name' => ['required', 'string','alpha'],
            'middle_initial' => ['required', 'string','alpha', 'max:5'],
            'last_name' => ['required', 'string','alpha'],
            'email' => ['required', 'email', 'unique:users,email'],
            'user_type' => ['required', 'in:' . implode(',', $user_type)],
            'office' => ['required', 'in:' . implode(',', $office)],
            'password' => ['required', 'string', 'min:8'],
            'isarchive' => ['nullable','in: A, I']
        ]);

        return $validator;
    }

}
