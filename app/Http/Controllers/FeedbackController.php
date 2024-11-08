<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
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


class FeedbackController extends Controller
{
    public function saveFeedback(Request $request, $id = null)
    {
        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'remarks' => 'string',
            'rating' => 'string|nullable', // Allow rating to be optional
            'feedback' => 'required|string'
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422); // Unprocessable Entity
        }
    
        try {
            // Fetch the existing request and accomplishment report by request ID
            $existingRequest = Requests::findOrFail($id);
            $existingAccomplishment = Accomplishment_report::where('request_id', $id)->firstOrFail();
    
            // Calculate the date threshold for automatic rating
            $dateCompleted = $existingAccomplishment->date_completed;
            $dateThreshold = Carbon::parse($dateCompleted)->addDays(3); // 3 days after the completed date
    
            // Determine the rating
            $rating = $request->rating; // Get the rating from the request
            if (empty($rating) && Carbon::now()->greaterThan($dateThreshold)) {
                $rating = 'Outstanding'; // Assign default rating if not provided and 3 days have passed
            }
    
            // Set up the feedback data
            $feedbackData = [
                'accomplishment_id' => $existingAccomplishment->id,
                'request_id' => $existingRequest->id,
                'rating' => $rating,
                'final_remarks' => $existingAccomplishment->remarks,
                'feedback' => $request->feedback,
                'date_started' => $existingAccomplishment->date_started,
                'date_completed' => $existingAccomplishment->date_completed
            ];
    
            // Check for existing feedback for the given accomplishment and request
            $feedbackReport = Feedback::where('accomplishment_id', $existingAccomplishment->id)
                ->where('request_id', $existingRequest->id)
                ->first();
    
            if ($feedbackReport) {
                // Update the existing feedback record
                $feedbackReport->update($feedbackData);
                $message = 'Feedback updated successfully.';
            } else {
                // Create a new feedback record
                $feedbackReport = Feedback::create($feedbackData);
                $message = 'Feedback created successfully.';
            }
    
            // Prepare the response including date_started and date_completed
            $response = [
                'isSuccess' => true,
                'message' => $message,
                'feedback' => $feedbackReport,
            ];
    
            $this->logAPICalls('saveFeedback', $existingAccomplishment->id, $request->all(), $response);
    
            return response()->json($response, 200);
    
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => "No accomplishment report found for request ID {$id}.",
            ], 404);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to save the feedback.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('saveFeedback', $id ?? '', $request->all(), $response);
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
            return false;  // Return false to indicate logging failure
        }
        return true;  // Return true to indicate successful logging
    }

}
