<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLogger;
use App\Models\User;
use App\Models\Category;
use App\Models\Division;
use App\Models\ApiLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;
use Illuminate\Database\Eloquent\ModelNotFoundException;


class DivisionController extends Controller
{
    /**
     * Create a new college office.
     */
    public function createDivision(Request $request)
    {
        try {
            // Get authenticated user
            $user = auth()->user();
    
            if (!$user) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Unauthorized. Please log in.',
                ], 401);
            }
    
            // Validate the incoming request
            $request->validate([
                'division_name' => 'required|string|unique:divisions,division_name',
                'office_location' => 'required|string',
                'staff_id' => 'nullable|array', // Expecting an array of staff IDs
                'staff_id.*' => 'exists:users,id', // Validate each ID exists in the users table
            ]);
    
            // Validate and fetch staff details
            $staffDetails = [];
            if (!empty($request->staff_id)) {
                // Fetch staff users based on provided IDs
                $staffUsers = User::whereIn('id', $request->staff_id)->get(['id', 'first_name', 'last_name', 'role_name']);
    
                // Check if all provided users have the role 'staff'
                $invalidUsers = $staffUsers->where('role_name', '!=', 'staff')->pluck('id')->toArray();
    
                if (!empty($invalidUsers)) {
                    AuditLogger::log('Failed Division Creation - Invalid Staff Role', 'N/A', 'N/A');
    
                    return response()->json([
                        'isSuccess' => false,
                        'message' => 'One or more selected users are not assigned the "staff" role.',
                        'invalid_users' => $invalidUsers, // Show which users are invalid
                    ], 400);
                }
    
                // Check if any staff is already assigned to another division
                $alreadyAssignedStaff = Division::whereNotNull('staff_id')
                    ->whereRaw("JSON_CONTAINS(staff_id, ?)", [json_encode($request->staff_id)])
                    ->exists();
    
                if ($alreadyAssignedStaff) {
                    AuditLogger::log('Failed Division Creation - Staff Already Assigned', 'N/A', 'N/A');
    
                    return response()->json([
                        'isSuccess' => false,
                        'message' => 'One or more selected staff members are already assigned to another division.',
                    ], 400);
                }
    
                // Extract staff details
                $staffDetails = $staffUsers->map(function ($staff) {
                    return [
                        'id' => $staff->id,
                        'first_name' => $staff->first_name,
                        'last_name' => $staff->last_name,
                    ];
                })->toArray();
            }
    
            // Create the division
            $division = Division::create([
                'division_name' => $request->division_name,
                'office_location' => $request->office_location,
                'staff_id' => json_encode(array_column($staffDetails, 'id')),
            ]);
    
            // Prepare the success response
            $response = [
                'isSuccess' => true,
                'message' => 'Division successfully created.',
                'division' => [
                    'id' => $division->id,
                    'division_name' => $division->division_name,
                    'office_location' => $division->office_location,
                    'staff' => $staffDetails, // Include staff details (id, first_name, last_name)
                ],
            ];
    
            // Log API call and audit event
            $this->logAPICalls('createDivision', $user->email, $request->all(), [$response]);
            AuditLogger::log('Created Division', 'N/A', 'Active');
    
            return response()->json($response, 201);
        } catch (ValidationException $v) {
            // Validation error response
            $response = [
                'isSuccess' => false,
                'message' => 'Invalid input data.',
                'error' => $v->errors(),
            ];
    
            $this->logAPICalls('createDivision', $user->email ?? 'unknown', $request->all(), [$response]);
            AuditLogger::log('Failed Division Creation - Validation Error', 'N/A', 'N/A');
    
            return response()->json($response, 400);
        } catch (Throwable $e) {
            // General error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to create the Division.',
                'error' => $e->getMessage(),
            ];
    
            $this->logAPICalls('createDivision', $user->email ?? 'unknown', $request->all(), [$response]);
            AuditLogger::log('Error Creating Division', 'N/A', 'N/A');
    
            return response()->json($response, 500);
        }
    }
    



    

    
    

    /**
     * Update an existing college office.
     */
    public function updateDivision(Request $request, $id)
    {
        try {
            // Get authenticated user
            $user = auth()->user();
    
            if (!$user) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Unauthorized. Please log in.',
                ], 401);
            }
    
            // Find the division by its ID
            $division = Division::findOrFail($id);
    
            // Capture the original division before making any updates
            $beforeUpdate = $division->getOriginal();
    
            // Validate the incoming request
            $request->validate([
                'division_name' => 'sometimes|required|string|unique:divisions,division_name,' . $id,
                'office_location' => 'sometimes|required|string',
                'staff_id' => 'nullable|array', // Expecting an array of staff IDs
                'staff_id.*' => 'exists:users,id', // Validate each ID exists in the users table
            ]);
    
            // Validate and fetch staff details
            $staffDetails = [];
            if (!empty($request->staff_id)) {
                // Fetch staff users based on provided IDs
                $staffUsers = User::whereIn('id', $request->staff_id)->get(['id', 'first_name', 'last_name', 'role_name']);
    
                // Check if all provided users have the role 'staff'
                $invalidUsers = $staffUsers->where('role_name', '!=', 'staff')->pluck('id')->toArray();
    
                if (!empty($invalidUsers)) {
                    return response()->json([
                        'isSuccess' => false,
                        'message' => 'One or more selected users are not assigned the "staff" role.',
                        'invalid_users' => $invalidUsers, // Show which users are invalid
                    ], 400);
                }
    
                // Check if any staff is already assigned to another division
                $alreadyAssignedStaff = Division::whereNotNull('staff_id')
                    ->where('id', '!=', $id) // Exclude current division
                    ->whereRaw("JSON_CONTAINS(staff_id, ?)", [json_encode($request->staff_id)])
                    ->exists();
    
                if ($alreadyAssignedStaff) {
                    return response()->json([
                        'isSuccess' => false,
                        'message' => 'One or more selected staff members are already assigned to another division.',
                    ], 400);
                }
    
                // Extract staff details
                $staffDetails = $staffUsers->map(function ($staff) {
                    return [
                        'id' => $staff->id,
                        'first_name' => $staff->first_name,
                        'last_name' => $staff->last_name,
                    ];
                })->toArray();
            }
    
            // Store the original status before update
            $statusBefore = $division->status; // Ensure correct tracking of status before update
    
            // Update the division
            $division->update([
                'division_name' => $request->division_name ?? $division->division_name,
                'office_location' => $request->office_location ?? $division->office_location,
                'staff_id' => json_encode(array_column($staffDetails, 'id') ?? json_decode($division->staff_id, true)),
            ]);
    
            // Capture the updated division after changes (excluding timestamps)
            $beforeUpdate = $division->only(['id', 'division_name', 'office_location', 'staff_id']);
            $afterUpdate = $division->only(['id', 'division_name', 'office_location', 'staff_id']);
    
            // Audit log the update
            AuditLogger::log(
                'updateDivision',
                json_encode($beforeUpdate),
                json_encode($afterUpdate)   // After state
            );
    
            // Prepare the success response
            $response = [
                'isSuccess' => true,
                'message' => 'Division successfully updated.',
                'status_before' => $statusBefore, // ✅ FIXED: Logs actual previous status
                'division' => [
                    'id' => $division->id,
                    'division_name' => $division->division_name,
                    'office_location' => $division->office_location,
                    'staff' => $staffDetails, // Include staff details (id, first_name, last_name)
                ],
            ];
    
            // Log API call with authenticated user's email
            $this->logAPICalls('updateDivision', $user->email, $request->all(), [$response]);
    
            return response()->json($response, 200);
        } catch (ValidationException $v) {
            // Validation error response
            $response = [
                'isSuccess' => false,
                'message' => 'Invalid input data.',
                'error' => $v->errors(),
            ];
    
            // Audit log failure with "N/A" before/after
            AuditLogger::log(
                'updateDivision',
                $user->email ?? 'unknown',
                'N/A',
                'N/A'
            );
    
            $this->logAPICalls('updateDivision', $user->email ?? 'unknown', $request->all(), [$response]);
            return response()->json($response, 400);
        } catch (Throwable $e) {
            // General error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to update the Division.',
                'error' => $e->getMessage(),
            ];
    
            // Audit log failure with "N/A" before/after
            AuditLogger::log(
                'updateDivision',
                $user->email ?? 'unknown',
                'N/A',
                'N/A'
            );
    
            $this->logAPICalls('updateDivision', $user->email ?? 'unknown', $request->all(), [$response]);
            return response()->json($response, 500);
        }
    }
    

    
    
    

    public function getdropdownCategories(Request $request)
    {
        try {

            $categories = Category::select('id', 'category_name')
                ->where('is_archived', '0')
                ->get();

            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown options retrieved successfully.',
                'category' => $categories,
            ];


            $this->logAPICalls('getDropdownOptions', "", [], [$response]);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown options.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('getDropdownOptions', "", [], [$response]);
            return response()->json($response, 500);
        }
    }

    public function dropdownSupervisor(Request $request)
    {
        try {
            // Retrieve the Supervisor user type ID
            $supervisorTypeId = DB::table('user_types')->where('name', 'Supervisor')->value('id');

            if (!$supervisorTypeId) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Supervisor type not found.'
                ], 404);
            }

            // Fetch active team leaders
            $supervisors = User::where('user_type_id', $supervisorTypeId)
                ->where('is_archived', '0')
                ->get()
                ->map(function ($leader) {
                    // Concatenate full name and return it only
                    return [
                        'id' => $leader->id,
                        'full_name' => trim($leader->first_name . ' ' . $leader->middle_initial . ' ' . $leader->last_name),
                    ];
                });

            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown options retrieved successfully.',
                'supervisor' => $supervisors,
            ];

            // Log the API call
            $this->logAPICalls('dropdownSupervisor', "", $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown options.',
                'error' => $e->getMessage(),
            ];

            $this->logAPICalls('dropdownSupervisor', "", $request->all(), $response);
            return response()->json($response, 500);
        }

    }

    /**
     * Get all college offices.
     */
    public function getDivisions()
{
    try {
        // Get authenticated user
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Unauthorized. Please log in.',
            ], 401);
        }

        // Fetch all divisions that are not archived
        $divisions = Division::where('is_archived', 0)
            ->get()
            ->map(function ($division) {
                // Decode the JSON-encoded staff IDs
                $staffIds = json_decode($division->staff_id, true);

                // Fetch staff details for the decoded IDs where staff is not archived
                $staffDetails = !empty($staffIds)
                    ? User::whereIn('id', $staffIds)
                        ->where('is_archived', 0) // Ensure staff is not archived
                        ->get(['id', 'first_name', 'last_name', 'email'])
                        ->map(function ($staff) {
                            return [
                                'id' => $staff->id,
                                'first_name' => $staff->first_name,
                                'last_name' => $staff->last_name,
                                'email' => $staff->email, // Include email
                            ];
                        })->toArray()
                    : [];

                // Return the structured division data
                return [
                    'id' => $division->id,
                    'division_name' => $division->division_name,
                    'office_location' => $division->office_location,
                    'staff' => $staffDetails, // Include decoded staff details
                    'created_at' => $division->created_at,
                    'updated_at' => $division->updated_at
                ];
            });

        // Build success response
        $response = [
            'isSuccess' => true,
            'message' => 'Divisions retrieved successfully.',
            'divisions' => $divisions,
        ];

        // Log API call with user's email
        $this->logAPICalls('getDivisions', $user->email, [], [$response]);

        return response()->json($response, 200);

    } catch (Throwable $e) {
        // Handle errors
        $response = [
            'isSuccess' => false,
            'message' => 'Failed to retrieve divisions.',
            'error' => $e->getMessage(),
        ];

        $this->logAPICalls('getDivisions', $user->email ?? 'unknown', [], [$response]);

        return response()->json($response, 500);
    }
}

