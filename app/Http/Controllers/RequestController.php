<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Division;
use App\Models\Requests;
use Illuminate\Http\Request;
use Throwable;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Session;
use Illuminate\Support\Facades\Auth;

class RequestController extends Controller
{
    public function __construct(Request $request)
    {
        // Retrieve the authenticated user
        $user = $request->user();

        // Apply middleware based on the user type
        if ($user && $user->user_type === 'Administrator') {
            $this->middleware('UserTypeAuth:Administrator')->only(['createRequest', 'updateRequest', 'getRequests', 'getRequestById']);
        }

        if ($user && $user->user_type === 'Supervisor') {
            $this->middleware('UserTypeAuth:Supervisor')->only(['getRequests']);
        }

        if ($user && $user->user_type === 'TeamLeader') {
            $this->middleware('UserTypeAuth:TeamLeader')->only(['getRequests']);
        }

        if ($user && $user->user_type === 'Controller') {
            $this->middleware('UserTypeAuth:Controller')->only(['getRequests']);
        }

        if ($user && $user->user_type === 'DeanHead') {
            $this->middleware('UserTypeAuth:DeanHead')->only(['createRequest', 'getRequests']);
        }
    }

    // Method to create a new request.    
    public function createRequest(Request $request)
    {
        // Validate the incoming request data using the model's validateRequest method
        $validator = Requests::validateRequest($request->all());

        if ($validator->fails()) {
            $response = [
                'isSuccess' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ];
            $this->logAPICalls('createRequest', '', $request->all(), $response);
            return response()->json($response, 400);
        }

        // Generate control number
        $controlNo = Requests::generateControlNo();

        // Initialize file path
        $filePath = null;
        if ($request->hasFile('file_path')) {
            // Store the file and get the path
            $filePath = $request->file('file_path')->store('public/uploads'); // Store in public directory
        }

        // Set default status if not provided
        $status = $request->input('status', 'Pending');

        // Store the validated request data
        try {
            $newRequest = Requests::create([
                'control_no' => $controlNo,
                'description' => $request->input('description'),
                'officename' => $request->input('officename'),
                'location_name' => $request->input('location_name'),
                'overtime' => $request->input('overtime'),
                'area' => $request->input('area'),
                'category_name' => $request->input('category_name'),
                'fiscal_year' => $request->input('fiscal_year'),
                'file_path' => $filePath, // Save the path to the database
                'status' => $status,
            ]);

            $response = [
                'isSuccess' => true,
                'message' => 'Request successfully created.',
                'request' => $newRequest,
            ];
            $this->logAPICalls('createRequest', $newRequest->id, $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to create the request.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('createRequest', '', $request->all(), $response);
            return response()->json($response, 500);
        }
    }



    // Method to retrieve all requests

    public function getRequests(Request $request)
    {
        try {
            // Validation for filters (optional)
            $validated = $request->validate([
                'per_page' => 'nullable|integer',
                'status' => 'nullable|string',
                'file_path' => 'nullable|string',
                'location_name' => 'nullable|string',
                'category_name' => 'nullable|string',
                'fiscal_year' => 'nullable|string',
                'division' => 'nullable|string',
                'search' => 'nullable|string',
                'is_archived' => 'nullable|in:A,I', 
            ]);
    
            // Initialize query
            $query = Requests::query();
    
            // Select specific fields from both tables
            $query->select(
                'requests.id',
                'requests.control_no',
                'requests.description',
                'requests.officename',
                'requests.location_name',
                'requests.overtime',
                'requests.file_path',
                'requests.area',
                'requests.category_name',
                'requests.fiscal_year',
                'requests.status',
                'categories.division'
            )
            ->join('categories', 'requests.category_name', '=', 'categories.category_name');
    
            // Apply filters dynamically if present
            if (!empty($validated['status'])) {
                $query->where('requests.status', $validated['status']);
            }
    
            if (!empty($validated['location_name'])) {
                $query->where('requests.location_name', 'like', '%' . $validated['location_name'] . '%');
            }
    
            if (!empty($validated['category_name'])) {
                $query->where('requests.category_name', $validated['category_name']);
            }
    
            if (!empty($validated['fiscal_year'])) {
                $query->where('requests.fiscal_year', $validated['fiscal_year']);
            }
    
            if (!empty($validated['division'])) {
                $query->where('categories.division', 'like', '%' . $validated['division'] . '%');
            }
    
            if (!empty($validated['search'])) {
                $query->where('requests.description', 'like', '%' . $validated['search'] . '%');
            }
    
            // Apply is_archived filter (active = 'A', archived = 'I')
            if (!empty($validated['is_archived'])) {
                $query->where('requests.is_archived', $validated['is_archived']);
            } else {
                // Default behavior: get active requests (is_archived = 'A') if no filter is provided
                $query->where('requests.is_archived', 'A');
            }
    
            // Pagination
            $perPage = $validated['per_page'] ?? 10;
    
            // Sort by division and paginate
            $requests = $query->orderBy('categories.division', 'asc')->paginate($perPage);
    
            // Response
            $response = [
                'isSuccess' => true,
                'message' => 'Requests retrieved successfully.',
                'request' => $requests,
            ];
    
            $this->logAPICalls('getRequests', '', $request->all(), $response);
    
            return response()->json($response, 200);
    
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve the requests.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('getRequests', '', $request->all(), $response);
    
            return response()->json($response, 500);
        }
    }
    

    // Method to update an existing request
    public function updateRequest(Request $request, $id)
    {
        // Validate the incoming request data
        $validator = Requests::validateRequest($request->all());

        if ($validator->fails()) {
            $response = [
                'isSuccess' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ];
            $this->logAPICalls('updateRequest', $id, $request->all(), $response);
            return response()->json($response, 400);
        }

        try {
            $existingRequest = Requests::findOrFail($id);

            // Update the request data
            $existingRequest->update([
                'description' => $request->input('description'),
                'officename' => $request->input('officename'),
                'location_name' => $request->input('location_name'),
                'overtime' => $request->input('overtime'),
                'area' => $request->input('area'),
                'category_name' => $request->input('category_name'),
                'fiscalyear' => $request->input('fiscal_year'),
                'file' => $request->file('file') ? $request->file('file')->store('storage/uploads') : $existingRequest->file,
                'status' => $request->input('status'),
                'user_id' => $request->input('user_id'),

            ]);

            $response = [
                'isSuccess' => true,
                'message' => 'Request updated successfully.',
                'request' => $existingRequest,
            ];
            $this->logAPICalls('updateRequest', $id, $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to update the request.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('updateRequest', $id, $request->all(), $response);
            return response()->json($response, 500);
        }
    }

    // Method to delete (archive) a request
    public function deleteRequest($id)
    {
        try {
            $requestRecord = Requests::findOrFail($id);
            $requestRecord->update(['is_archived' => 'I']);

            $response = [
                'isSuccess' => true,
                'message' => 'Request successfully archived.',
            ];
            $this->logAPICalls('deleteRequest', $id, [], $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to archive the request.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('deleteRequest', $id, [], $response);
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
