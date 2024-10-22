<?php

namespace App\Http\Controllers;
use App\Models\Accomplishment_report;
use App\Models\Category;
use App\Models\Division;
use App\Models\Office;
use App\Models\Location;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Requests;
use Illuminate\Http\Request;
use Throwable;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Session;
use Illuminate\Validation\Rule;


class ReviewController extends Controller
{

    // public function __construct(Request $request)
    // {
    //     // Retrieve the authenticated user
    //     $user = $request->user();

    //     // Apply middleware based on the user type
    //     if ($user && $user->user_type === 'Administrator') {
    //         $this->middleware('UserTypeAuth:Administrator')->only(['updateReview', 'getReviews']);
    //     }

    //     if ($user && $user->user_type === 'Supervisor') {
    //         $this->middleware('UserTypeAuth:Supervisor')->only(['updateReview', 'getReviews']);
    //     }

    //     if ($user && $user->user_type === 'TeamLeader') {
    //         $this->middleware('UserTypeAuth:TeamLeader')->only(['updateReview', 'getReviews']);
    //     }

    //     if ($user && $user->user_type === 'Controller') {
    //         $this->middleware('UserTypeAuth:Controller')->only(['updateReview', 'getReviews']);
    //     }

    //     if ($user && $user->user_type === 'DeanHead') {
    //         $this->middleware('UserTypeAuth:DeanHead')->only(['getReviews']);
    //     }
    // }
    // Method to retrieve all requests

    public function getReviews($id)
    {
        try {
            // Validate the ID
            if (!is_numeric($id)) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Invalid ID provided.',
                ], 400);
            }
    
            // Fetch the request data along with the category_id
            $result = Requests::select(
                'requests.id',
                'requests.control_no',
                'requests.description',
                'requests.office_id',
                'requests.location_id',
                'requests.overtime',
                'requests.file_path',
                'requests.area',
                'requests.fiscal_year',
                'requests.category_id'  // Add the category_id to the result
            )
            ->where('requests.id', $id)
            ->where('requests.is_archived', 'A')
            ->first();
    
            // Check if the result is null
            if (!$result) {
                return response()->json([
                    'isSuccess' => true,
                    'message' => 'No request found.',
                    'data' => null,
                    'searched_id' => $id,
                ], 200);
            }
    
            // Decode the category_id (stored as JSON) to get an array of category IDs
            $categoryIds = json_decode($result->category_id, true);
    
            // Fetch the corresponding category names using the IDs
            $categoryNames = [];
            if (!empty($categoryIds)) {
                $categoryNames = Category::whereIn('id', $categoryIds)->pluck('category_name');
            }
    
            // Log the executed queries (if needed)
            \Log::info(\DB::getQueryLog());
    
            // Return the result including the category names
            return response()->json([
                'isSuccess' => true,
                'message' => 'Request retrieved successfully.',
                'data' => $result,
                'category_names' => $categoryNames,  
            ], 200);
    
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve the request.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('getReviews', '', ['id' => $id], $response);
    
            return response()->json($response, 500);
        }
    }

