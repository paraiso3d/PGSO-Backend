<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Manpower;
use App\Models\ApiLog;
use Throwable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManpowerController extends Controller
{
    /**
     * Create a new user type.
     */
    public function createManpower(Request $request)
    {
        try {
            $request->validate([
                'first_name' => ['required', 'string'],
                'last_name' => ['required', 'string'],
            ]);

            $manpower = Manpower::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,

            ]);

            $manpowerResponse = [
                'id' => $manpower->id,
                'first_name' => $manpower->first_name,
                'last_name' => $manpower->last_name,
                'created_at' => $manpower->created_at,
                'updated_at' => $manpower->updated_at,
            ];

            $response = [
                'isSuccess' => true,
                'message' => "Manpower successfully created.",
                'manpower' => $manpowerResponse
            ];
            $this->logAPICalls('createmanpower', $manpower->id, $request->all(), [$response]);
            return response()->json($response, 200);
        } catch (ValidationException $v) {
            $response = [
                'isSuccess' => false,
                'message' => "Invalid input data.",
                'error' => $v->errors()
            ];
            $this->logAPICalls('createmanpower', "", $request->all(), [$response]);
            return response()->json($response, 500);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to create the manpower.",
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('createmanpower', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
    }

    /**
     * Update an existing Manpower
     */
    public function updatemanpower(Request $request, $id)
    {
        try {
            $manpower = Manpower::findOrFail($id);

            $request->validate([
                'first_name' => ['sometimes','required', 'string'],
                'last_name' => ['sometimes', 'required', 'string'],
            ]);

            $manpower->update([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
            ]);

            $response = [
                'isSuccess' => true,
                'message' => "Manpower successfully updated.",
                'manpower' => $manpower
            ];
            $this->logAPICalls('updatemanpower', $id, $request->all(), [$response]);
            return response()->json($response, 200);
        } catch (ValidationException $v) {
            $response = [
                'isSuccess' => false,
                'message' => "Invalid input data.",
                'error' => $v->errors()
            ];
            $this->logAPICalls('updatemanpower', "", $request->all(), [$response]);
            return response()->json($response, 500);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to update the Manpower.",
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('updatemanpower', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
    }


    /**
     * List manpower
     */
    public function getmanpowers(Request $request)
    {
        try {
            // Set default pagination value or use the one provided in the request
            $perPage = $request->input('per_page', 10);  // Default to 10 items per page

            // Fetch manpowers with pagination
            $manpowers = Manpower::paginate($perPage);

            $response = [
                'isSuccess' => true,
                'message' => "Manpower list retrieved successfully.",
                'manpower' => $manpowers->items(),  // Get the paginated data
                'pagination' => [
                    'total' => $manpowers->total(),
                    'per_page' => $manpowers->perPage(),
                    'current_page' => $manpowers->currentPage(),
                    'last_page' => $manpowers->lastPage(),
                    'next_page_url' => $manpowers->nextPageUrl(),
                    'prev_page_url' => $manpowers->previousPageUrl(),
                ]
            ];

            $this->logAPICalls('getmanpowers', "", [], [$response]);
            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to retrieve Manpowers.",
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('getmanpowers', "", [], [$response]);
            return response()->json($response, 500);
        }
    }

    /**
     * Delete a manpower
     */
    public function deletemanpower($id)
    {
        try {
            $usertype = Manpower::findOrFail($id);

            $usertype->delete();

            $response = [
                'isSuccess' => true,
                'message' => "Manpower successfully deleted."
            ];
            $this->logAPICalls('deletemanpower', $id, [], [$response]);
            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to delete the Manpower.",
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('deletemanpower', "", [], [$response]);
            return response()->json($response, 500);
        }
    }

    /**
     * Log all API calls.
     */
    public function logAPICalls(string $methodName, string $userId, array $param, array $resp)
    {
        try {
            ApiLog::create([
                'method_name' => $methodName,
                'user_id' => $userId,
                'api_request' => json_encode($param),
                'api_response' => json_encode($resp)
            ]);
        } catch (Throwable $e) {
            return false;
        }
        return true;
    }

    /**
     * Test method to verify API functionality.
     */
    public function test()
    {
        return response()->json([
            'isSuccess' => true,
            'message' => 'Test successful'
        ], 200);
    }
}
