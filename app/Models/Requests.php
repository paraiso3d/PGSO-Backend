<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class Requests extends Model
{
    use HasFactory;
    protected $guard = [];

    protected $fillable = [
        'control_no',
        'description',
        'officename',
        'location_name',
        'overtime',
        'area',
        'category_name',
        'fiscal_year',
        'file_name',
        'status',
        'user_id',
    ];


    public static function generateControlNo()
    {
        $currentYear = now()->year; 

       
        $lastRecord = self::where('control_no', 'like', "$currentYear-%")
                            ->orderBy('control_no', 'desc')
                            ->first();

        if ($lastRecord) {
           
            $lastControlNo = (int)substr($lastRecord->control_no, 5);
            $newControlNo = str_pad($lastControlNo + 1, 3, '0', STR_PAD_LEFT);
        } else {
          
            $newControlNo = '001';
        }

        return $currentYear . '-' . $newControlNo;
    }


    public static function validateRequest($data)
    {
        $category = Category::pluck('category_name')->toArray();
        $office = Office::pluck('acronym')->toArray();
        $location = Location::pluck('location_name')->toArray();
        

        $validator = Validator::make($data, [
            'control_no' => ['nullable', 'string'],
            'description'=> ['required', 'string'],
            'officename' => ['required', 'in:' . implode(',', $office)],
            'location_name'=> ['required','in:'. implode(',', $location)],
            'overtime' => ['nullable', 'in:Yes,No'], 
            'area'=> ['required', 'string'],
            'category_name' => ['required', 'in:' . implode(',', $category)],
            'fiscal_year'=> ['required', 'string'],
            'user_id' => 'get|string|exists:Requests,id',
          
            'file_name' => [
            'required',              
            'file',                  
            'mimes:pdf,jpg,png,docx',
            'max:5120',              
        ],

            'status' => ['string', 'in:Pending,Ongoing,For Inspection,Completed'],
            'isarchive' => ['nullable','in: A, I']
        ]);

        return $validator;
    }


    
}
