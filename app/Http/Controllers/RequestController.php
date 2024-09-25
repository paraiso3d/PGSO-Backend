<?php

namespace App\Http\Controllers;

use App\Models\Requests;
use Illuminate\Http\Request;
use Throwable;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Session;


class RequestController extends Controller
{
    // Method to handle storing a new request
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
        if ($request->hasFile('file_name')) {
            // Store the file and get the path
            $filePath = $request->file('file_name')->store('public/uploads'); // Store in public directory
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
                'file_name' => $filePath, // Save the path to the database
                'status' => $status,
            ]);

            $response = [
                'isSuccess' => true,
                'message' => 'Request successfully created.',
                'data' => $newRequest,
            ];
            $this->logAPICalls('createRequest', $newRequest->id, $request->all(), $response);

            return response()->json($response, 201);
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
            $perPage = $request->input('per_page', 10);
            $requests = Requests::paginate($perPage);
            $requests = Requests::select('location_name');
            $requests = Requests::select('status');
            $response = [
                'isSuccess' => true,
                'message' => 'Requests retrieved successfully.',
                'data' => $requests,
            ];
            $this->logAPICalls('getRequests', '', [], $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve the requests.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('getRequests', '', [], $response);
            return response()->json($response, 500);
        }
    }

    // Method to retrieve a specific request by ID
    public function getRequestById($id)
    {
        try {
            $requestRecord = Requests::findOrFail($id);

            $response = [
                'isSuccess' => true,
                'message' => 'Request retrieved successfully.',
                'data' => $requestRecord,
            ];
            $this->logAPICalls('getRequestById', $id, [], $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve the request.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('getRequestById', $id, [], $response);
            return response()->json($response, 404);
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
                'data' => $existingRequest,
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
            $requestRecord->update(['isarchive' => 'I']);

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
