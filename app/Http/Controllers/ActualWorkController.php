<?php

namespace App\Http\Controllers;

use App\Models\Actual_work;
use App\Models\ManpowerDeployment;
use App\Models\Manpower;
use App\Models\Requests;
use Illuminate\Http\Request;
use Validator;
use Throwable;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Session;
use Illuminate\Support\Facades\Auth;



class ActualWorkController extends Controller
{

    //CREATE WORK REPORT    
    public function createWorkreport(Request $request, $id = null)
    {

        // Validate the incoming request data using the `in` rule with an array
        $validator = Validator::make($request->all(), [
            'recommended_action' => 'required|string|max:255',
            'remarks' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            $response = [
                'isSuccess' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ];
            $this->logAPICalls('createWorkreport', $id, [], $response);
            return response()->json($response, 500);
        }

        try {

            $user = auth()->user();

            // Fetch the existing request using the provided ID or some identifier
            $existingRequest = Requests::find($id);

            if (!$existingRequest) {
                $response = [
                    'isSuccess' => false,
                    'message' => "No request found.",
                ];
                $this->logAPICalls('createWorkreport', $id, [], $response);
                return response()->json($response, 500);
            }

            // Prepare the data for creating a new Inspection report
            $actualworkData = [
                'recommended_action' => $request->input('recommended_action'),
                'remarks' => $request->input('remarks'),
                'control_no' => $existingRequest->control_no, // Link to the existing control_no from Requests table
                'request_id' => $existingRequest->id,
                'user_id' => $user->id,
            ];

            // Create a new entry in the Actual_work table
            $newWorkreport = Actual_work::create($actualworkData);

            // Add full name of User to the inspection response
            $newWorkreport->full_name = "{$user->first_name} {$user->middle_initial} {$user->last_name}";

            // Response for successful creation in the Actual_work table
            $response = [
                'isSuccess' => true,
                'message' => 'Actual work report created successfully.',
                'actualwork' => [
                    'id' => $newWorkreport->id,
                    'recommended_action' => $newWorkreport->recommended_action,
                    'remarks' => $newWorkreport->remarks,
                    'control_no' => $newWorkreport->control_no,
                    'request_id' => $newWorkreport->request_id,
                    'user_id' => $newWorkreport->user_id,
                    'full_name' => $newWorkreport->full_name,
                    'created_at' => $newWorkreport->created_at,
                    'updated_at' => $newWorkreport->updated_at,
                ],
            ];
            $this->logAPICalls('createInspection', $existingRequest->id, [], $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Response for failed operation
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to create the actual work report.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('createInspection', $id ?? '', [], $response);
            return response()->json($response, 500);
        }
    }

    //UPDATE WORK REPORT
    public function updateWorkreport(Request $request, $id)
    {
        // Validate the incoming request data using Laravel's built-in validation method
        $validator = Validator::make($request->all(), [
            'recommended_action' => 'sometimes|string|max:255',
            'remarks' => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            $response = [
                'isSuccess' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ];
            $this->logAPICalls('updateWorkreport', $id, [], $response);
            return response()->json($response, 500);
        }

        try {

            $user = auth()->user();

            // Fetch the existing inspection report using the provided ID
            $existingWorkreport = Actual_work::find($id);

            // If no inspection is found by ID, return an error response
            if (!$existingWorkreport) {
                $response = [
                    'isSuccess' => false,
                    'message' => "No actual work report found.",
                ];
                $this->logAPICalls('updateWorkreport', $id, [], $response);
                return response()->json($response, 500);
            }

            // Prepare the data for updating the inspection report
            $actualworkData = [
                'recommended_action' => $request->input('recommended_action') ? $request->input('recommended_action') : $existingWorkreport->recommended_action,
                'remarks' => $request->input('remarks') ? $request->input('remarks') : $existingWorkreport->remarks,
            ];

            // Update the existing inspection report with the new data
            $existingWorkreport->update($actualworkData);

            $full_name = "{$user->first_name} {$user->middle_initial} {$user->last_name}";

            // Response for successful update in the Inspection_report table
            $response = [
                'isSuccess' => true,
                'message' => 'Actual work report updated successfully.',
                'actualwork' => [
                    'id' => $existingWorkreport->id,
                    'recommended_action' => $existingWorkreport->recommended_action,
                    'remarks' => $existingWorkreport->remarks,
                    'user_id' => $existingWorkreport->user_id,
                    'full_name' => $full_name,
                    'updated_at' => $existingWorkreport->updated_at,
                ]
            ];
            $this->logAPICalls('updateWorkreport', $existingWorkreport->id, [], $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Response for failed operation
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to update the actual work report.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('updateWorkreport', $id, [], $response);
            return response()->json($response, 500);
        }
    }

    //GET WORK REPORT
    public function getWorkreports(Request $request, $requestId)
    {
        // Generate a unique identifier for logging
        $logRequestId = (string) Str::uuid();

        try {
            // Check if the `request_id` exists in the Requests table
            if (!Requests::where('id', $requestId)->exists()) {
                $response = [
                    'isSuccess' => false,
                    'message' => "No request found for this ID: {$requestId}.",
                    'actualwork' => [],  // Empty actual work array for consistency
                ];
                $this->logAPICalls('getWorkreports', $logRequestId, [], $response);

                return response()->json($response, 500);
            }

            // Fetch Actual work reports related to the `request_id`
                $actualWorkReports = Actual_work::where('is_archived', '1')
                ->where('request_id', $requestId)
                ->get(['request_id', 'control_no', 'id', 'recommended_action', 'remarks'])
                ->map(function ($actualWork) {
                    return [
                        'id' => $actualWork->id,
                        'request_id' => $actualWork->request_id,
                        'control_no' => $actualWork->control_no,
                        'recommended_action' => $actualWork->recommended_action,
                        'remarks' => $actualWork->remarks,
                    ];
                })->all(); // Convert to array for consistent output

            // Prepare the response
            $response = [
                'isSuccess' => true,
                'message' => 'Actual work report retrieved successfully.',
                'actualwork' => $actualWorkReports,
            ];

            // Log API call
            $this->logAPICalls('getWorkreports', $logRequestId, [], $response);

            return response()->json($response, 200);

        } catch (Throwable $e) {
            // Error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve actual work report.',
                'error' => $e->getMessage(),
                'actualwork' => [],  // Empty actual work array for consistency
            ];

            // Log the error
            $this->logAPICalls('getWorkreports', $logRequestId, [], $response);

            return response()->json($response, 500);
        }
    }

    //ADD MANPOWER DEPLOYMENT
    public function addManpowerDeploy(Request $request)
    {
        // Validate input
        $request->validate([
            'manpower_id' => 'required|exists:manpowers,id', // Assuming you have a manpower ID
            'rating' => 'required|numeric|between:0,100',
        ]);

        try {
            // Fetch the manpower record using the provided ID
            $manpower = Manpower::findOrFail($request->input('manpower_id'));

            // Clean the rating input (removing the '%' sign)
            $ratingInput = $request->input('rating');
            $numericRating = str_replace('%', '', $ratingInput);

            // Prepare the rating for storage
            $ratingToStore = $numericRating . '%';

            // Create a new manpower deployment record
            $newManpowerDeploy = ManpowerDeployment::create([
                'first_name' => $manpower->first_name,
                'last_name' => $manpower->last_name,
                'rating' => $ratingToStore,
                'manpower_id' => $manpower->id
            ]);

            // Prepare the response
            $response = [
                'isSuccess' => true,
                'message' => 'Manpower successfully added.',
                'manpowerdeployment' => $newManpowerDeploy,
            ];

            // Log the API call
            $this->logAPICalls('addManpowerDeploy', $newManpowerDeploy->id, [], $response);

            return response()->json($response, 200);

        } catch (Throwable $e) {
            // Handle any exceptions that may occur
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to create the Actual work report.',
                'error' => $e->getMessage(),
            ];

            // Log the API call
            $this->logAPICalls('addManpowerDeploy', '', [], $response);

            return response()->json($response, 500);
        }
    }

    //GET MANPOWER DEPLOYMENT
    public function getManpowerDeploy(Request $request)
    {
        try {
            // Fetch all manpower deployment records
            $manpowerDeployments = ManpowerDeployment::select('id', 'first_name', 'last_name', 'rating')
                ->where('is_archived', '1')
                ->get();


            // Prepare the response
            $response = [
                'isSuccess' => true,
                'message' => 'Manpower deployments retrieved successfully.',
                'manpowerdeployment' => $manpowerDeployments,
            ];

            // Log the API call
            $this->logAPICalls('getManpowerDeploy', '', [], $response);

            return response()->json($response, 200);

        } catch (Throwable $e) {

            // Prepare the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve manpower deployments.',
                'error' => $e->getMessage(),
            ];

            // Log the API call
            $this->logAPICalls('getManpowerDeploy', '', [], $response);

            return response()->json($response, 500);
        }
    }

    //DELETE MANPOWER DEPLOYMENT
    public function deletemanpowerdeployment(Request $request)
    {
        try {

            $manpowerdeployment = ManpowerDeployment::findOrFail($request->id);
            $manpowerdeployment->update(['is_archived' => "1"]);
            $response = [
                'isSuccess' => true,
                'message' => "ManpowerDeployment successfully deleted."
            ];

            // Log the API call (assuming this method works properly)
            $this->logAPICalls('deletemanpowerdeployment', $manpowerdeployment->id, [], [$response]);
            return response()->json($response, 200);

        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to delete the ManpowerDeployment.",
                'error' => $e->getMessage()
            ];

            // Log the API call with failure response
            $this->logAPICalls('deletemanpowerdeployment', "", [], [$response]);

            return response()->json($response, 500);
        }
    }

