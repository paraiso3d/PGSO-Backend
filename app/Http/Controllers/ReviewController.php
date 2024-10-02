<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Division;
use Illuminate\Support\Facades\Auth;
use App\Models\Requests;
use Illuminate\Http\Request;
use Throwable;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Session;


class ReviewController extends Controller
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
    // Method to retrieve all requests
    public function getReviews(Request $request)
    {
        try {
            // Validation for filters (optional)
            $validated = $request->validate([
                'per_page' => 'nullable|integer',
                'status' => 'nullable|string',
                'file_name' => 'nullable|string',
                'location_name' => 'nullable|string',
                'category_name' => 'nullable|string',
                'fiscal_year' => 'nullable|string',
                'division' => 'nullable|string',
                'search' => 'nullable|string',
            ]);

            // Initialize query
            $query = Requests::query();

            // Select specific fields from both tables
            $query->select('requests.id', 'requests.control_no', 'requests.description', 'requests.officename', 'requests.location_name', 'requests.overtime', 'requests.file_name', 'requests.area', 'requests.category_name', 'requests.fiscal_year', 'requests.status', 'categories.division')
                ->join('categories', 'requests.category_name', '=', 'categories.category_name');

        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve the requests.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('getReviews', '', $request->all(), $response);

            return response()->json($response, 500);
        }
    }







    // Method to update an existing request
    public function updateReview(Request $request, $id)
    {
        // Validate the incoming request data
        $validator = Requests::validateRequest($request->all());



        if ($validator->fails()) {
            $response = [
                'isSuccess' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ];
            $this->logAPICalls('updateReview', $id, $request->all(), $response);
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
            $this->logAPICalls('updateReview', $id, $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to update the request.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('updateReview', $id, $request->all(), $response);
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
