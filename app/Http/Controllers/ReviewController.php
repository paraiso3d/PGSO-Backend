<?php

namespace App\Http\Controllers;
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
                'requests.category_id'
            )
                ->where('requests.id', $id)
                ->where('requests.is_archived', 'A')
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
            $office = Office::find($result->office_id);
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
            'description' => 'sometimes|string',
            'office_id' => 'required|exists:offices,id',
            'location_id' => 'required|exists:locations,id',
            'area' => 'sometimes|string',
            'fiscal_year' => 'sometimes|string',
            'file_path' => 'sometimes|file|mimes:pdf,jpg,png,docx|max:5120',
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

            $this->logAPICalls('updateReview', "", [], $response);

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

                $this->logAPICalls('updateReview', "", [], $response);

                return response()->json($response, 500);
            }

            // Fetch location and office based on the IDs from the request
            $locationId = $request->input('location_id');
            $officeId = $request->input('office_id');

            $location = Location::findOrFail($locationId);
            $office = Office::findOrFail($officeId);

            // Handle file upload
            if ($request->hasFile('file_path')) {
                // Get the uploaded file
                $file = $request->file('file_path');

                // Convert the uploaded file to base64
                $fileContents = file_get_contents($file->getRealPath());
                $base64Image = 'data:image/' . $file->extension() . ';base64,' . base64_encode($fileContents);

                // Call your saveImage method to handle the base64 image
                $path = $this->getSetting("ASSET_IMAGE_PATH");
                $fdateNow = now()->format('Y-m-d');
                $ftimeNow = now()->format('His');
                $filePath = (new AuthController)->saveImage($base64Image, 'asset', 'Asset-' . $existingRequest->control_no, $fdateNow . '_' . $ftimeNow);

                $fileUrl = asset('storage/' . $filePath);
            }

            // Handle categories (multiple checkboxes)
            $categories = $request->input('categories', []); // Fetch selected categories or default to empty

            // Update fields that are present in the request
            $reviewChangeData = [
                'description' => $request->filled('description') ? $request->input('description') : $existingRequest->description,
                'control_no' => $existingRequest->control_no,
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

            // Prepare the success response
            $response = [
                'isSuccess' => true,
                'message' => $reviewChange->wasRecentlyCreated ? 'Review change created successfully.' : 'Review change updated successfully.',
                'request' => [
                    'id' => $reviewChange->id,
                    'control_no' => $reviewChange->control_no,
                    'description' => $reviewChange->description,
                    'overtime' => $reviewChange->overtime,
                    'area' => $reviewChange->area,
                    'fiscal_year' => $reviewChange->fiscal_year,
                    'status' => $reviewChange->status,
                    'office_id' => $office->id,
                    'office_name' => $office->office_name,
                    'location_id' => $location->id,
                    'location_name' => $location->location_name,
                    'file_url' => asset('storage/' . $reviewChange->file_path), // Return the public URL of the uploaded file
                    'category_names' => $categoryNames, // Return category names
                ]
            ];

            $this->logAPICalls('updateReview', "", [], $response);

            return response()->json($response, 200);

        } catch (Throwable $e) {
            // Handle any exceptions
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to save the review change.',
                'error' => $e->getMessage(),
            ];

            $this->logAPICalls('updateReview', "", [], $response);

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
                ->where('is_archived', 'A')
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
            // Retrieve the record based on the provided control_no from the request
            $work = Requests::where('id', $request->id)
                ->firstOrFail(); // Throws a 404 error if no matching record is found

            // Update the status to "On-going"
            $work->update(['status' => 'Returned']);

            // Prepare the response
            $response = [
                'isSuccess' => true,
                'request_id' => $work->id,
                'status' => $work->status,
            ];

            // Log the API call (assuming this method works properly)
            $this->logAPICalls('updateWorkStatus', $work->id, [], $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Prepare the error response
            $response = [
                'isSuccess' => false,
                'message' => "Failed to update the review report status.",
                'error' => $e->getMessage(),
            ];

            // Log the API call with failure response
            $this->logAPICalls('updateWorkStatus', $request->id ?? '', [], $response);

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
