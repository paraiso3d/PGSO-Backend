<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use App\Helpers\AuditLogger;
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
use Illuminate\Support\Facades\Mail;

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

                AuditLogger::log('Failed User Creation - Validation Error');
                return response()->json($response, 422);
            }

            $division = Division::findOrFail($request->division_id);
            $department = $request->department_id ? Department::findOrFail($request->department_id) : null;

            $profilePath = null;
            if ($request->hasFile('avatar')) {
                $directoryPath = public_path('img/profile');
                if (!file_exists($directoryPath)) {
                    mkdir($directoryPath, 0755, true);
                }

                $file = $request->file('avatar');
                $fileName = 'Profile-' . uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move($directoryPath, $fileName);
                $profilePath = 'img/profile/' . $fileName;
            }

            // Set default password
            $defaultPassword = '@Password1';

            $userAccount = User::create([
                'first_name' => $request->first_name,
                'middle_initial' => $request->middle_initial ?? null,
                'last_name' => $request->last_name,
                'number' => $request->number,
                'age' => $request->age,
                'gender' => $request->gender,
                'email' => $request->email,
                'designation' => $request->designation,
                'password' => Hash::make($defaultPassword),
                'role_name' => $request->role_name,
                'avatar' => $profilePath,
            ]);

            // Send email with default password
            $emailBody = "Dear {$userAccount->first_name} {$userAccount->last_name},\n\n"
                . "Your account has been created.\n\n"
                . "Login Email: {$userAccount->email}\n"
                . "Default Password: {$defaultPassword}\n\n"
                . "Please log in and change your password as soon as possible.\n\n"
                . "Thank you.";

            Mail::raw($emailBody, function ($message) use ($userAccount) {
                $message->to($userAccount->email)
                    ->subject('Your New Account Credentials');
            });

            AuditLogger::log('Created New User Account', 'N/A', 'Active');

            $response = [
                'isSuccess' => true,
                'message' => 'User account successfully created.',
                'user' => [
                    'id' => $userAccount->id,
                    'first_name' => $userAccount->first_name,
                    'middle_initial' => $userAccount->middle_initial,
                    'last_name' => $userAccount->last_name,
                    'number' => $userAccount->number,
                    'age' => $userAccount->age,
                    'gender' => $userAccount->gender,
                    'email' => $userAccount->email,
                    'designation' => $userAccount->designation,
                    'role_name' => $userAccount->role_name,
                    'division_id' => $userAccount->division_id,
                    'department_id' => $userAccount->department_id,
                    'avatar' => $profilePath ? asset($profilePath) : null,
                ],
            ];

            return response()->json($response, 201);
        } catch (ValidationException $v) {
            AuditLogger::log('Failed User Creation - Validation Exception');
            return response()->json([
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $v->errors(),
            ], 422);
        } catch (Throwable $e) {
            AuditLogger::log('Error Creating User Account', null, $e->getMessage());
            return response()->json([
                'isSuccess' => false,
                'message' => 'An error occurred while creating the user account.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    /**
     * Create a get user account.
     */
    public function getUserAccounts(Request $request)
    {
        try {
            // Ensure the user is authenticated
            if (!auth()->check()) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Unauthorized access.',
                ], 401);
            }

            $userEmail = auth()->user()->email;

            $searchTerm = $request->input('search', null);
            $perPage = $request->input('per_page', 10);

            $query = User::select('id', 'avatar', 'first_name', 'last_name', 'email', 'is_archived', 'role_name', 'age', 'gender', 'number', 'status')
                ->where('is_archived', '0');

            $result = $query->paginate($perPage);

            if ($result->isEmpty()) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'No  Users found',
                ];

                $this->logAPICalls('getUserAccounts', $userEmail, $request->all(), $response);
                return response()->json($response, 404);
            }

            $formattedUsers = $result->getCollection()->transform(function ($user) {
                return [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'role_name' => $user->role_name,
                    'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                    'is_archived' => $user->is_archived,
                    'age' => $user->age,
                    'gender' => $user->gender,
                    'number' => $user->number,
                    'status' => $user->status
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

            $this->logAPICalls('getUserAccounts', $userEmail, $request->all(), $response);
            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve user accounts.',
                'error' => $e->getMessage(),
            ];

            $this->logAPICalls('getUserAccounts', auth()->user()->email ?? "Unknown", $request->all(), $response);
            return response()->json($response, 500);
        }
    }


    public function getUserAccountsArchive(Request $request)
    {
        try {
            // Ensure the user is authenticated
            if (!auth()->check()) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Unauthorized access.',
                ], 401);
            }

            $userEmail = auth()->user()->email;

            $searchTerm = $request->input('search', null);
            $perPage = $request->input('per_page', 10);

            $query = User::select('id', 'avatar', 'first_name', 'last_name', 'email', 'is_archived', 'role_name', 'age', 'gender', 'number', 'status')
                ->where('is_archived', '1');

            $result = $query->paginate($perPage);

            if ($result->isEmpty()) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'No Users found',
                ];

                $this->logAPICalls('getUserAccountsArchive', $userEmail, $request->all(), $response);
                return response()->json($response, 404);
            }

            $formattedUsers = $result->getCollection()->transform(function ($user) {
                return [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'role_name' => $user->role_name,
                    'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                    'is_archived' => $user->is_archived,
                    'age' => $user->age,
                    'gender' => $user->gender,
                    'number' => $user->number,
                    'status' => $user->status
                ];
            });

            $response = [
                'isSuccess' => true,
                'message' => 'User accounts archive retrieved successfully.',
                'user' => $formattedUsers,
                'pagination' => [
                    'total' => $result->total(),
                    'per_page' => $result->perPage(),
                    'current_page' => $result->currentPage(),
                    'last_page' => $result->lastPage(),
                ],
            ];

            $this->logAPICalls('getUserAccountsArchive', $userEmail, $request->all(), $response);
            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve user accounts.',
                'error' => $e->getMessage(),
            ];

            $this->logAPICalls('getUserAccountsArchive', auth()->user()->email ?? "Unknown", $request->all(), $response);
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
            $user = auth()->user();

            // Validation
            $validator = Validator::make($request->all(), [
                'first_name' => ['sometimes', 'string', 'max:255'],
                'last_name' => ['sometimes', 'string', 'max:255'],
                'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
                'current_password' => ['required'],
                'password' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'min:8',
                    'regex:/^(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/'
                ],
                'number' => ['sometimes', 'string', Rule::unique('users')->ignore($user->id)],
                'age' => ['sometimes', 'integer', 'min:1', 'max:100'],
                'gender' => ['sometimes', 'in:Male,Female'],
                'avatar' => ['sometimes', 'nullable', 'file', 'mimes:jpeg,png,jpg,pdf,docx', 'max:2048']
            ], [
                'password.regex' => 'Password must include at least one uppercase letter, one number, and one special character.',
            ]);

            if ($validator->fails()) {
                AuditLogger::log('Failed Profile Update - Validation Error', $user->email, 'N/A');
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verify current password
            if (!Hash::check($request->current_password, $user->password)) {
                AuditLogger::log('Failed Profile Update - Incorrect Password', $user->email, 'Incorrect current password.');
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Current password is incorrect.'
                ], 403);
            }

            $oldData = $user->only(['first_name', 'last_name', 'email', 'number', 'age', 'gender', 'avatar']);

            // Update fields
            $user->fill($request->only(['first_name', 'last_name', 'email', 'number', 'age', 'gender']));

            if ($request->filled('password')) {
                $user->password = Hash::make($request->password);
            }

            // Avatar upload
            if ($request->hasFile('avatar')) {
                $file = $request->file('avatar');
                $fileName = 'Profile-' . $user->id . '-' . now()->format('YmdHis') . '.' . $file->getClientOriginalExtension();
                $relativePath = 'img/profile/' . $fileName;
                $absolutePath = public_path($relativePath);

                // Ensure directory exists
                if (!file_exists(dirname($absolutePath))) {
                    mkdir(dirname($absolutePath), 0755, true);
                }

                // Move file
                $file->move(dirname($absolutePath), basename($absolutePath));

                // Delete old avatar if exists (in img/profile, not storage)
                if ($user->avatar && file_exists(public_path($user->avatar))) {
                    unlink(public_path($user->avatar));
                }

                // Save path (no 'storage/')
                $user->avatar = $relativePath;
            }

            $user->save();

            $response = [
                'isSuccess' => true,
                'message' => 'Profile updated successfully.',
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'number' => $user->number,
                    'age' => $user->age,
                    'gender' => $user->gender,
                    'avatar' => $user->avatar ? asset($user->avatar) : null
                ]
            ];

            AuditLogger::log('Updated Profile', json_encode($oldData), json_encode($user->only(['first_name', 'last_name', 'email', 'number', 'age', 'gender', 'avatar'])));

            return response()->json($response, 200);
        } catch (Throwable $e) {
            AuditLogger::log('Error Updating Profile', $user->email ?? 'Unknown', $e->getMessage());
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to update profile.',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    public function toggleUserStatus($id)
    {
        try {
            // Find the user account
            $userAccount = User::findOrFail($id);

            // Store the old status for audit logging
            $oldData = $userAccount->only(['status']);

            // Determine new status
            $newStatus = $userAccount->status === 'Active' ? 'Inactive' : 'Active';

            // Update the user's status
            $userAccount->update(['status' => $newStatus]);

            // Prepare a response message
            $action = $newStatus === 'Inactive' ? 'deactivated' : 'activated';
            $response = [
                'isSuccess' => true,
                'message' => "User account has been successfully {$action}.",
            ];

            // Log audit
            AuditLogger::log(
                ucfirst($action) . ' User',
                json_encode($oldData),
                "Status changed to {$newStatus}"
            );

            // Log API call
            $this->logAPICalls('toggleUserStatus', $userAccount->email, [], $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle errors
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to change user status.',
                'error' => $e->getMessage()
            ];

            // Log error
            AuditLogger::log(
                'Error Changing User Status',
                json_encode(['id' => $id]),
                $e->getMessage()
            );

            return response()->json($response, 500);
        }
    }



    public function deleteUserAccount($id)
    {
        try {
            // Find user account
            $userAccount = User::findOrFail($id);

            // Store old data for audit logging
            $oldData = $userAccount->only(['is_archived']);

            // Archive the user account
            $userAccount->update(['is_archived' => '1']);

            // Prepare response
            $response = [
                'isSuccess' => true,
                'message' => 'User account successfully deleted.',
            ];

            // Log audit - Store changes
            AuditLogger::log(
                'Delete User Account',
                json_encode($oldData),
                'Deleted'
            );

            // Log API call using email instead of ID
            $this->logAPICalls('deleteUserAccount', $userAccount->email, [], $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle errors
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to delete the user account.',
                'error' => $e->getMessage()
            ];

            // Log error audit
            AuditLogger::log('Error Deleting User Account', json_encode(['id' => $id]), $e->getMessage());

            return response()->json($response, 500);
        }
    }


    public function restoreUserAccount($id)
    {
        try {
            // Find user account
            $userAccount = User::findOrFail($id);

            // Store old data for audit logging
            $oldData = $userAccount->only(['is_archived']);

            // Archive the user account
            $userAccount->update(['is_archived' => '0']);

            // Prepare response
            $response = [
                'isSuccess' => true,
                'message' => 'User account successfully unarchive.',
            ];

            // Log audit - Store changes
            AuditLogger::log(
                'User Account Successfully Unarchive',
                json_encode($oldData),
                'Deleted'
            );

            // Log API call using email instead of ID
            $this->logAPICalls('restoreUserAccount', $userAccount->email, [], $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle errors
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to delete the user account.',
                'error' => $e->getMessage()
            ];
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
