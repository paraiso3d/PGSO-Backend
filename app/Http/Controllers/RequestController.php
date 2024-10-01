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
            // Initialize query to get all requests
            $query = Requests::query();
    
            // Select specific fields from both tables
            $query->select(
                'requests.id',
                'requests.control_no',
                'requests.description',
                'requests.officename',
                'requests.location_name',
                'requests.overtime',
                // 'requests.file_path',
                'requests.area',
                // 'requests.category_name',
                // 'requests.fiscal_year',
                'requests.updated_at',
                'requests.status',
                // 'categories.division'
            )
            ->join('categories', 'requests.category_name', '=', 'categories.category_name');
    
            // Basic pagination (default per page = 10)
            $perPage = 10;
            $requests = $query->paginate($perPage);
    
            // Response
            $response = [
                'isSuccess' => true,
                'message' => 'Requests retrieved successfully.',
                'request' => $requests,
            ];
    
            $this->logAPICalls('getRequests', '', [], $response);
    
            return response()->json($response, 200);
    
        } catch (Throwable $e) {
            // Error handling
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve the requests.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('getRequests', '', [], $response);
    
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
