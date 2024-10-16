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
                'first_name' => ['sometimes', 'required', 'string'],
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
            // Validate that 'paginate' is provided in the request
            $validate = $request->validate([
                'paginate' => 'required'
            ]);
    
            // Get search term and pagination settings from the request
            $searchTerm = $request->input('search', null);
            $perPage = $request->input('per_page', 10);
    
            // Check if pagination is enabled
            if ($validate['paginate'] == 0) {
                // Fetch all manpowers without pagination
                $query = Manpower::select('id', 'first_name', 'last_name','is_archived')
                    ->where('is_archived', 'A')
                    ->when($searchTerm, function ($query, $searchTerm) {
                        return $query->where(function ($q) use ($searchTerm) {
                            $q->where('first_name', 'like', '%' . $searchTerm . '%')
                                ->orWhere('last_name', 'like', '%' . $searchTerm . '%');
                        });
                    })
                    ->get();
    
                // Check if query is empty
                if ($query->isEmpty()) {
                    $response = [
                        'isSuccess' => false,
                        'message' => 'No active Manpower found matching the criteria',
                    ];
                    $this->logAPICalls('getmanpowers', "", $request->all(), $response);
                    return response()->json($response, 500);
                }
    
                // Prepare response without pagination
                $response = [
                    'isSuccess' => true,
                    'message' => 'Manpower list retrieved successfully.',
                    'manpower' => $query
                ];
            } else {
                // Fetch paginated manpowers
                $query = Manpower::select('id', 'first_name','last_name','is_archived')
                    ->where('is_archived', 'A')
                    ->when($searchTerm, function ($query, $searchTerm) {
                        return $query->where(function ($q) use ($searchTerm) {
                            $q->where('first_name', 'like', '%' . $searchTerm . '%')
                                ->orWhere('last_name', 'like', '%' . $searchTerm . '%');
                        });
                    })
                    ->paginate($perPage);
    
                // Check if query is empty
                if ($query->isEmpty()) {
                    $response = [
                        'isSuccess' => false,
                        'message' => 'No active Manpower found matching the criteria',
                    ];
                    $this->logAPICalls('getmanpowers', "", $request->all(), $response);
                    return response()->json($response, 500);
                }
    
                // Prepare response with pagination
                $response = [
                    'isSuccess' => true,
                    'message' => 'Manpower list retrieved successfully.',
                    'manpower' => $query,
                    'pagination' => [
                        'total' => $query->total(),
                        'per_page' => $query->perPage(),
                        'current_page' => $query->currentPage(),
                        'last_page' => $query->lastPage(),
                        'url' => url('api/accounts?page=' . $query->currentPage() . '&per_page=' . $query->perPage()),
                    ]
                ];
            }
    
            // Log API call
            $this->logAPICalls('getmanpowers', "", $request->all(), $response);
            return response()->json($response, 200);
    
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve Manpower list',
                'error' => $e->getMessage()
            ];
    
            // Log API call
            $this->logAPICalls('getmanpowers', "", $request->all(), $response);
            return response()->json($response, 500);
        }
    }
    


    /**
     * Delete a manpower
     */
    public function deletemanpower(Request $request)
    {
        try {

            $manpower = Manpower::findOrFail($request->id);
            $manpower->update(['is_archived' => "I"]);
            $response = [
                'isSuccess' => true,
                'message' => "Manpower successfully deleted."
            ];

            // Log the API call (assuming this method works properly)
            $this->logAPICalls('deletemanpower', $manpower->id, [], [$response]);
            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to delete the Manpower.",
                'error' => $e->getMessage()
            ];

            // Log the API call with failure response
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
                'api_response' => json_encode($resp),
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
