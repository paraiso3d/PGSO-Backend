<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Division;
use Illuminate\Support\Facades\Auth;
use App\Models\Inspection_report;
use Illuminate\Http\Request;
use Throwable;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Session;

class InspectionController extends Controller
{

    public function __construct(Request $request)
    {
        // Retrieve the authenticated user
        $user = $request->user();

        // Apply middleware based on the user type
        if ($user && $user->user_type === 'Administrator') {
            $this->middleware('UserTypeAuth:Administrator')->only(['updateReview', 'getReviews']);
        }

        if ($user && $user->user_type === 'Supervisor') {
            $this->middleware('UserTypeAuth:Supervisor')->only(['updateReview', 'getReviews']);
        }

        if ($user && $user->user_type === 'TeamLeader') {
            $this->middleware('UserTypeAuth:TeamLeader')->only(['updateReview', 'getReviews']);
        }

        if ($user && $user->user_type === 'Controller') {
            $this->middleware('UserTypeAuth:Controller')->only(['updateReview', 'getReviews']);
        }

        if ($user && $user->user_type === 'DeanHead') {
            $this->middleware('UserTypeAuth:DeanHead')->only(['getReviews']);
        }
    }

    public function createInspection(Request $request)
    {
        // Validate the incoming request data using the built-in validation method
        $request->validate([
            'description' => 'required|string|max:255',
            'recommendation' => 'required|string|max:255',
        ]);
    
        // Store the validated request data
        try {
            // Create a new Inspection record using the validated data
            $newInspection = Inspection_report::create([
                'description' => $request->input('description'),
                'recommendation' => $request->input('recommendation'),
            ]);
    
            $response = [
                'isSuccess' => true,
                'message' => 'Inspection report successfully created.',
                'data' => $newInspection,
            ];
    
            // Log the API call (assuming `logAPICalls` is a defined method in your class)
            $this->logAPICalls('createInspection', $newInspection->id, $request->all(), $response);
    
            // Return a 201 Created response
            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle any exceptions that may occur
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to create the inspection report.',
                'error' => $e->getMessage(),
            ];
    
            // Log the API call (assuming `logAPICalls` is a defined method in your class)
            $this->logAPICalls('createInspection', '', $request->all(), $response);
    
            // Return a 500 Internal Server Error response
            return response()->json($response, 500);
        }
    }
    

    
    public function getInspections(Request $request)
    {
        try {
            // Validation for filters (optional)
            $validated = $request->validate([
                'per_page' => 'nullable|integer',
                'description' => 'nullable|string', // If you want to filter by description
                'recommendation' => 'nullable|string', // If you want to filter by recommendation
            ]);
    
            // Initialize query
            $query = Inspection_report::query();
    
            // Apply filters dynamically if present
            if (!empty($validated['description'])) {
                $query->where('description', 'like', '%' . $validated['description'] . '%');
            }
    
            if (!empty($validated['recommendation'])) {
                $query->where('recommendation', 'like', '%' . $validated['recommendation'] . '%');
            }
    
            // Pagination (default to 10 if not provided)
            $perPage = $validated['per_page'] ?? 10;
    
            // Sort by 'description' 
            $inspections = $query->orderBy('description', 'asc')->paginate($perPage);
    
            // Response
            $response = [
                'isSuccess' => true,
                'message' => 'Inspections retrieved successfully.',
                'data' => $inspections,
            ];
    
            // Log API calls if necessary
            $this->logAPICalls('getInspections', '', $request->all(), $response);
    
            return response()->json($response, 200);
    
        } catch (Throwable $e) {
            // Catch any exceptions and send error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve the inspection reports.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('getInspections', '', $request->all(), $response);
    
            return response()->json($response, 500);
        }
    }
    







    // Method to update an existing request
    public function updateInspection(Request $request, $id)
    {
        // Validate the incoming request data using Laravel's built-in validation method
        $request->validate([
            'description' => 'required|string|max:255',
            'recommendation' => 'required|string|max:255',
        ]);
    
        try {
            // Find the existing inspection report by ID or throw a 404 error
            $existingRequest = Inspection_report::findOrFail($id);
    
            // Update the request data
            $existingRequest->update([
                'description' => $request->input('description'),
                'recommendation' => $request->input('recommendation'),
            ]);
    
            $response = [
                'isSuccess' => true,
                'message' => 'Request updated successfully.',
                'data' => $existingRequest,
            ];
    
            $this->logAPICalls('updateInspection', $id, $request->all(), $response);
            return response()->json($response, 200); // Return a 200 OK response
        } catch (Throwable $e) {
            // Handle any errors during update
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to update the request.',
                'error' => $e->getMessage(),
            ];
    
            $this->logAPICalls('updateInspection', $id, $request->all(), $response);
            return response()->json($response, 500); // Return a 500 Internal Server Error response
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