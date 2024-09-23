<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\usertype;
use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Throwable;

class UserController extends Controller
{
    /**
     * Create a new user account.
     */
    public function createUserAccount(Request $request)
    {
        try {
            $validator = User::validateUserAccount($request->all());

            if ($validator->fails()) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors()
                ];
                $this->logAPICalls('createUserAccount', "", $request->all(), $response);
                return response()->json($response, 500);
            }

            $userAccount = User::create([
                'first_name' => $request->first_name,
                'middle_initial' => $request->middle_initial,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'office' => $request->office,
                'designation' => $request->designation,
                'user_type' => $request->user_type,
                'password' => Hash::make($request->password)
            ]);

            $response = [
                'isSuccess' => true,
                'message' => 'UserAccount successfully created.',
                'data' => $userAccount
            ];
            $this->logAPICalls('createUserAccount', $userAccount->id, $request->all(), $response);
            return response()->json($response, 200);
        }
        catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to create the UserAccount.',
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('createUserAccount', "", $request->all(), $response);
            return response()->json($response, 500);
        }
    }

    /**
     * Read: Get all user accounts.
     */
    public function getUserAccounts(Request $request)
    {
        try {
           
            $perPage = $request->input('per_page', 10); 
    
            // Fetch user accounts with pagination
            $userAccounts = User::paginate($perPage);
    
            $response = [
                'isSuccess' => true,
                'message' => 'User accounts retrieved successfully.',
                'data' => $userAccounts
            ];
            $this->logAPICalls('getUserAccounts', "", [], $response);
            return response()->json($response, 200);
        }
        catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve user accounts.',
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('getUserAccounts', "", [], $response);
            return response()->json($response, 500);
        }
    }

    /**
     * Update an existing user account.
     */
    public function updateUserAccount(Request $request, $id)
    {
        try {
            $userAccount = User::findOrFail($id);

            $validator = User::validateUserAccount($request->all());

            if ($validator->fails()) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors()
                ];
                $this->logAPICalls('updateUserAccount', $id, $request->all(), $response);
                return response()->json($response, 500);
            }

            $userAccount->update([
                'first_name' => $request->first_name,
                'middle_intial' => $request->middle_inital,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'office' => $request->office,
                'designation' => $request->designation,
                'user_type' => $request->user_type,
                'password' => Hash::make($request->password),
            ]);

            $response = [
                'isSuccess' => true,
                'message' => 'UserAccount successfully updated.',
                'data' => $userAccount
            ];
            $this->logAPICalls('updateUserAccount', $id, $request->all(), $response);
            return response()->json($response, 200);
        }
        catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to update the UserAccount.',
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('updateUserAccount', $id, $request->all(), $response);
            return response()->json($response, 500);
        }
    }

    /**
     * Delete a user account.
     */
    public function deleteUserAccount(Request $request)
    {
        try {
            $userAccount = User::find($request->id);

            $userAccount->update(['isarchive' => "I"]);

            $response = [
                'isSuccess' => true,
                'message' => 'UserAccount successfully deleted.'
            ];
            $this->logAPICalls('deleteUserAccount', $userAccount->id , [], $response);
            return response()->json($response, 200);
        }
        catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to delete the UserAccount.',
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('deleteUserAccount', '', [], $response);
            return response()->json($response, 500);
        }
    }

    /**
     * Log all API calls.
     */
    public function logAPICalls(string $methodName, string $userId, array $param, array $resp)
    {
        try {
            \App\Models\ApiLog::create([
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
}
