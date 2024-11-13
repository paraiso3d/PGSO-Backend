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
        'designation',
        'password',
        'office_id',
        'user_type_id',
        'is_archived'
    ];

    protected $attributes = [
        'is_archived' => '0',  // Default value
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
        // Retrieve arrays of valid user types and offices

        // Validate user data
        $validator = Validator::make($data, [
            'first_name' => ['required', 'string', 'alpha_spaces'],
            'middle_initial' => ['required', 'string', 'alpha_spaces', 'max:5'],
            'last_name' => ['required', 'string', 'alpha_spaces'],
            'email' => ['required', 'email', 'unique:users,email'],
            'user_type_id' => ['required', 'exists:user_types,id'],
            'office_id' => ['required', 'exists:offices,id'],
            'password' => ['required', 'string', 'min:8'],
            'is_archived' => ['nullable', 'in:A,I']
        ]);

        return $validator;
    }
    public function user_types()
    {
        return $this->belongsTo(user_type::class, 'user_type_id');
    }

    // Define the relationship with Office
    public function office()
    {
        return $this->belongsTo(Office::class, 'office_id');
    }
}
