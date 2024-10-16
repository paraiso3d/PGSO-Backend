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
            $validate = $request->validate([
                'paginate' => 'required'
            ]);
    
            $searchTerm = $request->input('search', null);
            $perPage = $request->input('per_page', 10);
    
            if ($validate['paginate'] == 0) {
                $query = User::select('id', 'first_name', 'middle_initial', 'last_name', 'email', 'office', 'designation', 'user_type', 'is_archived', 'office_id', 'user_type_id')
                    ->where('is_archived', 'A')
                    ->when($searchTerm, function ($query, $searchTerm) {
                        return $query->where(function ($activeQuery) use ($searchTerm) {
                            $activeQuery->where('first_name', 'like', '%' . $searchTerm . '%')
                                ->orWhere('email', 'like', '%' . $searchTerm . '%')
                                ->orWhere('last_name', 'like', '%' . $searchTerm . '%');
                        });
                    })
                    ->get();
    
                if ($query->isEmpty()) {
                    $response = [
                        'isSuccess' => false,
                        'message' => 'No active Users found matching the criteria',
                    ];
                    $this->logAPICalls('getUserAccounts', "", $request->all(), $response);
                    return response()->json($response, 500);
                }
    
                // Prepare response without pagination
                $response = [
                    'isSuccess' => true,
                    'message' => 'User accounts retrieved successfully.',
                    'user' => $query
                ];
            } else {
                $query = User::select('id', 'first_name', 'middle_initial', 'last_name', 'email', 'office', 'designation', 'user_type', 'is_archived', 'office_id', 'user_type_id')
                    ->where('is_archived', 'A')
                    ->when($searchTerm, function ($query, $searchTerm) {
                        return $query->where(function ($activeQuery) use ($searchTerm) {
                            $activeQuery->where('first_name', 'like', '%' . $searchTerm . '%')
                                ->orWhere('email', 'like', '%' . $searchTerm . '%')
                                ->orWhere('last_name', 'like', '%' . $searchTerm . '%');
                        });
                    })
                    ->paginate($perPage);
    
                if ($query->isEmpty()) {
                    $response = [
                        'isSuccess' => false,
                        'message' => 'No active Users found matching the criteria',
                    ];
                    $this->logAPICalls('getUserAccounts', "", $request->all(), $response);
                    return response()->json($response, 500);
                }
    
                // Prepare response with pagination
                $response = [
                    'isSuccess' => true,
                    'message' => 'User accounts retrieved successfully.',
                    'user' => $query,
                    'pagination' => [
                        'total' => $query->total(),
                        'per_page' => $query->perPage(),
                        'current_page' => $query->currentPage(),
                        'last_page' => $query->lastPage(),
                        'url' => url('api/accounts?page=' . $query->currentPage() . '&per_page=' . $query->perPage()),
                    ],
                ];
            }
    
            $this->logAPICalls('getUserAccounts', "", $request->all(), $response);
            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve user accounts.',
                'error' => $e->getMessage()
            ];
    
            $this->logAPICalls('getUserAccounts', "", $request->all(), $response);
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
    
            // Define validation rules for the request
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
                'user_type_id' => ['sometimes', 'exists:user_types,id'],
                'office_id' => ['sometimes', 'exists:offices,id']
            ]);
    
            // Retrieve the user type and office
            if ($request->has('user_type_id')) {
                $usertype = user_type::findOrFail($request->input('user_type_id'));
            } else {
                // Keep the existing user_type_id if not provided
                $usertype = $userAccount->user_type_id;
            }
    
            if ($request->has('office_id')) {
                $office = Office::findOrFail($request->input('office_id'));
            } else {
                // Keep the existing office_id if not provided
                $office = $userAccount->office_id;
            }
    
            // Only hash the password if it has been provided in the request
            $dataToUpdate = [
                'first_name' => $request->input('first_name', $userAccount->first_name),
                'middle_initial' => $request->input('middle_initial', $userAccount->middle_initial),
                'last_name' => $request->input('last_name', $userAccount->last_name),
                'email' => $request->input('email', $userAccount->email),
                'office' => $office->acronym,
                'designation' => $request->input('designation', $userAccount->designation),
                'user_type' => $usertype->name,
                'user_type_id' => $usertype->id,
                'office_id' => $office->id
            ];
    
            // Hash the password only if provided
            if ($request->filled('password')) {
                $dataToUpdate['password'] = Hash::make($request->password);
            }
    
            // Update the user account
            $userAccount->update($dataToUpdate);
    
            // Retrieve the fresh user account
            $userAccount = $userAccount->fresh();
    
            // Hide user_type_id and office_id from the response
            $userAccount->makeHidden(['user_type_id', 'office_id']);
    
            $response = [
                'isSuccess' => true,
                'message' => 'UserAccount successfully updated.',
                'user' => $userAccount // Get the updated user data without hidden attributes
            ];
            $this->logAPICalls('updateUserAccount', $id, $request->except(['user_type_id', 'office_id']), $response);
            return response()->json($response, 200);
        } catch (ValidationException $v) {
            $response = [
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $v->errors()
            ];
            $this->logAPICalls('updateUserAccount', $id, $request->except('user_type_id', 'office_id'), $response);
            return response()->json($response, 422); // Use 422 for validation errors
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to update the UserAccount.',
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('updateUserAccount', $id, $request->except('user_type_id', 'office_id'), $response);
            return response()->json($response, 500);
        }
    }


    public function deleteUserAccount($id)
{
    try {

        $userAccount = User::findOrFail($id);

        $userAccount->update(['is_archived' => 'I']);

        $response = [
            'isSuccess' => true,
            'message' => 'UserAccount successfully archived.',
        ];

        $this->logAPICalls('deleteUserAccount', $id, [], $response);

        return response()->json($response, 200);
    } catch (Throwable $e) {
        $response = [
            'isSuccess' => false,
            'message' => 'Failed to archive the UserAccount.',
            'error' => $e->getMessage()
        ];

        $this->logAPICalls('deleteUserAccount', $id, [], $response);

        return response()->json($response, 500);
    }
}
    

    public function getDropdownOptionsUsertype(Request $request)
    {
        try {

            $offices = user_type::select('id', 'name')
                ->where('is_archived', 'A')
                ->get();

            // Build the response
            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown data retrieved successfully.',
                'user_types' => $offices,
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
