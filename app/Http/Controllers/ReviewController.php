<?php

namespace App\Http\Controllers;
use App\Models\Accomplishment_report;
use App\Models\Category;
use App\Models\Control_Request;
use App\Models\Division;
use App\Models\Office;
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

            // Initialize the query with a join to fetch the full officename
            $result = Control_Request::select(
                'control__requests.id',
                'control__requests.control_no',
                'control__requests.description',
                'offices.officename as officename', // Fetch the full officename from the offices table
                'control__requests.location_name',
                'control__requests.overtime',
                'control__requests.file_path',
                'control__requests.area',
                'control__requests.fiscal_year',
                'control__requests.status'
            )
                ->join('offices', 'control__requests.officename', '=', 'offices.acronym') // Adjust the join condition to match the acronym in the offices table
                ->where('control__requests.id', $id)
                ->where('control__requests.is_archived', 'A')
                ->first(); // Get the first matching record

            // Check if the result is null
            if (!$result) {
                return response()->json([
                    'isSuccess' => true,
                    'message' => 'No request found.',
                    'data' => null,
                ], 200);
            }

            return response()->json([
                'isSuccess' => true,
                'message' => 'Request retrieved successfully.',
                'data' => $result, // Return the found request
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
        // Retrieve the list of office acronyms as an array
        $officenames = Office::pluck('officename')->toArray(); // Retrieve full office names
        $officeAcronyms = Office::pluck('acronym')->toArray(); // Retrieve acronyms for validation

        // Validate the incoming request data using the `in` rule with an array
        $validator = Validator::make($request->all(), [
            'description' => 'required|string',
            'officename' => ['required', Rule::in($officeAcronyms)], // Validate using acronyms
            'location_name' => 'nullable|string',
            'overtime' => 'nullable|string|in:Yes,No', // Explicitly check for 'Yes' or 'No'
            'area' => 'nullable|string',
            'fiscal_year' => 'nullable|string',
            'file' => 'nullable|file',
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

            // If no request is found by ID, return an error response
            if (!$existingRequest) {
                $response = [
                    'isSuccess' => false,
                    'message' => "No request found with ID {$id}.",
                ];
                $this->logAPICalls('saveReview', $id, $request->all(), $response);
                return response()->json($response, 404);
            }

            // Check if the overtime field is being updated
            $overtimeUpdated = $request->has('overtime') && $existingRequest->overtime !== $request->input('overtime');

            // Check if the officename field is being updated
            $officenameUpdated = $request->has('officename') && $existingRequest->officename !== $request->input('officename');

            // Handle file upload and get the file path
            $filePath = $existingRequest->file_path;
            if ($request->hasFile('file')) {
                $filePath = $request->file('file')->store('storage/uploads');
            }

            // Find the full office name corresponding to the acronym provided
            $fullOfficeName = Office::where('acronym', $request->input('officename'))->value('officename');

            // Prepare the data for saving to the Control_Request table
            $reviewChangeData = [
                'request_id' => $existingRequest->id, // Reference to the original request
                'description' => $request->input('description'),
                'control_no' => $existingRequest->control_no, // Keep existing control_no
                'officename' => $fullOfficeName, // Store the full office name in Control_Request
                'location_name' => $request->input('location_name'),
                'overtime' => $request->input('overtime'),
                'area' => $request->input('area'),
                'fiscal_year' => $request->input('fiscal_year'),
                'file_path' => $filePath,
                'remarks' => $request->input('remarks'),
                'status' => 'For Inspection',
            ];

            // Check for existing review change record
            $reviewChange = Control_Request::where('request_id', $existingRequest->id)->first();

            if ($reviewChange) {
                // Update the existing review change record
                $reviewChange->update($reviewChangeData);
            } else {
                // Create a new entry in the Control_Request table
                $reviewChange = Control_Request::create($reviewChangeData);
            }

            // Update the Requests table if the overtime or officename fields have changed
            $requestUpdateData = [];
            if ($overtimeUpdated) {
                $requestUpdateData['overtime'] = $request->input('overtime');
            }
            if ($officenameUpdated) {
                $requestUpdateData['officename'] = $request->input('officename'); // Update with acronym in Requests table
            }
            if (!empty($requestUpdateData)) {
                $existingRequest->update($requestUpdateData);
            }

            // Update the status of the existing request
            $existingRequest->update(['status' => 'For Inspection']);

            // Response for successful update or create in the Control_Request table
            $response = [
                'isSuccess' => true,
                'message' => $reviewChange->wasRecentlyCreated ? 'Review change created successfully.' : 'Review change updated successfully.',
                'data' => $reviewChange,
            ];
            $this->logAPICalls('saveReview', $existingRequest->id, $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Response for failed operation
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to save the review change.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('saveReview', $id ?? '', $request->all(), $response);
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
