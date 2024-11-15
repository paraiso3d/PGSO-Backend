<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\Control_Request;
use App\Models\Requests;
use App\Models\Actual_work;
use App\Models\Accomplishment_report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;
use Log;

class AccomplishmentReportController extends Controller
{
    // Create a new accomplishment report    
    public function saveAccomplishmentReport(Request $request, $id = null)
    {
        // Ensure the user is authenticated
        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'User is not authenticated.',
            ], 401); // Unauthorized
        }
    
        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'remarks' => 'string',
            'date_completed' => 'required|date|after_or_equal:today',
            'status' => 'required|string'
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422); // Unprocessable Entity
        }
    
        try {
            // Fetch the existing request using the provided ID
            $existingRequest = Requests::find($id);
    
            // Check if the request exists
            if (!$existingRequest) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => "No request found with ID {$id}.",
                ], 404); // Not Found
            }
    
            $status = $request->status;
            $dateStarted = $existingRequest->created_at;
            $dateCompleted = $request->input('date_completed');
    
            $accomplishmentData = [
                'id' => $existingRequest->id,
                'description' => $existingRequest->description,
                'date_started' => $dateStarted,
                'date_completed' => $dateCompleted,
                'status' => $status,
                'remarks' => $request->input('remarks'),
                'user_id' => $user->id
            ];
    
            // Check for existing Accomplishment Report for the given request
            $accomplishmentReport = Accomplishment_Report::where('request_id', $existingRequest->id)->first();
    
            if ($accomplishmentReport) {
                // Update the existing Accomplishment Report record
                $accomplishmentReport->update($accomplishmentData);
            } else {
                // Create a new entry in the Accomplishment_Report table
                $accomplishmentReport = Accomplishment_Report::create($accomplishmentData);
            }
    
            // Update the status of all related Accomplishment Reports
            Accomplishment_Report::where('request_id', $existingRequest->id)
                ->update(['status' => $status]);
    
            // Update the status of the related Control Request
            $existingRequest->update(['status' => $status]);
    
            // Prepare response with authenticated user full name and updated_at timestamp
            $response = [
                'isSuccess' => true,
                'message' => $accomplishmentReport->wasRecentlyCreated ? 'Accomplishment Report created successfully.' : 'Accomplishment Report updated successfully.',
                'accomplishment' => [
                    'id' => $accomplishmentReport->id,
                    'description' => $accomplishmentReport->description,
                    'date_started' => $accomplishmentReport->date_started,
                    'date_completed' => $accomplishmentReport->date_completed,
                    'status' => $accomplishmentReport->status,
                    'remarks' => $accomplishmentReport->remarks,
                    'user_id'=> $user->id,
                    'full_name' => "{$user->first_name} {$user->middle_initial} {$user->last_name}",
                    'updated_at' => $accomplishmentReport->updated_at,
                ]
            ];
    
            $this->logAPICalls('saveAccomplishmentReport', $existingRequest->id, [], $response);
    
            return response()->json($response, 200);
    
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => "No control_request found with ID {$id}.",
            ], 404); // Not Found
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to save the accomplishment report.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('saveAccomplishmentReport', $id ?? '', [], $response);
            
            return response()->json($response, 500); // Internal Server Error
        }
    }

    public function submitFeedback(Request $request)
    {
        try {
            // Retrieve the currently logged-in user
            $user = auth()->user();

            // Retrieve the record based on the provided request ID
            $work = Requests::where('id', $request->id)->firstOrFail();

            // Update the status to "For Feedback"
            $work->update(['status' => 'For Feedback']);

            // Prepare the full name of the currently logged-in user
            $fullName = "{$user->first_name} {$user->middle_initial} {$user->last_name}";

            // Prepare the response
            $response = [
                'isSuccess' => true,
                'messsage' => 'Feedback successfully submitted.',
                'request_id' => $work->id,
                'status' => $work->status,
                'user_id' => $user->id,
                'user' => $fullName,
            ];

            // Log the API call
            $this->logAPICalls('submitFeedback', $work->id, [], $response);

            return response()->json($response, 200);

        } catch (Throwable $e) {
            // Prepare the error response
            $response = [
                'isSuccess' => false,
                'message' => "Failed to update the work status.",
                'error' => $e->getMessage(),
            ];

            // Log the API call with failure response
            $this->logAPICalls('submitFeedback', $request->id ?? '', [], $response);

            return response()->json($response, 500);
        }
    }
    
    
    
    public function logAPICalls(string $methodName, string $userId, array $param, array $resp)
    {
        try {
            // Log to the database using ApiLog model
            \App\Models\ApiLog::create([
                'method_name' => $methodName,
                'user_id' => $userId,
                'api_request' => json_encode($param),
                'api_response' => json_encode($resp),
            ]);
        } catch (Throwable $e) {
            return false;  
        }

        return true; 
    }

}
