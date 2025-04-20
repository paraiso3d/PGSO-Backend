<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class Requests extends Model
{
    use HasFactory;

    protected $casts = [
        'updated_at' => 'string',
    ];

    protected $guarded = []; // Allows all fields to be mass-assignable

    protected $fillable = [
        'control_no',
        'request_title',
        'note',
        'description',
        'category_id',
        'personnel_ids',
        'team_lead_id',
        'file_path',
        'status',
        'requested_by',
        'date_requested',
        'date_completed',
        'is_archived',
    ];

    public static function generateControlNo()
    {
        $currentYear = now()->year;

        $lastRecord = self::where('control_no', 'like', "$currentYear-%")
            ->orderBy('control_no', 'desc')
            ->first();

        if ($lastRecord) {
            $lastControlNo = (int) substr($lastRecord->control_no, 5);
            $newControlNo = str_pad($lastControlNo + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newControlNo = '001';
        }

        return $currentYear . '-' . $newControlNo;
    }

    public static function validateRequest($data)
    {
        $validator = Validator::make($data, [
            'control_no' => ['nullable', 'string'],
            'request_title' => ['required', 'string'],
            'description' => ['required', 'string'],
            'category_id' => ['nullable', 'string'],
            'requested_by' => ['nullable', 'integer', 'exists:users,id'],
             // Changed rule for user_id
            'file_path' => [
                'required',
                // 'file',
                // 'mimes:pdf,jpg,png,docx',
                'max:5120',
            ],
            'status' => ['string'],
            'is_archived' => ['nullable', 'in:0,1'],

        ]);

        return $validator;
    }

    public function user()
{
    return $this->belongsTo(User::class, 'requested_by');
}

    public function office()
    {
        return $this->belongsTo(Department::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

// Request Model
public function category()
{
    return $this->belongsTo(Category::class);
}


public function division()
{
    return $this->belongsTo(Division::class, 'division_id');
}


}
