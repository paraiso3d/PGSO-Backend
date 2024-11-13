<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\user_type;
use Illuminate\Http\Request;
use App\Models\ApiLog;
use Throwable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UsertypeController extends Controller
{
    /**
     * Create a new user type.
     */
    public function createUserType(Request $request)
    {
        try {
            $request->validate([
                'name' => ['required', 'string'],
            ]);

            $usertype = user_type::create([
                'name' => $request->name,
            ]);

            $response = [
                'isSuccess' => true,
                'message' => "UserType successfully created.",
                'usertype' => $usertype
            ];
            $this->logAPICalls('createUserType', $usertype->id, $request->all(), [$response]);
            return response()->json($response, 201);
        } catch (ValidationException $v) {
            $response = [
                'isSuccess' => false,
                'message' => "Invalid input data.",
                'error' => $v->errors()
            ];
            $this->logAPICalls('createUserType', "", $request->all(), [$response]);
            return response()->json($response, 422);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to create the UserType.",
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('createUserType', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
    }

    /**
     * Update an existing user type.
     */
    public function updateUserType(Request $request, $id)
    {
        try {
            // Find the user type or throw 404
            $usertype = user_type::findOrFail($id);

            // Validate the incoming request
            $request->validate([
                'name' => ['required', 'string', 'alpha_spaces'],
            ]);

            // Update the user_type record
            $usertype->update([
                'name' => $request->name,
            ]);

            // Prepare response for successful update
            $response = [
                'isSuccess' => true,
                'message' => "UserType and related users successfully updated.",
                'usertype' => $usertype
            ];
            $this->logAPICalls('updateUserType', $id, $request->all(), [$response]);

            return response()->json($response, 200);

        } catch (ValidationException $v) {
            // Handle validation error
            $response = [
                'isSuccess' => false,
                'message' => "Invalid input data.",
                'error' => $v->errors()
            ];
            $this->logAPICalls('updateUserType', "", $request->all(), [$response]);

            return response()->json($response, 422);

        } catch (Throwable $e) {
            // Handle general error
            $response = [
                'isSuccess' => false,
                'message' => "Failed to update the UserType.",
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('updateUserType', "", $request->all(), [$response]);

            return response()->json($response, 500);
        }
    }

    /**
     * Get all user types.
     */
    public function getUserTypes(Request $request)
    {
        try {
            // Validate the request to include a search term
            $validated = $request->validate([
                'search' => 'nullable|string', // New search parameter
            ]);

            // Initialize the query
            $query = user_type::select('id', 'name')
                ->whereIn('is_archived', ['A']);

            // Apply search if provided
            if (!empty($validated['search'])) {
                $query->where(function ($q) use ($validated) {
                    $q->where('name', 'like', '%' . $validated['search'] . '%')
                        ->orWhere('description', 'like', '%' . $validated['search'] . '%');
                });
            }

            // Get the user types
            $usertypes = $query->get();

            // Prepare the response
            $response = [
                'isSuccess' => true,
                'message' => "UserTypes list:",
                'usertype' => $usertypes
            ];

            // Log API calls
            $this->logAPICalls('getUserTypes', "", $request->all(), [$response]);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Prepare the error response
            $response = [
                'isSuccess' => false,
                'message' => "Failed to retrieve UserTypes.",
                'error' => $e->getMessage()
            ];

            // Log API calls
            $this->logAPICalls('getUserTypes', "", $request->all(), [$response]);

            return response()->json($response, 500);
        }
    }

    /**
     * Delete a user type.
     */
    public function deleteUserType($id)
    {
        try {
            $usertype = user_type::findOrFail($id); // Find or throw 404

            $usertype->update(['is_archived' => "I"]);

            $response = [
                'isSuccess' => true,
                'message' => "UserType successfully deleted."
            ];
            $this->logAPICalls('deleteUserType', $id, [], [$response]);
            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to delete the UserType.",
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('deleteUserType', "", [], [$response]);
            return response()->json($response, 500);
        }
    }

    /**
      Tooggle Active Inactive a user type.
     */
    public function toggleUsertype(Request $request, $id)
    {

        $request->validate([
            'is_archived' => 'required|in:A,I,D'
        ]);

        try {
            $is_archived = strtoupper($request->is_archived);

            // Use the provided $id directly
            $usertype = user_type::findOrFail($id);
            $usertype->update(['is_archived' => $is_archived]);

            // Set the success message based on the value of is_archived
            if ($is_archived == 'A') {
                $message = "Activated Successfully.";
            } elseif ($is_archived == 'I') {
                $message = "Inactivated Successfully.";
            } elseif ($is_archived == 'D') {
                $message = "Deleted Successfully.";
            } else {
                return response()->json([
                    'isSuccess' => false,
                    'message' => "Invalid toggle value provided."
                ], 400);
            }

            $response = [
                'isSuccess' => true,
                'message' => $message
            ];


            $this->logAPICalls('toggleUsertype', $id, [], $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to update the UserType.",
                'error' => $e->getMessage()
            ];

            $this->logAPICalls('toggleUsertype', "", [], $response);

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
