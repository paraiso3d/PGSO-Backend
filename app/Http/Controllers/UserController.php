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


            $userTypeId = $request->input('user_type_id');
            $usertype = user_type::findOrFail($userTypeId);


            $gsoRoles = user_type::whereIn('name', ['TeamLeader', 'Supervisor', 'Controller', 'Administrator'])
                ->pluck('id')->toArray();

            if (in_array($usertype->id, $gsoRoles)) {
                $officeId = 1; // Default to GSO
            } else {
                $officeId = $request->input('office_id');
            }


            $office = Office::findOrFail($officeId);


            $userAccount = User::create([
                'first_name' => $request->first_name,
                'middle_initial' => $request->middle_initial,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'designation' => $request->designation,
                'password' => Hash::make($request->password),
                'user_type_id' => $usertype->id,
                'office_id' => $office->id,
            ]);


            $response = [
                'isSuccess' => true,
                'message' => 'UserAccount successfully created.',
                'user' => [
                    'id' => $userAccount->id,
                    'is_archived' => $userAccount->is_archived,
                    'first_name' => $userAccount->first_name,
                    'middle_initial' => $userAccount->middle_initial,
                    'last_name' => $userAccount->last_name,
                    'email' => $userAccount->email,
                    'designation' => $userAccount->designation,
                    'user_type_id' => $userAccount->user_type_id,
                    'user_type_name' => $usertype->name,
                    'office_id' => $userAccount->office_id,
                    'office_name' => $office->acronym,
                ]
            ];

            $this->logAPICalls('createUserAccount', "", $request->except(['password', 'user_type_id', 'office_id']), $response);
            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to create the UserAccount.',
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('createUserAccount', "", $request->except(['password']), $response);
            return response()->json($response, 500);
        }
    }


    public function getUserAccounts(Request $request)
    {
        try {
            $searchTerm = $request->input('search', null);
            $perPage = $request->input('per_page', 10);


            $query = User::with(['user_types:id,name', 'office:id,acronym'])
                ->select('id', 'first_name', 'middle_initial', 'last_name', 'email', 'designation', 'is_archived', 'office_id', 'user_type_id')
                ->where('is_archived', 'A')
                ->when($searchTerm, function ($query, $searchTerm) {
                    return $query->where(function ($activeQuery) use ($searchTerm) {
                        $activeQuery->where('first_name', 'like', '%' . $searchTerm . '%')
                            ->orWhere('email', 'like', '%' . $searchTerm . '%')
                            ->orWhere('last_name', 'like', '%' . $searchTerm . '%');
                    });
                });


            $result = $query->paginate($perPage);

            if ($result->isEmpty()) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'No active Users found matching the criteria',
                ];
                $this->logAPICalls('getUserAccounts', "", $request->all(), $response);
                return response()->json($response, 500);
            }


            $formattedUsers = $result->getCollection()->transform(function ($user) {
                return [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'middle_initial' => $user->middle_initial,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'designation' => $user->designation,
                    'user_type_id' => $user->user_type_id,
                    'user_type_name' => optional($user->user_types)->name,
                    'office_id' => $user->office_id,
                    'office_name' => optional($user->office)->acronym,
                    'is_archived' => $user->is_archived,
                ];
            });


            $response = [
                'isSuccess' => true,
                'message' => 'User accounts retrieved successfully.',
                'user' => $formattedUsers,
                'pagination' => [
                    'total' => $result->total(),
                    'per_page' => $result->perPage(),
                    'current_page' => $result->currentPage(),
                    'last_page' => $result->lastPage(),
                    'next_page_url' => $result->nextPageUrl(),
                    'prev_page_url' => $result->previousPageUrl(), 
                    'url' => url('api/userList?page=' . $result->currentPage() . '&per_page=' . $result->perPage()),
                ],
            ];

            $this->logAPICalls('getUserAccounts', "", $request->all(), $response);
            return response()->json($response, 200);

        } catch (Throwable $e) {
            // Handle error cases
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
                'designation' => ['sometimes', 'string', 'max:255'],
                'password' => ['sometimes', 'nullable', 'string', 'min:8'],
                'user_type_id' => ['sometimes', 'exists:user_types,id'],
                'office_id' => ['sometimes', 'exists:offices,id']
            ]);


            $officeId = $request->input('office_id');
            if ($request->has('user_type_id')) {
                $usertype = user_type::findOrFail($request->input('user_type_id'));

                if (in_array($usertype->name, ['TeamLeader', 'Supervisor', 'Controller', 'Administrator'])) {
                    $officeId = 1;
                }
            } else {
                $usertype = user_type::findOrFail($userAccount->user_type_id);
            }


            if ($officeId) {
                $office = Office::findOrFail($officeId);
            } else {
                $office = Office::findOrFail($userAccount->office_id);
            }


            $dataToUpdate = [
                'first_name' => $request->input('first_name', $userAccount->first_name),
                'middle_initial' => $request->input('middle_initial', $userAccount->middle_initial),
                'last_name' => $request->input('last_name', $userAccount->last_name),
                'email' => $request->input('email', $userAccount->email),
                'designation' => $request->input('designation', $userAccount->designation),
                'user_type_id' => $usertype->id,
                'office_id' => $office->id,
                'office' => $office->acronym, // Keep office acronym for response
                'user_type' => $usertype->name // Keep user type name for response
            ];

            // Hash the password only if provided
            if ($request->filled('password')) {
                $dataToUpdate['password'] = Hash::make($request->password);
            }

            // Update the user account
            $userAccount->update($dataToUpdate);

            // Retrieve the updated user account
            $userAccount = $userAccount->fresh();

            // Prepare the response
            $response = [
                'isSuccess' => true,
                'message' => 'UserAccount successfully updated.',
                'user' => [
                    'id' => $userAccount->id,
                    'first_name' => $userAccount->first_name,
                    'middle_initial' => $userAccount->middle_initial,
                    'last_name' => $userAccount->last_name,
                    'email' => $userAccount->email,
                    'designation' => $userAccount->designation,
                    'user_type_id' => $userAccount->user_type_id,
                    'user_type_name' => $usertype->name,
                    'office_id' => $userAccount->office_id,
                    'office_name' => $office->acronym,
                ]
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


            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown data retrieved successfully.',
                'user_types' => $offices,
            ];


            $this->logAPICalls('getDropdownOptionsUseroffice', "", $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {


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
