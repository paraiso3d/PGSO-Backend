<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Division;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Throwable;
use App\Models\ApiLog;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

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
            $division = Division::findOrFail($request->division_id);
            $department = $request->department_id ? Department::findOrFail($request->department_id) : null;
            
            $userAccount = User::create([
                'first_name' => $request->first_name,
                'middle_initial' => $request->middle_initial,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'designation' => $request->designation,
                'password' => Hash::make($request->password),
                'role_name' => $request->role_name,
                'division_id' => $division->id,
                'department_id' => $department ? $department->id : null,
                'profile_img' => null, // Explicitly set NULL
            ]);
    
            // Prepare the response
            $response = [
                'isSuccess' => true,
                'message' => 'User account successfully created.',
                'user' => [
                    'id' => $userAccount->id,
                    'first_name' => $userAccount->first_name,
                    'last_name' => $userAccount->last_name,
                    'email' => $userAccount->email,
                    'role_name' => $userAccount->role_name, // Return role name directly
                    'division_id' => $userAccount->division_id,
                    'department_id' => $userAccount->department_id,
                ],
            ];
    
            // Log API call
            $this->logAPICalls('createUserAccount', "", $request->except(['password']), $response);
    
            return response()->json($response, 200);
        } catch (ValidationException $v) {
            // Handle validation errors
            $response = [
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $v->errors(),
            ];
            $this->logAPICalls('createUserAccount', "", $request->all(), $response);
            return response()->json($response, 400);
        } catch (Throwable $e) {
            // Handle other errors
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to create the user account.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('createUserAccount', "", $request->all(), $response);
            return response()->json($response, 500);
        }
    }
     /**
     * Create a get user account.
     */
    public function getUserAccounts(Request $request)
    {
        try {
            $searchTerm = $request->input('search', null);
            $perPage = $request->input('per_page', 10);
    
            // Query the users table with necessary filters
            $query = User::with(['departments:id,department_name','divisions:id,division_name'])
                ->select('id', 'first_name', 'last_name', 'email', 'is_archived', 'department_id', 'role_name', 'division_id')
                ->where('is_archived', '0')
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
    
            // Format the user data
            $formattedUsers = $result->getCollection()->transform(function ($user) {
                return [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'role_name' => $user->role_name, // Directly use the role_name column
                    'department_id' => $user->department_id,
                    'department_name' => optional($user->departments)->department_name,
                    'division_id'=>$user->division_id,
                    'division_name'=> optional($user->divisions)->division_name,
                    'profile_img'=>$user->profile_img,
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
                ],
            ];
    
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
                'last_name' => ['sometimes', 'required', 'string', 'max:255'],
                'email' => $emailRule,
                'password' => ['sometimes', 'nullable', 'string', 'min:8'],
                'role_id' => ['sometimes', 'exists:roles,id'],
                'department_id' => ['sometimes', 'exists:departments,id']
            ]);


            $departmentId = $request->input('department_id');
            if ($request->has('role_id')) {
                $role = Role::findOrFail($request->input('role_id'));

                if (in_array($role->role_name, ['Staff', 'Head', 'Personnel', 'Personnel'])) {
                    $departmentId = 1;
                }
            } else {
                $usertype = Role::findOrFail($userAccount->role_id);
            }
            if ($departmentId) {
                $office = Department::findOrFail($departmentId);
            } else {
                $office = Department::findOrFail($userAccount->department_id);
            }


            $dataToUpdate = [
                'first_name' => $request->input('first_name', $userAccount->first_name),
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
                    'last_name' => $userAccount->last_name,
                    'email' => $userAccount->email,
                    'designation' => $userAccount->designation,
                    'role_id' => $userAccount->role_id,
                    'role_name' => $usertype->role_name,
                    'department_id' => $userAccount->department_id,
                    'department_name' => $office->department_name,
                ]
            ];

            $this->logAPICalls('updateUserAccount', $id, $request->except(['role_id', 'department_id']), $response);
            return response()->json($response, 200);
        } catch (ValidationException $v) {
            $response = [
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $v->errors()
            ];
            $this->logAPICalls('updateUserAccount', $id, $request->except('role_id', 'department_id'), $response);
            return response()->json($response, 422); // Use 422 for validation errors
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to update the UserAccount.',
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('updateUserAccount', $id, $request->except('role_id', 'department_id'), $response);
            return response()->json($response, 500);
        }
    }

    public function changeProfile(Request $request)
{
    try {
        $user = auth()->user(); // Get the logged-in user

        $validator = Validator::make($request->all(), [
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => [
                'sometimes',
                'nullable',
                'string',
                'min:8',
                'regex:/^(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/'
            ],
            'profile_img' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048']
        ], [
            'password.regex' => 'The password must be at least 8 characters long and contain at least one uppercase letter, one number, and one special character (@$!%*?&).'
        ]);

        if ($validator->fails()) {
            $response = [
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ];
            $this->logAPICalls('changeProfile', "", $request->all(), $response);
            return response()->json($response, 422);
        }

        // Update user details
        $user->first_name = $request->input('first_name', $user->first_name);
        $user->last_name = $request->input('last_name', $user->last_name);
        $user->email = $request->input('email', $user->email);

        // Handle password update
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        // Handle profile image upload with structured logic
        $filePath = $user->profile_img;
        $fileUrl = null;

        if ($request->hasFile('profile_img')) {
            $file = $request->file('profile_img');
            $directory = public_path('img/profile'); // Define the directory
            $fileName = 'Profile-' . $user->id . '-' . now()->format('YmdHis') . '.' . $file->getClientOriginalExtension();

            // Ensure directory exists
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            // Move file to the directory
            $file->move($directory, $fileName);

            // Generate the relative file path
            $filePath = 'img/profile/' . $fileName;
            $fileUrl = asset($filePath); // Generate URL
            $user->profile_img = $filePath;
        }

        $user->save(); // Save user changes

        // Prepare response
        $response = [
            'isSuccess' => true,
            'message' => 'Profile updated successfully.',
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'profile_img' => $fileUrl ?? asset($filePath),
            ]
        ];

        $this->logAPICalls('changeProfile', $user->id, $request->except(['password']), $response);
        return response()->json($response, 200);
    } catch (Throwable $e) {
        $response = [
            'isSuccess' => false,
            'message' => 'Failed to update profile.',
            'error' => $e->getMessage()
        ];
        $this->logAPICalls('changeProfile', $user->id, $request->except(['password']), $response);
        return response()->json($response, 500);
    }
}






    public function deleteUserAccount($id)
    {
        try {

            $userAccount = User::findOrFail($id);

            $userAccount->update(['is_archived' => '1']);

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

            $offices = Role::select('id', 'role_name')
                ->where('is_archived', '0')
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

            $offices = Department::select('id', 'department_name')
                ->where('is_archived', '0')
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
}
