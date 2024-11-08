<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Division;
use App\Models\Requests;
use App\Models\Control_Request;
use Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\Inspection_report;
use Illuminate\Http\Request;
use Throwable;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Session;

class InspectionController extends Controller
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

    public function createInspection(Request $request, $id = null)
    {

        // Validate the incoming request data using the `in` rule with an array
        $validator = Validator::make($request->all(), [
            'description' => 'required|string|max:255',
            'recommendation' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            $response = [
                'isSuccess' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ];
            $this->logAPICalls('createInspection', $id, $request->all(), $response);
            return response()->json($response, 500);
        }

        try {
            // Fetch the existing request using the provided ID or some identifier
            $existingRequest = Requests::find($id);

            // If no request is found by ID, return an error response
            if (!$existingRequest) {
                $response = [
                    'isSuccess' => false,
                    'message' => "No request found.",
                ];
                $this->logAPICalls('createInspection', $id, $request->all(), $response);
                return response()->json($response, 404);
            }


            // Prepare the data for creating a new Inspection report
            $inspectionData = [
                'description' => $request->input('description'),
                'recommendation' => $request->input('recommendation'),
                'control_no' => $existingRequest->control_no, // Link to the existing control_no from Requests table
                'request_id' => $existingRequest->id,
            ];

            // Create a new entry in the Inspection_report table
            $newInspection = Inspection_report::create($inspectionData);

            // Response for successful creation in the Inspection_report table
            $response = [
                'isSuccess' => true,
                'message' => 'Inspection report created successfully.',
                'inspection' => $newInspection,
            ];
            $this->logAPICalls('createInspection', $existingRequest->id, $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Response for failed operation
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to create the inspection report.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('createInspection', $id ?? '', $request->all(), $response);
            return response()->json($response, 500);
        }
    }

    public function getInspections(Request $request, $Request_id)
    {
        try {
            // Check if the `request_id` exists in the Requests table
            $requestExists = Requests::where('id', $Request_id)->exists();

            if (!$requestExists) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => "No request found for this id: {$Request_id}.",
                    'inspection' => [],  // Empty inspection array for consistency
                ], 500);
            }

            // Fetch inspection reports related to the `request_id`
            $inspectionReports = Inspection_report::where('is_archived', 'A')
                ->where('request_id', $Request_id)
                ->get(['request_id','control_no', 'id', 'description', 'recommendation']);

            // Map each inspection to the required structure
            $inspections = $inspectionReports->map(function ($inspection) {
                return [
                    'request_id' =>$inspection->request_id,
                    'id' => $inspection->id,
                    'control_no' => $inspection->control_no,
                    'description' => $inspection->description,
                    'recommendation' => $inspection->recommendation,
                ];
            })->values(); // Ensure it's an array instead of a collection

            // Prepare the response
            $response = [
                'isSuccess' => true,
                'message' => 'Inspections retrieved successfully.',
                'inspections' => $inspections,  // Consistently named 'inspections' key
            ];

            // Log API calls
            $this->logAPICalls('getInspections', $Request_id, $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve inspections.',
                'error' => $e->getMessage(),
                'inspections' => [],  // Empty inspections array for consistency
            ];

            // Log the error
            $this->logAPICalls('getInspections', $Request_id ?? '', $request->all(), $response);

            return response()->json($response, 500);
        }
    }

    public function updateInspection(Request $request, $id)
    {
        // Validate the incoming request data using Laravel's built-in validation method
        $validator = Validator::make($request->all(), [
            'description' => 'sometimes|string|max:255',
            'recommendation' => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            $response = [
                'isSuccess' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ];
            $this->logAPICalls('updateInspection', $id, $request->all(), $response);
            return response()->json($response, 500);
        }

        try {
            // Fetch the existing inspection report using the provided ID
            $existingInspection = Inspection_report::find($id);

            // If no inspection is found by ID, return an error response
            if (!$existingInspection) {
                $response = [
                    'isSuccess' => false,
                    'message' => "No inspection report found.",
                ];
                $this->logAPICalls('updateInspection', $id, $request->all(), $response);
                return response()->json($response, 404);
            }

            // Prepare the data for updating the inspection report
            $inspectionData = [
                'description' => $request->filled('description') ? $request->input('description') : $existingInspection->description,
                'recommendation' => $request->input('recommendation') ? $request->input('recommendation') : $existingInspection->recommendation,
            ];

            // Update the existing inspection report with the new data
            $existingInspection->update($inspectionData);

            // Response for successful update in the Inspection_report table
            $response = [
                'isSuccess' => true,
                'message' => 'Inspection report updated successfully.',
                'inspection' => $existingInspection,
            ];
            $this->logAPICalls('updateInspection', $existingInspection->id, $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Response for failed operation
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to update the inspection report.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('updateInspection', $id, $request->all(), $response);
            return response()->json($response, 500);
        }
    }

    public function deleteInspection(Request $request)
    {
        try {
            // Retrieve the inspection report based on the request_id provided in the request
            $inspection = Inspection_report::where('id', $request->id)
                ->where('is_archived', 'A') // Ensure the report is not already archived
                ->firstOrFail();

            // Update the `is_archived` status to "I" (assuming "I" means inactive/archived)
            $inspection->update(['is_archived' => 'I']);

            // Prepare the response
            $response = [
                'isSuccess' => true,
                'message' => "Inspection report successfully archived."
            ];

            // Log the API call (assuming this method works properly)
            $this->logAPICalls('deleteInspection', $inspection->request_id, $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Prepare the error response
            $response = [
                'isSuccess' => false,
                'message' => "Failed to archive the inspection report.",
                'error' => $e->getMessage()
            ];

            // Log the API call with failure response
            $this->logAPICalls('deleteInspection', $request->request_id ?? '', $request->all(), $response);

            return response()->json($response, 500);
        }
    }

    public function updateWorkStatus(Request $request)
    {
        try {
            // Retrieve the record based on the provided control_no from the request
            $work = Requests::where('id', $request->id)
                ->firstOrFail(); // Throws a 404 error if no matching record is found

            // Update the status to "On-going"
            $work->update(['status' => 'On-going']);

            // Prepare the response
            $response = [
                'isSuccess' => true,
                'request_id' => $work->id,
                'status' => $work->status,
            ];

            // Log the API call (assuming this method works properly)
            $this->logAPICalls('updateWorkStatus', $work->id, $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Prepare the error response
            $response = [
                'isSuccess' => false,
                'message' => "Failed to update the work status.",
                'error' => $e->getMessage(),
            ];

            // Log the API call with failure response
            $this->logAPICalls('updateWorkStatus', $request->id ?? '', $request->all(), $response);

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