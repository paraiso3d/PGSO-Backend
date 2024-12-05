<?php

namespace App\Http\Controllers;
use App\Models\Department;
use DB;
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
    // Method to retrieve all requests
    public function getReviews($id)
    {
        // Generate a unique identifier for logging
        $requestId = (string) Str::uuid();

        try {
            // Validate the ID
            if (!is_numeric($id)) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'Invalid ID provided.',
                ];
                $this->logAPICalls('getReviews', $requestId, [], $response);
                return response()->json($response, 500);
            }

            // Fetch the request data along with related office, location, and category ID
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
                'requests.category_id',
                'requests.remarks'
            )
                ->where('requests.id', $id)
                ->where('requests.is_archived', '0')
                ->first();

            // Check if the request data is found
            if (!$result) {
                $response = [
                    'isSuccess' => true,
                    'message' => 'No request found.',
                    'data' => null,
                    'searched_id' => $id,
                ];
                $this->logAPICalls('getReviews', $requestId, [], $response);
                return response()->json($response, 500);
            }

            // Fetch the related office and location details
            $office = Department::find($result->office_id);
            $location = Location::find($result->location_id);

            // Decode category IDs and fetch category names
            $categoryIds = json_decode($result->category_id, true);
            $categoryNames = !empty($categoryIds)
                ? Category::whereIn('id', $categoryIds)->pluck('category_name')
                : [];

            // Prepare the response data
            $response = [
                'isSuccess' => true,
                'message' => 'Request retrieved successfully.',
                'request' => [
                    'id' => $result->id,
                    'control_no' => $result->control_no,
                    'description' => $result->description,
                    'overtime' => $result->overtime,
                    'area' => $result->area,
                    'fiscal_year' => $result->fiscal_year,
                    'status' => 'For Inspection',
                    'office_id' => $office->id,
                    'office_name' => $office->office_name,
                    'location_id' => $location->id,
                    'location_name' => $location->location_name,
                    'file_url' => asset('storage/' . $result->file_path),
                    'category_names' => $categoryNames,
                    'remarks' => $result->remarks
                ]
            ];

            $this->logAPICalls('getReviews', $requestId, [], $response);
            return response()->json($response, 200);

        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve the request.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('getReviews', $requestId, [], $response);
            return response()->json($response, 500);
        }
    }

    // Method to update the Review report.
    public function updateReview(Request $request, $id = null)
    {
        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'category_names' => 'sometimes|array',
            'category_names.*' => 'exists:categories,id',
            'overtime' => 'sometimes|string|in:Yes,No',
            'remarks' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            $response = [
                'isSuccess' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ];

            $this->logAPICalls('updateReview', "", [], $response);

            return response()->json($response, 422);
        }

        try {
            // Fetch the existing request using the provided ID
            $existingRequest = Requests::find($id);

            if (!$existingRequest) {
                $response = [
                    'isSuccess' => false,
                    'message' => "No request found with ID {$id}.",
                ];

                $this->logAPICalls('updateReview', "", [], $response);

                return response()->json($response, 404);
            }

            // // Ensure the status is "For Review"
            // if ($existingRequest->status !== "For Review") {
            //     $response = [
            //         'isSuccess' => false,
            //         'message' => 'The request cannot be updated because its status is not "For Review".',
            //     ];

            //     $this->logAPICalls('updateReview', "", [], $response);

            //     return response()->json($response, 403);
            // }

            // Update fields that are present in the request
            if ($request->filled('overtime')) {
                $existingRequest->overtime = $request->input('overtime');
            }

            if ($request->filled('remarks')) {
                $existingRequest->remarks = $request->input('remarks');
            }

            // Process category_names
            $categoryNames = $request->input('category_names', []); // Default to an empty array if not provided

            if (!empty($categoryNames)) {
                // Store category IDs as JSON
                $existingRequest->category_id = json_encode($categoryNames);
            }

            // Auto-update status to "For Inspection"
            $existingRequest->status = "For Inspection";

            // Save the updated request
            $existingRequest->save();

            // Fetch the category names based on the stored IDs
            $categoryIds = json_decode($existingRequest->category_id, true) ?? [];
            $fetchedCategoryNames = Category::whereIn('id', $categoryIds)->pluck('category_name');

            // Prepare the success response
            $response = [
                'isSuccess' => true,
                'message' => 'Request updated successfully.',
                'request' => [
                    'id' => $existingRequest->id,
                    'control_no' => $existingRequest->control_no,
                    'description' => $existingRequest->description,
                    'overtime' => $existingRequest->overtime,
                    'area' => $existingRequest->area,
                    'fiscal_year' => $existingRequest->fiscal_year,
                    'status' => $existingRequest->status, // Updated status
                    'office_id' => $existingRequest->office_id,
                    'location_id' => $existingRequest->location_id,
                    'file_path' => $existingRequest->file_path,
                    'category_names' => $fetchedCategoryNames, // Return category names
                    'remarks' => $existingRequest->remarks,
                ]
            ];

            $this->logAPICalls('updateReview', "", [], $response);

            return response()->json($response, 200);

        } catch (Throwable $e) {
            // Handle any exceptions
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to update the request.',
                'error' => $e->getMessage(),
            ];

            $this->logAPICalls('updateReview', "", [], $response);

            return response()->json($response, 500);
        }
    }

    public function editReview(Request $request, $id)
    {
        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'categories' => 'sometimes|array',
            'categories.*' => 'exists:categories,id',
            'overtime' => 'sometimes|string|in:Yes,No',
            'remarks' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            $response = [
                'isSuccess' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ];

            $this->logAPICalls('updateBasicFields', "", [], $response);

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

                $this->logAPICalls('updateBasicFields', "", [], $response);

                return response()->json($response, 500);
            }

            // Update only the fields specified in the request
            if ($request->filled('overtime')) {
                $existingRequest->overtime = $request->input('overtime');
            }

            if ($request->filled('remarks')) {
                $existingRequest->remarks = $request->input('remarks');
            }

            // Handle categories (multiple checkboxes)
            if ($request->has('categories')) {
                $categories = $request->input('categories', []);
                $existingRequest->category_id = json_encode($categories);
            }

            // Save the changes to the database
            $existingRequest->save();

            // Fetch the category names based on the stored IDs
            $categoryIds = json_decode($existingRequest->category_id, true);
            $categoryNames = Category::whereIn('id', $categoryIds)->pluck('category_name');

            // Prepare the success response
            $response = [
                'isSuccess' => true,
                'message' => 'Request fields updated successfully.',
                'request' => [
                    'id' => $existingRequest->id,
                    'overtime' => $existingRequest->overtime,
                    'remarks' => $existingRequest->remarks,
                    'category_names' => $categoryNames, // Return category names
                ],
            ];

            $this->logAPICalls('updateBasicFields', "", [], $response);

            return response()->json($response, 200);

        } catch (Throwable $e) {
            // Handle any exceptions
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to update request fields.',
                'error' => $e->getMessage(),
            ];

            $this->logAPICalls('updateBasicFields', "", [], $response);

            return response()->json($response, 500);
        }
    }

    //DROPDOWN FOR EDITING LOCATION IN REVIEW
    public function getDropdownOptionsReviewlocation(Request $request)
    {
        try {

            $location = Location::select('id', 'location_name')
                ->where('is_archived', '0')
                ->get();

            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown data retrieved successfully.',
                'location' => $location
            ];

            // Log the API call
            $this->logAPICalls('getDropdownOptionsReviewlocation', "", [], $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown data.',
                'error' => $e->getMessage()
            ];

            // Log the error
            $this->logAPICalls('getDropdownOptionsReviewlocation', "", [], $response);

            return response()->json($response, 500);
        }
    }

    //DROPDOWN FOR EDITING OFFICE IN REVIEW
    public function getDropdownOptionsReviewoffice(Request $request)
    {
        try {
            $office = Office::select('id', 'office_name')
                ->where('is_archived', '0')
                ->get();

            // Build the response
            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown data retrieved successfully.',
                'office' => $office,
            ];

            // Log the API call
            $this->logAPICalls('getDropdownOptionsReviewoffice', "", [], $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown data.',
                'error' => $e->getMessage()
            ];

            // Log the error
            $this->logAPICalls('getDropdownOptionsReviewoffice', "", [], $response);

            return response()->json($response, 500);
        }
    }

    public function returnReview(Request $request)
    {
        try {
            // Retrieve the currently logged-in user
            $user = auth()->user();

            // Retrieve the record based on the provided control_no from the request
            $requests = Requests::where('id', $request->id)->firstOrFail();

            // Update the status to "Returned"
            $requests->update(['status' => 'Returned']);

            // Prepare the full name of the currently logged-in user
            $fullName = "{$user->first_name} {$user->middle_initial} {$user->last_name}";

            // Prepare the response
            $response = [
                'isSuccess' => true,
                'messsage' => 'Request returned.',
                'request_id' => $requests->id,
                'status' => $requests->status,
                'user_id' => $user->id,
                'user' => $fullName,
            ];

            // Log the API call (assuming this method works properly)
            $this->logAPICalls('returnReview', $requests->id, [], $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Prepare the error response
            $response = [
                'isSuccess' => false,
                'message' => "Failed to update the review report status.",
                'error' => $e->getMessage(),
            ];

            // Log the API call with failure response
            $this->logAPICalls('returnReview', $request->id ?? '', [], $response);

            return response()->json($response, 500);
        }
    }

    public function getSetting(string $code)
    {
        try {
            $value = DB::table('settings')
                ->where('setting_code', $code)
                ->value('setting_value');
        } catch (Throwable $e) {
            return $e->getMessage();
        }
        return $value;
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
