<?php

namespace App\Http\Controllers;

use App\Models\Actual_work;
use App\Models\ManpowerDeployment;
use App\Models\Manpower;
use Illuminate\Http\Request;
use Throwable;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Session;
use Illuminate\Support\Facades\Auth;



class ActualWorkController extends Controller
{
    public function createWorkreport(Request $request)
    {
        // Validate the incoming request data using the built-in validation method
        $request->validate([
            'recommended_action' => 'required|string|max:255',
            'remarks' => 'required|string|max:255',
        ]);
    
        // Store the validated request data
        try {
            // Create a new Actual work report record using the validated data
            $newWorkreport = Actual_work::create([
                'recommended_action' => $request->input('recommended_action'),
                'remarks' => $request->input('remarks'),
            ]);
    
            $response = [
                'isSuccess' => true,
                'message' => 'Actual work report successfully created.',
                'data' => $newWorkreport,
            ];
    
            // Log the API call (assuming `logAPICalls` is a defined method in your class)
            $this->logAPICalls('createWorkreport', $newWorkreport->id, $request->all(), $response);
    
            // Return a 201 Created response
            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle any exceptions that may occur
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to create the Actual work report.',
                'error' => $e->getMessage(),
            ];
    
            // Log the API call (assuming `logAPICalls` is a defined method in your class)
            $this->logAPICalls('createWorkreport', '', $request->all(), $response);
    
            // Return a 500 Internal Server Error response
            return response()->json($response, 500);
        }
    }

    public function updateWorkreport(Request $request, $id)
    {
        // Validate the incoming request data using Laravel's built-in validation method
        $request->validate([
            'recommended_action' => 'required|string|max:255',
            'remarks' => 'required|string|max:255',
        ]);
    
        try {
            // Find the existing inspection report by ID or throw a 404 error
            $existingRequest = Actual_work::findOrFail($id);
    
            // Update the request data
            $existingRequest->update([
                'recommended_action' => $request->input('recommended_action'),
                'remarks' => $request->input('remarks'),
            ]);
    
            $response = [
                'isSuccess' => true,
                'message' => 'Actual work report updated successfully.',
                'data' => $existingRequest,
            ];
    
            $this->logAPICalls('updateWorkreport', $id, $request->all(), $response);
            return response()->json($response, 200); // Return a 200 OK response
        } catch (Throwable $e) {
            // Handle any errors during update
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to update the actual work report.',
                'error' => $e->getMessage(),
            ];
    
            $this->logAPICalls('updateWorkreport', $id, $request->all(), $response);
            return response()->json($response, 500); // Return a 500 Internal Server Error response
        }
    }

    public function getWorkreport(Request $request)
    {
        try {
            // Validation for filters (optional)
            $validated = $request->validate([
                'per_page' => 'nullable|integer',
                'recommended_action' => 'nullable|string', // If you want to filter by description
                'remarks' => 'nullable|string', // If you want to filter by recommendation
            ]);
    
            // Initialize query
            $query = Actual_work::query();
    
            // Apply filters dynamically if present
            if (!empty($validated['recommended_action'])) {
                $query->where('recommended_action', 'like', '%' . $validated['recommended_action'] . '%');
            }
    
            if (!empty($validated['remarks'])) {
                $query->where('remarks', 'like', '%' . $validated['remarks'] . '%');
            }
    
            // Pagination (default to 10 if not provided)
            $perPage = $validated['per_page'] ?? 10;
    
            // Sort by 'description' 
            $Workreport = $query->orderBy('recommended_action', 'asc')->paginate($perPage);
    
            // Response
            $response = [
                'isSuccess' => true,
                'message' => 'Actual work report retrieved successfully.',
                'data' => $Workreport,
            ];
    
            // Log API calls if necessary
            $this->logAPICalls('getWorkreport', '', $request->all(), $response);
    
            return response()->json($response, 200);
    
        } catch (Throwable $e) {
            // Catch any exceptions and send error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve the actual work reports.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('getWorkreport', '', $request->all(), $response);
    
            return response()->json($response, 500);
        }
    }

    public function addManpowerDeploy (Request $request)
    {
        $ManpowerDeploy = Manpower::pluck('first_name')->toArray();
        $Manpowerlastname = Manpower::pluck('last_name')->toArray();

        $ratingInput = $request->input('rating');
        $numericRating = str_replace('%', '', $ratingInput);
        // Validate the incoming request data using the built-in validation method
        $request->validate([
            'first_name' => ['required','alpha_spaces' ,'in:' . implode(',', $ManpowerDeploy)],
            'last_name' => ['required', 'alpha_spaces' , 'in:' . implode(',', $Manpowerlastname)],
            'rating' => 'required|numeric|between:0,100',
        ]);
    
        try {
            // Step 1: Pre-process the input to remove the '%' symbol if present
            $ratingInput = $request->input('rating');
            $numericRating = str_replace('%', '', $ratingInput); // Strip out '%' symbol
        
            // Step 2: Validate the input after removing the '%' symbol
            $validatedData = $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'rating' => 'required|numeric|between:0,100', // Ensure the rating is numeric between 0 and 100
            ]);
        
            // Step 3: Append '%' symbol back to the rating before storing in the database
            $ratingToStore = $numericRating . '%';
        
            // Step 4: Create a new Actual work report record using the validated data
            $newManpowerDeploy = ManpowerDeployment::create([
                'first_name' => $validatedData['first_name'],
                'last_name' => $validatedData['last_name'],
                'rating' => $ratingToStore, // Store the rating with '%' symbol in the database
            ]);
        
            // Prepare the response data
            $response = [
                'isSuccess' => true,
                'message' => 'Manpower successfully added.',
                'data' => $newManpowerDeploy,
            ];
        
            // Log the API call (assuming `logAPICalls` is a defined method in your class)
            $this->logAPICalls('addManpowerDeploy', $newManpowerDeploy->id, $request->all(), $response);
        
            // Return a 201 Created response
            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle any exceptions that may occur
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to create the Actual work report.',
                'error' => $e->getMessage(),
            ];
        
            // Log the API call (assuming `logAPICalls` is a defined method in your class)
            $this->logAPICalls('addManpowerDeploy', '', $request->all(), $response);
        
            // Return a 500 Internal Server Error response
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