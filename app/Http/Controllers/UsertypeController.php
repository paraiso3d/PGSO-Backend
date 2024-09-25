<?php

namespace App\Http\Controllers;

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
                'name' => ['required', 'alpha'],
            ]);

            $usertype = user_type::create([
                'name' => $request->name,
            ]);

            $response = [
                'isSuccess' => true,
                'message' => "UserType successfully created.",
                'data' => $usertype
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
            $usertype = user_type::findOrFail($id); // Find the user type or throw 404

            $request->validate([
                'name' => ['required', 'string', 'alpha'], // Ensure the 'name' is a string
            ]);

            $usertype->update([
                'name' => $request->name,
            ]);

            $response = [
                'isSuccess' => true,
                'message' => "UserType successfully updated.",
                'data' => $usertype
            ];
            $this->logAPICalls('updateUserType', $id, $request->all(), [$response]);
            return response()->json($response, 200);
        } catch (ValidationException $v) {
            $response = [
                'isSuccess' => false,
                'message' => "Invalid input data.",
                'error' => $v->errors()
            ];
            $this->logAPICalls('updateUserType', "", $request->all(), [$response]);
            return response()->json($response, 422);
        } catch (Throwable $e) {
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
    public function getUserTypes()
    {
        try {
            $usertypes = user_type::select('name', 'description');

            $response = [
                'isSuccess' => true,
                'message' => "UserTypes list:",
                'data' => $usertypes
            ];
            $this->logAPICalls('getUserTypes', "", [], [$response]);
            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to retrieve UserTypes.",
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('getUserTypes', "", [], [$response]);
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

            $usertype->delete();

            $response = [
                'isSuccess' => true,
                'message' => "UserType successfully deleted."
            ];
            $this->logAPICalls('deleteUserType', $id, [], [$response]);
            return response()->json($response, 204);
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
