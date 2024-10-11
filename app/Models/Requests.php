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
    
    protected $guard = [];

    protected $fillable = [
        'control_no',
        'description',
        'office_name',
        'location_name',
        'overtime',
        'area',
        'fiscal_year',
        'file_path',
        'status',
        'user_id',
        'office_id',
        'location_id',
        'is_archived'
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
        
        $currentYear = now()->year;


        $validator = Validator::make($data, [
            'control_no' => ['nullable', 'string'],
            'description' => ['required', 'string'],
            'overtime' => ['nullable', 'in:Yes,No'],
            'area' => ['required', 'string'],
            'fiscal_year' => ['required', 'string', 'in:' . $currentYear],
            'user_id' => 'get|string|exists:Requests,id',
            'file_path' => [
                'required',
                'file',
                'mimes:pdf,jpg,png,docx',
                'max:5120',
            ],

            'status' => ['string', 'in:Pending, For Inspection, On-going, Completed'],
            'is_archived' => ['nullable', 'in: A, I']
        ]);

        return $validator;
    }
     public function office()
    {
        return $this->belongsTo(Office::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

}
