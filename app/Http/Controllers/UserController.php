<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\user_type;
use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Throwable;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
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
            $usertype = user_type::findOrFail($request->input('user_type_id'));
            $office = Office::findOrFail($request->input('office_id'));


            $userAccount = User::create([
                'first_name' => $request->first_name,
                'middle_initial' => $request->middle_initial,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'office' => $office->acronym,
                'designation' => $request->designation,
                'user_type' => $usertype->name,
                'password' => Hash::make($request->password),
                'user_type_id' => $usertype->id,
                'office_id' => $office->id,
            ]);

            $response = [
                'isSuccess' => true,
                'message' => 'UserAccount successfully created.',
                'user' => $userAccount
            ];
            $this->logAPICalls('createUserAccount', $userAccount->id, $request->except(['password', 'user_type_id', 'office_id']), $response);
            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to create the UserAccount.',
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('createUserAccount', $userAccount->id, $request->except(['password']), $response);
            return response()->json($response, 500);
        }
    }

    public function getUserAccounts(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10);
            $searchTerm = $request->input('search', null);

            // Create query to fetch active user accounts
            $query = User::select('id', 'first_name', 'middle_initial', 'last_name', 'email', 'office', 'designation', 'user_type')
                ->where('is_archived', 'A');

            // Add search condition if search term is provided
            if ($searchTerm) {
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('first_name', 'like', "%{$searchTerm}%")
                        ->orWhere('last_name', 'like', "%{$searchTerm}%")
                        ->orWhere('email', 'like', "%{$searchTerm}%");
                });
            }

            // Paginate the result
            $userAccounts = $query->paginate($perPage);

            // Prepare the response
            $response = [
                'isSuccess' => true,
                'message' => 'User accounts retrieved successfully.',
                'user' => $userAccounts,
                'pagination' => [
                    'total' => $userAccounts->total(),
                    'per_page' => $userAccounts->perPage(),
                    'current_page' => $userAccounts->currentPage(),
                    'last_page' => $userAccounts->lastPage(),
                    'next_page_url' => $userAccounts->nextPageUrl(),
                    'prev_page_url' => $userAccounts->previousPageUrl(),
                ]
            ];

            // Log API calls
            $this->logAPICalls('getUserAccounts', "", [], [$response]);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve user accounts.',
                'error' => $e->getMessage()
            ];

            // Log the error
            $this->logAPICalls('getUserAccounts', "", [], [$response]);

            return response()->json($response, 500);
        }
    }


    /**
     * Update an existing user account.
     */
    public function updateUserAccount(Request $request, $id)
    {
        try {
            // Find the user account
            $userAccount = User::findOrFail($id);

            $emailRule = ['sometimes', 'string', 'email', 'max:255'];

            // Check if the email is provided in the request
            if ($request->has('email')) {
                $emailRule[] = Rule::unique('users')->ignore($userAccount->id);
            }

            $request->validate([
                'first_name' => ['sometimes', 'required', 'string', 'max:255'],
                'middle_initial' => ['sometimes', 'string', 'max:5'],
                'last_name' => ['sometimes', 'required', 'string', 'max:255'],
                'email' => $emailRule,
                'office' => ['sometimes', 'string', 'max:255'],
                'designation' => ['sometimes', 'string', 'max:255'],
                'user_type' => ['sometimes', 'string', 'max:255'],
                'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            ]);

            // Only hash the password if it has been provided in the request
            $dataToUpdate = [
                'first_name' => $request->first_name,
                'middle_initial' => $request->middle_initial,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'office' => $request->office,
                'designation' => $request->designation,
                'user_type' => $request->user_type,
            ];

            // Hash the password only if provided
            if ($request->filled('password')) {
                $dataToUpdate['password'] = Hash::make($request->password);
            }

            // Update the user account
            $userAccount->update($dataToUpdate);

            $response = [
                'isSuccess' => true,
                'message' => 'UserAccount successfully updated.',
                'user' => $userAccount
            ];
            $this->logAPICalls('updateUserAccount', $id, $request->except('user_type_id','office_id'), $response);
            return response()->json($response, 200);
        } catch (ValidationException $v) {
            $response = [
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $v->errors()
            ];
            $this->logAPICalls('updateUserAccount', $id, $request->all('user_type_id','office_id'), $response);
            return response()->json($response, 422); // Use 422 for validation errors
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to update the UserAccount.',
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('updateUserAccount', $id, $request->all('division_id'), $response);
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

            $userAccount->update(['is_archived' => "I"]);

            $response = [
                'isSuccess' => true,
                'message' => 'UserAccount successfully deleted.'
            ];
            $this->logAPICalls('deleteUserAccount', $userAccount->id, [], $response);
            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to delete the UserAccount.',
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('deleteUserAccount', '', [], $response);
            return response()->json($response, 500);
        }
    }


    public function getDropdownOptionsUsertype(Request $request)
    {
        try {


            $userTypes = user_type::select('id', 'name')
                ->where('is_archived', 'A')
                ->get();

            // Build the response
            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown data retrieved successfully.',
                'user_types' => $userTypes,
            ];

            // Log the API call
            $this->logAPICalls('getDropdownOptionsUsertype', "", $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown data.',
                'error' => $e->getMessage()
            ];

            // Log the error
            $this->logAPICalls('getDropdownOptionsUsertype', "", $request->all(), $response);

            return response()->json($response, 500);
        }
    }


    public function getDropdownOptionsUseroffice(Request $request)
    {
        try {

            $offices = Office::select('id', 'acronym')
                ->where('is_archived', 'A')
                ->get();

            // Build the response
            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown data retrieved successfully.',
                'office' => $offices,
            ];

            // Log the API call
            $this->logAPICalls('getDropdownOptionsUseroffice', "", $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown data.',
                'error' => $e->getMessage()
            ];

            // Log the error
            $this->logAPICalls('getDropdownOptionsUseroffice', "", $request->all(), $response);

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