public function updateReview(Request $request, $id = null)
{
    // Validate the incoming request data
    $validator = Validator::make($request->all(), [
        'description' => 'sometimes|string',
        'office_id' => 'required|exists:offices,id',
        'location_id' => 'required|exists:locations,id',
        'area' => 'sometimes|string',
        'fiscal_year' => 'sometimes|string',
        'file_path' => 'sometimes|file',
        'categories' => 'sometimes|array',
        'categories.*' => 'exists:categories,id',
        'overtime' => 'sometimes|string|in:Yes,No',
        'remarks' => 'sometimes|string',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Validation error',
            'errors' => $validator->errors(),
        ], 422);
    }

    try {
        // Fetch the existing request using the provided ID
        $existingRequest = Requests::find($id);

        if (!$existingRequest) {
            return response()->json([
                'isSuccess' => false,
                'message' => "No request found with ID {$id}.",
            ], 404);
        }

        // Fetch location and office based on the IDs from the request
        $location = Location::find($request->input('location_id'));
        $office = Office::find($request->input('office_id'));

        // Handle file upload
        $filePath = $existingRequest->file_path;
        if ($request->hasFile('file_path')) {
            $filePath = $request->file('file_path')->store('uploads');
        }

        // Handle categories (multiple checkboxes)
        $categories = $request->input('categories', []); // Fetch selected categories or default to empty

        // Update fields that are present in the request
        $reviewChangeData = [
            'description' => $request->filled('description') ? $request->input('description') : $existingRequest->description,
            'control_no' => $existingRequest->control_no,
            'office_name' => $office->office_name,
            'location_name' => $location->location_name,
            'overtime' => $request->filled('overtime') ? $request->input('overtime') : $existingRequest->overtime,
            'area' => $request->filled('area') ? $request->input('area') : $existingRequest->area,
            'fiscal_year' => $request->filled('fiscal_year') ? $request->input('fiscal_year') : $existingRequest->fiscal_year,
            'file_path' => $filePath,
            'remarks' => $request->filled('remarks') ? $request->input('remarks') : $existingRequest->remarks,
            'office_id' => $office->id,
            'location_id' => $location->id,
            'status' => 'For Inspection',
        ];

        // Update or create the review change record in the Requests table
        $reviewChange = Requests::updateOrCreate(['id' => $existingRequest->id], $reviewChangeData);

        // Store category IDs as JSON
        if (!empty($categories)) {
            $reviewChange->category_id = json_encode($categories);  // Store category IDs as JSON
            $reviewChange->save();
        }

        // Fetch the category names based on the stored IDs
        $categoryIds = json_decode($reviewChange->category_id, true);
        $categoryNames = Category::whereIn('id', $categoryIds)->pluck('category_name');

        // Return the updated response with category names
        return response()->json([
            'isSuccess' => true,
            'message' => $reviewChange->wasRecentlyCreated ? 'Review change created successfully.' : 'Review change updated successfully.',
            'data' => $reviewChange,
            'category_names' => $categoryNames,  
        ], 200);

    } catch (Throwable $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Failed to save the review change.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    
    //DROPDOWN FOR EDITING LOCATION IN REVIEW
    public function getDropdownOptionsReviewlocation(Request $request)
    {
        try {

            $location = Location::select('id', 'location_name')
                ->where('is_archived', 'A')
                ->get();

            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown data retrieved successfully.',
                'location' => $location
            ];

            // Log the API call
            $this->logAPICalls('getDropdownOptionsReviewlocation', "", $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown data.',
                'error' => $e->getMessage()
            ];

            // Log the error
            $this->logAPICalls('getDropdownOptionsReviewlocation', "", $request->all(), $response);

            return response()->json($response, 500);
        }
    }

    //DROPDOWN FOR EDITING OFFICE IN REVIEW
    public function getDropdownOptionsReviewoffice(Request $request)
    {
        try {
            $office = Office::select('id', 'office_name')
                ->where('is_archived', 'A')
                ->get();

            // Build the response
            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown data retrieved successfully.',
                'office' => $office,
            ];

            // Log the API call
            $this->logAPICalls('getDropdownOptionsReviewoffice', "", $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown data.',
                'error' => $e->getMessage()
            ];

            // Log the error
            $this->logAPICalls('getDropdownOptionsReviewoffice', "", $request->all(), $response);

            return response()->json($response, 500);
        }
    }

    // Log API calls for requests
    public function logAPICalls(string $methodName, string $requestId, array $param, array $resp)
    {
        try {
            \App\Models\ApiLog::create([
                'method_name' => $methodName,
                'request_id' => $requestId,
                'api_request' => json_encode($param),
                'api_response' => json_encode($resp),
            ]);
        } catch (Throwable $e) {
            return false;
        }
        return true;
    }
}
