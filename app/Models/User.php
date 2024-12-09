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
        'last_name',
        'email',
        'password',
        'division_id',
        'department_id',
        'role_name',
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
            'last_name' => ['required', 'string', 'alpha_spaces'],
            'email' => ['required', 'email', 'unique:users,email'],
            'role_name' => ['required', 'string'],
            'department_id' => ['required', 'exists:departments,id'],
            'division_id' => ['required', 'exists:divisions,id'],
            'password' => ['required', 'string', 'min:8'],
            'is_archived' => ['nullable', 'in:A,I']
        ]);

        return $validator;
    }
    public function division()
    {
        return $this->belongsTo(Division::class);
    }
    
    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
