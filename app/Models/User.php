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
        'status',
        'profile_img',
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
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/'
            ],
            'is_archived' => ['nullable', 'in:A,I'],
            'profile_img'=> ['nullable']
        ], [
            'password.regex' => 'The password must contain at least one uppercase letter, one number, and one special character.'
        ]);
    
        return $validator;
    }
    
    public function divisions()
    {
        return $this->belongsTo(Division::class, 'division_id', 'id');
    }
    
    public function departments()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function categories()
{
    return $this->belongsToMany(Category::class, 'category_personnel', 'personnel_id', 'category_id');
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
