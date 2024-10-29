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
        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'remarks' => 'string',
            'date_completed' => 'required|date|after_or_equal:today'
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
            $existingRequest = Requests::findOrFail($id);
            $status = $request->input('status', 'Completed'); // Set status to Completed or the input value
            $dateStarted = $existingRequest->created_at;
            $dateCompleted = $request->input('date_completed');

            $accomplishmentData = [
                'id' => $existingRequest->id,
                'description' => $existingRequest->description,
                'date_started' => $dateStarted,
                'date_completed' => $dateCompleted,
                'status' => $status,  // Add the status here
                'remarks' => $request->input('remarks'),
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

            // Assuming `Requests` is related to `Control_Request` by ID (or another common field).
            Requests::where('id', $existingRequest->id)->update(['status' => $status]);


            $response = [
                'isSuccess' => true,
                'message' => $accomplishmentReport->wasRecentlyCreated ? 'Accomplishment Report created successfully.' : 'Accomplishment Report updated successfully.',
                'accomplishment' => $accomplishmentReport,
            ];

            $this->logAPICalls('saveAccomplishmentReport', $existingRequest->id, $request->all(), $response);

            return response()->json($response, 200);

        } catch (ModelNotFoundException $e) {
            // Handling if Control_Request with the given ID is not found
            return response()->json([
                'isSuccess' => false,
                'message' => "No control_request found with ID {$id}.",
            ], 404);
        } catch (Throwable $e) {
            // Response for failed operation
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to save the accomplishment report.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('saveAccomplishmentReport', $id ?? '', $request->all(), $response);
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
            // Log any exceptions that occur during database logging
            Log::error("Failed to log API call to database: {$e->getMessage()}");
            return false;  // Return false to indicate logging failure
        }

        // Also log to the Laravel log file
        Log::info("API Call: {$methodName}", [
            'user_id' => $userId,
            'request_data' => $param,
            'response' => $resp,
        ]);

        return true;  // Return true to indicate successful logging
    }

}
