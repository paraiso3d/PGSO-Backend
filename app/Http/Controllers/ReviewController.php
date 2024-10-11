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

            // Log the ID being searched
            \Log::info('Fetching request with ID: ' . $id);

            // Enable query logging
            \DB::enableQueryLog();

            // Initialize the query with a join to fetch the full officename
            $result = Requests::select(
                'requests.id',
                'requests.control_no',
                'requests.description',
                'requests.office_name',
                'requests.location_name',
                'requests.overtime',
                'requests.file_path',
                'requests.area',
                'requests.fiscal_year',
                'requests.status'
            )
                ->where('requests.id', $id)
                ->where('requests.is_archived', 'A')
                ->first();

            // Log executed queries
            \Log::info(\DB::getQueryLog());

            // Check if the result is null
            if (!$result) {
                return response()->json([
                    'isSuccess' => true,
                    'message' => 'No request found.',
                    'data' => null,
                    'searched_id' => $id,
                ], 200);
            }

            return response()->json([
                'isSuccess' => true,
                'message' => 'Request retrieved successfully.',
                'data' => $result,
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

    // Method to update an existing request
    public function updateReview(Request $request, $id = null)
    {
        // Validate the incoming request data, including the location_id and office_id
        $validator = Validator::make($request->all(), [
            'description' => 'sometimes|string',
            'office_id' => 'required|exists:offices,id',  // Ensure office_id exists
            'location_id' => 'required|exists:locations,id',  // Ensure location_id exists
            'location_name' => 'sometimes|string',
            'overtime' => 'sometimes|string|in:Yes,No',  // Explicitly check for 'Yes' or 'No'
            'area' => 'sometimes|string',
            'fiscal_year' => 'sometimes|string',
            'file_path' => 'sometimes|file',
        ]);

        if ($validator->fails()) {
            $response = [
                'isSuccess' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ];
            $this->logAPICalls('saveReview', $id, $request->all(), $response);
            return response()->json($response, 500);
        }

        try {
            // Fetch the existing request using the provided ID
            $existingRequest = Requests::find($id);

            if (!$existingRequest) {
                $response = [
                    'isSuccess' => false,
                    'message' => "No request found with ID {$id}.",
                ];
                $this->logAPICalls('saveReview', $id, $request->all(), $response);
                return response()->json($response, 404);
            }

            // Fetch location and office based on the IDs from the request
            $location = Location::find($request->input('location_id'));
            $office = Office::find($request->input('office_id'));

            // Fetch categories for checkbox functionality
            $categories = Category::select('id', 'category_name')
                ->where('is_archived', 'A')  // Only active categories
                ->get();

            // Update fields that are present in the request
            $reviewChangeData = [
                'description' => $request->filled('description') ? $request->input('description') : $existingRequest->description,
                'control_no' => $existingRequest->control_no,
                'office_name' => $office->office_name,  // Use the name from the office
                'location_name' => $location->location_name,  // Use the name from the location
                'overtime' => $request->filled('overtime') ? $request->input('overtime') : $existingRequest->overtime,
                'area' => $request->filled('area') ? $request->input('area') : $existingRequest->area,
                'fiscal_year' => $request->filled('fiscal_year') ? $request->input('fiscal_year') : $existingRequest->fiscal_year,
                'file_path' => $request->hasFile('file') ? $request->file('file')->store('storage/uploads') : $existingRequest->file_path,
                'remarks' => $request->input('remarks'),
                'office_id' => $office->id,  // Store office ID
                'location_id' => $location->id,  // Store location ID
                'status' => 'For Inspection',
            ];

            // Update or create the review change record in the Requests table
            $reviewChange = Requests::updateOrCreate(['id' => $existingRequest->id], $reviewChangeData);

            // Update only if overtime or office_name have changed
            $requestUpdateData = [];
            if ($request->filled('overtime') && $existingRequest->overtime !== $request->input('overtime')) {
                $requestUpdateData['overtime'] = $request->input('overtime');
            }
            if (!empty($requestUpdateData)) {
                $existingRequest->update($requestUpdateData);
            }

            // Update the status of the existing request
            $existingRequest->update(['status' => 'For Inspection']);

            // Response for successful update including categories for checkbox
            $response = [
                'isSuccess' => true,
                'message' => $reviewChange->wasRecentlyCreated ? 'Review change created successfully.' : 'Review change updated successfully.',
                'data' => $reviewChange,
                'categories' => $categories,  // Categories for the checkbox function
            ];
            $this->logAPICalls('saveReview', $existingRequest->id, $request->all(), $response);

            return response()->json($response, 200);

        } catch (Throwable $e) {
            // Handle exception
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to save the review change.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('saveReview', $id ?? '', $request->all(), $response);
            return response()->json($response, 500);
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
