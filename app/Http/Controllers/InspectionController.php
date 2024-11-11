<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Division;
use App\Models\Requests;
use App\Models\User;
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
    // Method to create new Inspection report.
    public function createInspection(Request $request, $id = null)
    {
        // Validate the incoming request data
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
            $this->logAPICalls('createInspection', $id, [], $response);
            return response()->json($response, 500);
        }

        try {

            $user = auth()->user();

            // Fetch the existing request using the provided ID or some identifier
            $existingRequest = Requests::find($id);

            // If no request is found by ID, return an error response
            if (!$existingRequest) {
                $response = [
                    'isSuccess' => false,
                    'message' => "No request found.",
                ];
                $this->logAPICalls('createInspection', $id, [], $response);
                return response()->json($response, 404);
            }

            // Prepare the data for creating a new Inspection report
            $inspectionData = [
                'description' => $request->input('description'),
                'recommendation' => $request->input('recommendation'),
                'control_no' => $existingRequest->control_no, // Link to the existing control_no from Requests table
                'request_id' => $existingRequest->id,
                'user_id' => $user->id, // Only the ID is needed here
            ];

            // Create a new entry in the Inspection_report table
            $newInspection = Inspection_report::create($inspectionData);

            // Add full name of User to the inspection response
            $newInspection->full_name = "{$user->first_name} {$user->middle_initial} {$user->last_name}";

            // Response for successful creation in the Inspection_report table
            $response = [
                'isSuccess' => true,
                'message' => 'Inspection report created successfully.',
                'inspection' => [
                    'id' => $newInspection->id,
                    'description' => $newInspection->description,
                    'recommendation' => $newInspection->recommendation,
                    'control_no' => $newInspection->control_no,
                    'request_id' => $newInspection->request_id,
                    'user_id' => $newInspection->user_id,
                    'full_name' => $newInspection->full_name,
                    'created_at' => $newInspection->created_at,
                    'updated_at' => $newInspection->updated_at,
                ],
            ];
            $this->logAPICalls('createInspection', $existingRequest->id, [], $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Response for failed operation
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to create the inspection report.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('createInspection', $id ?? '', [], $response);
            return response()->json($response, 500);
        }
    }

    // Method for getting Inspection report list.
    public function getInspections(Request $request, $requestId)
    {
        // Generate a unique identifier for logging
        $logRequestId = (string) Str::uuid();

        try {
            // Check if the `request_id` exists in the Requests table
            if (!Requests::where('id', $requestId)->exists()) {
                $response = [
                    'isSuccess' => false,
                    'message' => "No request found for this ID: {$requestId}.",
                    'inspections' => [],  // Empty inspections array for consistency
                ];
                $this->logAPICalls('getInspections', $logRequestId, [], $response);

                return response()->json($response, 500);
            }

            // Fetch inspection reports related to the `request_id`
            $inspections = Inspection_report::where('is_archived', 'A')
                ->where('request_id', $requestId)
                ->get(['request_id', 'control_no', 'id', 'description', 'recommendation'])
                ->map(function ($inspection) {
                    return [
                        'request_id' => $inspection->request_id,
                        'id' => $inspection->id,
                        'control_no' => $inspection->control_no,
                        'description' => $inspection->description,
                        'recommendation' => $inspection->recommendation,
                    ];
                })->all(); // Convert to array for consistent output

            // Prepare the response
            $response = [
                'isSuccess' => true,
                'message' => 'Inspections retrieved successfully.',
                'inspections' => $inspections,
            ];

            // Log API call
            $this->logAPICalls('getInspections', $logRequestId, [], $response);

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
            $this->logAPICalls('getInspections', $logRequestId, [], $response);

            return response()->json($response, 500);
        }
    }

    // Method for updating the Inspection report.
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
            $this->logAPICalls('updateInspection', $id, [], $response);

            return response()->json($response, 500);
        }

        try {

            $user = auth()->user();

            // Fetch the existing inspection report using the provided ID
            $existingInspection = Inspection_report::find($id);

            // If no inspection is found by ID, return an error response
            if (!$existingInspection) {
                $response = [
                    'isSuccess' => false,
                    'message' => "No inspection report found.",
                ];
                $this->logAPICalls('updateInspection', $id, [], $response);

                return response()->json($response, 404);
            }

            // Prepare the data for updating the inspection report
            $inspectionData = [
                'description' => $request->filled('description') ? $request->input('description') : $existingInspection->description,
                'recommendation' => $request->input('recommendation') ? $request->input('recommendation') : $existingInspection->recommendation,
                'user_id' => $user->id,
            ];

            // Update the existing inspection report with the new data
            $existingInspection->update($inspectionData);

            $full_name = "{$user->first_name} {$user->middle_initial} {$user->last_name}";

            // Response for successful update in the Inspection_report table
            $response = [
                'isSuccess' => true,
                'message' => 'Inspection report updated successfully.',
                'inspection' => [
                    'id' => $existingInspection->id,
                    'description' => $existingInspection->description,
                    'recommendation' => $existingInspection->recommendation,
                    'user_id' => $existingInspection->user_id,
                    'full_name' => $full_name,
                    'updated_at' => $existingInspection->updated_at,

                ],
            ];
            $this->logAPICalls('updateInspection', $existingInspection->id, [], $response);

            return response()->json($response, 200);

        } catch (Throwable $e) {
            // Response for failed operation
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to update the inspection report.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('updateInspection', $id, [], $response);

            return response()->json($response, 500);
        }
    }
    
    //Method for deleting the Inspection report.
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
            $this->logAPICalls('deleteInspection', $inspection->request_id, [], $response);

            return response()->json($response, 200);

        } catch (Throwable $e) {
            // Prepare the error response
            $response = [
                'isSuccess' => false,
                'message' => "Failed to archive the inspection report.",
                'error' => $e->getMessage()
            ];

            // Log the API call with failure response
            $this->logAPICalls('deleteInspection', $request->request_id ?? '', [], $response);

            return response()->json($response, 500);
        }
    }

    //Method for submitting the Inspection report.
    public function submitInspection(Request $request)
    {
        try {
            // Retrieve the currently logged-in user
            $user = auth()->user();

            // Retrieve the record based on the provided request ID
            $work = Requests::where('id', $request->id)->firstOrFail();

            // Update the status to "On-going"
            $work->update(['status' => 'On-going']);

            // Prepare the full name of the currently logged-in user
            $fullName = "{$user->first_name} {$user->middle_initial} {$user->last_name}";

            // Prepare the response
            $response = [
                'isSuccess' => true,
                'messsage' => 'Inspection report successfully submitted.',
                'request_id' => $work->id,
                'status' => $work->status,
                'user_id' => $user->id,
                'user' => $fullName,
            ];

            // Log the API call
            $this->logAPICalls('submitInspection', $work->id, [], $response);

            return response()->json($response, 200);

        } catch (Throwable $e) {
            // Prepare the error response
            $response = [
                'isSuccess' => false,
                'message' => "Failed to update the work status.",
                'error' => $e->getMessage(),
            ];

            // Log the API call with failure response
            $this->logAPICalls('submitInspection', $request->id ?? '', [], $response);

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