public function getDivisionsArchive()
{
    try {
        // Get authenticated user
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Unauthorized. Please log in.',
            ], 401);
        }

        // Fetch all divisions that are not archived
        $divisions = Division::where('is_archived', 1)
            ->get()
            ->map(function ($division) {
                // Decode the JSON-encoded staff IDs
                $staffIds = json_decode($division->staff_id, true);

                // Fetch staff details for the decoded IDs where staff is not archived
                $staffDetails = !empty($staffIds)
                    ? User::whereIn('id', $staffIds)
                        ->where('is_archived', 0) // Ensure staff is not archived
                        ->get(['id', 'first_name', 'last_name', 'email'])
                        ->map(function ($staff) {
                            return [
                                'id' => $staff->id,
                                'first_name' => $staff->first_name,
                                'last_name' => $staff->last_name,
                                'email' => $staff->email, // Include email
                            ];
                        })->toArray()
                    : [];

                // Return the structured division data
                return [
                    'id' => $division->id,
                    'division_name' => $division->division_name,
                    'office_location' => $division->office_location,
                    'staff' => $staffDetails, // Include decoded staff details
                    'created_at' => $division->created_at,
                    'updated_at' => $division->updated_at
                ];
            });

        // Build success response
        $response = [
            'isSuccess' => true,
            'message' => 'Divisions Archive retrieved successfully.',
            'divisions' => $divisions,
        ];

        // Log API call with user's email
        $this->logAPICalls('getDivisionsArchive', $user->email, [], [$response]);

        return response()->json($response, 200);

    } catch (Throwable $e) {
        // Handle errors
        $response = [
            'isSuccess' => false,
            'message' => 'Failed to retrieve divisions.',
            'error' => $e->getMessage(),
        ];

        $this->logAPICalls('getDivisionsArchive', $user->email ?? 'unknown', [], [$response]);

        return response()->json($response, 500);
    }
}

    
    
    


    /**
     * Delete a college office.
     */
    public function deleteDivision($id)
    {
        try {
            // Get authenticated user
            $user = auth()->user();
    
            if (!$user) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Unauthorized. Please log in.',
                ], 401);
            }
    
            // Find division by ID
            $division = Division::findOrFail($id);
    
            // Capture the original is_archived value before update
            $beforeUpdate = ['is_archived' => $division->is_archived];
    
            // Soft delete by setting is_archived to 1
            $division->update(['is_archived' => 1]);
    
            // Capture the updated is_archived value after update
            $afterUpdate = ['is_archived' => $division->is_archived];
    
            // Audit log only for the is_archived field
            AuditLogger::log(
                'deleteDivision',
                json_encode($beforeUpdate),
                'Deleted'   // After state
            );
    
            // Success response
            $response = [
                'isSuccess' => true,
                'message' => "Division successfully archived.",
            ];
    
            // Log API call with authenticated user's email
            $this->logAPICalls('deleteDivision', $user->email, ['division_id' => $division->id], [$response]);
    
            return response()->json($response, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Division not found.',
            ], 404);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to archive the division.",
                'error' => $e->getMessage(),
            ];
    
            $this->logAPICalls('deleteDivision', $user->email ?? 'unknown', ['division_id' => $id], [$response]);
    
            return response()->json($response, 500);
        }
    }


    public function restoreDivision($id)
    {
        try {
            // Get authenticated user
            $user = auth()->user();
    
            if (!$user) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Unauthorized. Please log in.',
                ], 401);
            }
    
            // Find division by ID
            $division = Division::findOrFail($id);
    
            // Capture the original is_archived value before update
            $beforeUpdate = ['is_archived' => $division->is_archived];
    
            $division->update(['is_archived' => 0]);
    
            // Capture the updated is_archived value after update
            $afterUpdate = ['is_archived' => $division->is_archived];
    
            // Audit log only for the is_archived field
            AuditLogger::log(
                'deleteDivision',
                json_encode($beforeUpdate),
                'Deleted'   // After state
            );
    
            // Success response
            $response = [
                'isSuccess' => true,
                'message' => "Division successfully restored.",
            ];
    
            // Log API call with authenticated user's email
            $this->logAPICalls('restoreDivision', $user->email, ['division_id' => $division->id], [$response]);
    
            return response()->json($response, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Division not found.',
            ], 404);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to restore the division.",
                'error' => $e->getMessage(),
            ];
    
            $this->logAPICalls('restoreDivision', $user->email ?? 'unknown', ['division_id' => $id], [$response]);
    
            return response()->json($response, 500);
        }
    }
    


    /*
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
            // Handle logging error if necessary
            return false;
        }
        return true;
    }

}

