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
        'avatar',
        'age',
        'gender',
        'number',
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
        $validator = Validator::make($data, [
            'first_name' => ['required', 'string', 'alpha_spaces'],
            'last_name' => ['required', 'string', 'alpha_spaces'],
            'email' => ['required', 'email', 'unique:users,email'],
            'role_name' => ['required', 'string'],
            'department_id' => ['required', 'exists:departments,id'],
            'division_id' => ['required', 'exists:divisions,id'],
            'password' => [
                'string',
                'min:8',
                'regex:/^(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/'
            ],
            'age' => ['required', 'integer', 'min:1', 'max:100'], // 
            'gender' => ['required', 'in:Male,Female'], //
            'number' => ['required', 'string', 'unique:users,number', 'regex:/^\d{11,15}$/'], 
            'is_archived' => ['nullable', 'in:A,I'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'] 
        ], [
            'password.regex' => 'The password must contain at least one uppercase letter, one number, and one special character.',
            'age.max' => 'The maximum age allowed is 100.',
            'gender.in' => 'The gender must be either Male or Female.',
            'number.regex' => 'The number must be between 10 and 15 digits.',
            'profile.image' => 'The profile must be a valid image file.',
            'profile.mimes' => 'The profile must be a file of type: jpeg, png, jpg, gif.',
            'profile.max' => 'The profile image must not exceed 2MB.'
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

   // In the User model
// In User Model
public function categories()
{
    return $this->belongsToMany(Category::class, 'category_personnel', 'personnel_id', 'category_id')
                ->withPivot('is_team_lead');  // Ensure you're including 'is_team_lead'
}

public function isTeamLeadForCategory($categoryId)
{
    return $this->categories()
                ->wherePivot('category_id', $categoryId)
                ->wherePivot('is_team_lead', true)
                ->exists();
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