    //
    public function submitWorkreport(Request $request)
    {
        try {
            // Retrieve the currently logged-in user
            $user = auth()->user();

            // Retrieve the record based on the provided request ID
            $requests = Requests::where('id', $request->id)->firstOrFail();

            // Update the status to "Completed"
            $requests->update(['status' => 'Completed']);

            // Prepare the full name of the currently logged-in user
            $fullName = "{$user->first_name} {$user->middle_initial} {$user->last_name}";

            // Prepare the response
            $response = [
                'isSuccess' => true,
                'messsage' => 'Actual Work report successfully submitted.',
                'request_id' => $requests->id,
                'status' => $requests->status,
                'user_id' => $user->id,
                'user' => $fullName,
            ];

            // Log the API call
            $this->logAPICalls('submitWorkreport', $requests->id, [], $response);

            return response()->json($response, 200);

        } catch (Throwable $e) {
            // Prepare the error response
            $response = [
                'isSuccess' => false,
                'message' => "Failed to update the work status.",
                'error' => $e->getMessage(),
            ];

            // Log the API call with failure response
            $this->logAPICalls('submitWorkreport', $request->id ?? '', [], $response);

            return response()->json($response, 500);
        }
    }
    public function getDropdownOptionsManpower(Request $request)
    {
        try {


            $Manpowerdeploy = ManpowerDeployment::select('id', 'first_name', 'last_name')
                ->where('is_archived', '0')
                ->get();

            // Build the response
            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown data retrieved successfully.',
                'div_name' => $Manpowerdeploy,
            ];

            // Log the API call
            $this->logAPICalls('getDropdownOptionsActualwork', "", [], $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown data.',
                'error' => $e->getMessage()
            ];

            // Log the error
            $this->logAPICalls('getDropdownOptionsActualwork', "", [], $response);

            return response()->json($response, 500);
        }
    }

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