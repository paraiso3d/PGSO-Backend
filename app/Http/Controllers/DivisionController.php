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
            $user = auth()->user();
    
            if (!$user) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Unauthorized. Please log in.',
                ], 401);
            }
    
            $request->validate([
                'division_name' => 'required|string|unique:divisions,division_name',
                'office_location' => 'required|string',
                'staff_id' => 'nullable|array',
                'staff_id.*' => 'exists:users,id',
                'personnel_id' => 'nullable|array',
                'personnel_id.*' => 'exists:users,id',
            ]);
    
            // Handle Staff
            $staffDetails = [];
            if (!empty($request->staff_id)) {
                $staffUsers = User::whereIn('id', $request->staff_id)->get(['id', 'first_name', 'last_name', 'role_name']);
    
                $invalidStaff = $staffUsers->where('role_name', '!=', 'staff')->pluck('id')->toArray();
                if (!empty($invalidStaff)) {
                    AuditLogger::log('Failed Division Creation - Invalid Staff Role', 'N/A', 'N/A');
                    return response()->json([
                        'isSuccess' => false,
                        'message' => 'One or more selected users are not assigned the "staff" role.',
                        'invalid_users' => $invalidStaff,
                    ], 400);
                }
    
                $alreadyAssignedStaff = Division::whereNotNull('staff_id')
                    ->where(function ($query) use ($request) {
                        foreach ($request->staff_id as $staffId) {
                            $query->orWhereRaw("JSON_CONTAINS(staff_id, ?)", [json_encode($staffId)]);
                        }
                    })->exists();
    
                if ($alreadyAssignedStaff) {
                    AuditLogger::log('Failed Division Creation - Staff Already Assigned', 'N/A', 'N/A');
                    return response()->json([
                        'isSuccess' => false,
                        'message' => 'One or more selected staff members are already assigned to another division.',
                    ], 400);
                }
    
                $staffDetails = $staffUsers->map(function ($staff) {
                    return [
                        'id' => $staff->id,
                        'first_name' => $staff->first_name,
                        'last_name' => $staff->last_name,
                    ];
                })->toArray();
            }
    
            // Handle Personnel
            $personnelDetails = [];
            if (!empty($request->personnel_id)) {
                $personnelUsers = User::whereIn('id', $request->personnel_id)->get(['id', 'first_name', 'last_name', 'role_name']);
    
                $invalidPersonnel = $personnelUsers->where('role_name', '!=', 'personnel')->pluck('id')->toArray();
                if (!empty($invalidPersonnel)) {
                    AuditLogger::log('Failed Division Creation - Invalid Personnel Role', 'N/A', 'N/A');
                    return response()->json([
                        'isSuccess' => false,
                        'message' => 'One or more selected users are not assigned the "personnel" role.',
                        'invalid_users' => $invalidPersonnel,
                    ], 400);
                }
    
                $alreadyAssignedPersonnel = Division::whereNotNull('personnel_id')
                    ->where(function ($query) use ($request) {
                        foreach ($request->personnel_id as $personnelId) {
                            $query->orWhereRaw("JSON_CONTAINS(personnel_id, ?)", [json_encode($personnelId)]);
                        }
                    })->exists();
    
                if ($alreadyAssignedPersonnel) {
                    AuditLogger::log('Failed Division Creation - Personnel Already Assigned', 'N/A', 'N/A');
                    return response()->json([
                        'isSuccess' => false,
                        'message' => 'One or more selected personnel are already assigned to another division.',
                    ], 400);
                }
    
                $personnelDetails = $personnelUsers->map(function ($person) {
                    return [
                        'id' => $person->id,
                        'first_name' => $person->first_name,
                        'last_name' => $person->last_name,
                    ];
                })->toArray();
            }
    
            // Save division
            $division = Division::create([
                'division_name' => $request->division_name,
                'office_location' => $request->office_location,
                'staff_id' => json_encode(array_column($staffDetails, 'id')),
                'personnel_id' => json_encode(array_column($personnelDetails, 'id')),
            ]);
    
            $response = [
                'isSuccess' => true,
                'message' => 'Division successfully created.',
                'division' => [
                    'id' => $division->id,
                    'division_name' => $division->division_name,
                    'office_location' => $division->office_location,
                    'staff' => $staffDetails,
                    'personnel' => $personnelDetails,
                ],
            ];
    
            $this->logAPICalls('createDivision', $user->email, $request->all(), [$response]);
            AuditLogger::log('Created Division', 'N/A', 'Active');
    
            return response()->json($response, 201);
    
        } catch (ValidationException $v) {
            $response = [
                'isSuccess' => false,
                'message' => 'Invalid input data.',
                'error' => $v->errors(),
            ];
            $this->logAPICalls('createDivision', $user->email ?? 'unknown', $request->all(), [$response]);
            AuditLogger::log('Failed Division Creation - Validation Error', 'N/A', 'N/A');
    
            return response()->json($response, 400);
    
        } catch (Throwable $e) {
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
            $user = auth()->user();
    
            if (!$user) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Unauthorized. Please log in.',
                ], 401);
            }
    
            $division = Division::findOrFail($id);
            $beforeUpdate = $division->getOriginal();
    
            $request->validate([
                'division_name' => 'sometimes|required|string|unique:divisions,division_name,' . $id,
                'office_location' => 'sometimes|required|string',
                'staff_id' => 'nullable|array',
                'staff_id.*' => 'exists:users,id',
                'personnel_id' => 'nullable|array',
                'personnel_id.*' => 'exists:users,id',
            ]);
    
            // ✅ Staff validation
            $staffDetails = [];
            if (!empty($request->staff_id)) {
                $staffUsers = User::whereIn('id', $request->staff_id)->get(['id', 'first_name', 'last_name', 'role_name']);
                $invalidStaff = $staffUsers->where('role_name', '!=', 'staff')->pluck('id')->toArray();
    
                if (!empty($invalidStaff)) {
                    return response()->json([
                        'isSuccess' => false,
                        'message' => 'One or more selected users are not assigned the "staff" role.',
                        'invalid_users' => $invalidStaff,
                    ], 400);
                }
    
                $alreadyAssignedStaff = Division::whereNotNull('staff_id')
                    ->where('id', '!=', $id)
                    ->whereRaw("JSON_CONTAINS(staff_id, ?)", [json_encode($request->staff_id)])
                    ->exists();
    
                if ($alreadyAssignedStaff) {
                    return response()->json([
                        'isSuccess' => false,
                        'message' => 'One or more selected staff members are already assigned to another division.',
                    ], 400);
                }
    
                $staffDetails = $staffUsers->map(function ($staff) {
                    return [
                        'id' => $staff->id,
                        'first_name' => $staff->first_name,
                        'last_name' => $staff->last_name,
                    ];
                })->toArray();
            }
    
            // ✅ Personnel validation
            $personnelDetails = [];
            if (!empty($request->personnel_id)) {
                $personnelUsers = User::whereIn('id', $request->personnel_id)->get(['id', 'first_name', 'last_name', 'role_name']);
                $invalidPersonnel = $personnelUsers->where('role_name', '!=', 'personnel')->pluck('id')->toArray();
    
                if (!empty($invalidPersonnel)) {
                    return response()->json([
                        'isSuccess' => false,
                        'message' => 'One or more selected users are not assigned the "personnel" role.',
                        'invalid_users' => $invalidPersonnel,
                    ], 400);
                }
    
                $alreadyAssignedPersonnel = Division::whereNotNull('personnel_id')
                    ->where('id', '!=', $id)
                    ->whereRaw("JSON_CONTAINS(personnel_id, ?)", [json_encode($request->personnel_id)])
                    ->exists();
    
                if ($alreadyAssignedPersonnel) {
                    return response()->json([
                        'isSuccess' => false,
                        'message' => 'One or more selected personnel are already assigned to another division.',
                    ], 400);
                }
    
                $personnelDetails = $personnelUsers->map(function ($person) {
                    return [
                        'id' => $person->id,
                        'first_name' => $person->first_name,
                        'last_name' => $person->last_name,
                    ];
                })->toArray();
            }
    
            $statusBefore = $division->status;
    
            // ✅ Update division with staff and personnel
            $division->update([
                'division_name' => $request->division_name ?? $division->division_name,
                'office_location' => $request->office_location ?? $division->office_location,
                'staff_id' => json_encode(array_column($staffDetails, 'id') ?? json_decode($division->staff_id, true)),
                'personnel_id' => json_encode(array_column($personnelDetails, 'id') ?? json_decode($division->personnel_id, true)),
            ]);
    
            $afterUpdate = $division->only(['id', 'division_name', 'office_location', 'staff_id', 'personnel_id']);
    
            AuditLogger::log(
                'updateDivision',
                json_encode($beforeUpdate),
                json_encode($afterUpdate)
            );
    
            $response = [
                'isSuccess' => true,
                'message' => 'Division successfully updated.',
                'status_before' => $statusBefore,
                'division' => [
                    'id' => $division->id,
                    'division_name' => $division->division_name,
                    'office_location' => $division->office_location,
                    'staff' => $staffDetails,
                    'personnel' => $personnelDetails,
                ],
            ];
    
            $this->logAPICalls('updateDivision', $user->email, $request->all(), [$response]);
            return response()->json($response, 200);
        } catch (ValidationException $v) {
            $response = [
                'isSuccess' => false,
                'message' => 'Invalid input data.',
                'error' => $v->errors(),
            ];
            $this->logAPICalls('updateDivision', $user->email ?? 'unknown', $request->all(), [$response]);
            return response()->json($response, 400);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to update the Division.',
                'error' => $e->getMessage(),
            ];
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
    public function getDivisions(Request $request)
    {
        try {
            $user = auth()->user();
    
            if (!$user) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Unauthorized. Please log in.',
                ], 401);
            }
    
            $search = strtolower($request->input('search', ''));
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);
    
            // Get all non-archived divisions
            $divisions = Division::where('is_archived', 0)->get();
    
            // Filter and transform divisions
            $filteredDivisions = $divisions->map(function ($division) use ($search) {
                $staffIds = json_decode($division->staff_id, true) ?? [];
                $personnelIds = json_decode($division->personnel_id, true) ?? [];
    
                $staffDetails = User::whereIn('id', $staffIds)
                    ->where('is_archived', 0)
                    ->get(['id', 'first_name', 'last_name', 'email']);
    
                $personnelDetails = User::whereIn('id', $personnelIds)
                    ->where('is_archived', 0)
                    ->get(['id', 'first_name', 'last_name', 'email']);
    
                // Check if any match in staff
                $matchedStaff = $staffDetails->filter(function ($user) use ($search) {
                    return str_contains(strtolower($user->first_name), $search) ||
                           str_contains(strtolower($user->last_name), $search);
                });
    
                // Check if any match in personnel
                $matchedPersonnel = $personnelDetails->filter(function ($user) use ($search) {
                    return str_contains(strtolower($user->first_name), $search) ||
                           str_contains(strtolower($user->last_name), $search);
                });
    
                $divisionMatches = str_contains(strtolower($division->division_name), $search) ||
                                   str_contains(strtolower($division->office_location), $search);
    
                // Only include divisions that match by name/location or staff/personnel
                if ($search && !$divisionMatches && $matchedStaff->isEmpty() && $matchedPersonnel->isEmpty()) {
                    return null;
                }
    
                return [
                    'id' => $division->id,
                    'division_name' => $division->division_name,
                    'office_location' => $division->office_location,
                    'staff' => $matchedStaff->isNotEmpty() ? $matchedStaff->values() : $staffDetails->values(),
                    'personnel' => $matchedPersonnel->isNotEmpty() ? $matchedPersonnel->values() : $personnelDetails->values(),
                    'created_at' => $division->created_at,
                    'updated_at' => $division->updated_at,
                ];
            })->filter()->values(); // Remove nulls
    
            // Manual pagination
            $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
                $filteredDivisions->forPage($page, $perPage),
                $filteredDivisions->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
    
            $response = [
                'isSuccess' => true,
                'message' => 'Divisions retrieved successfully.',
                'divisions' => [
                    'data' => $paginated->items(),
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'total' => $paginated->total(),
                    'per_page' => $paginated->perPage(),
                ],
            ];
    
            $this->logAPICalls('getDivisions', $user->email, $request->all(), [$response]);
    
            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve divisions.',
                'error' => $e->getMessage(),
            ];
    
            $this->logAPICalls('getDivisions', $user->email ?? 'unknown', $request->all(), [$response]);
    
            return response()->json($response, 500);
        }
    }
    
    
    

    public function getDivisionsArchive(Request $request)
    {
        try {
            $user = auth()->user();
    
            if (!$user) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Unauthorized. Please log in.',
                ], 401);
            }
    
            $search = strtolower($request->input('search', ''));
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);
    
            // Get all archived divisions
            $divisions = Division::where('is_archived', 1)->get();
    
            // Filter and transform divisions
            $filteredDivisions = $divisions->map(function ($division) use ($search) {
                $staffIds = json_decode($division->staff_id, true) ?? [];
                $personnelIds = json_decode($division->personnel_id, true) ?? [];
    
                $staffDetails = User::whereIn('id', $staffIds)
                    ->where('is_archived', 0)
                    ->get(['id', 'first_name', 'last_name', 'email']);
    
                $personnelDetails = User::whereIn('id', $personnelIds)
                    ->where('is_archived', 0)
                    ->get(['id', 'first_name', 'last_name', 'email']);
    
                // Check if staff matches search
                $matchedStaff = $staffDetails->filter(function ($user) use ($search) {
                    return str_contains(strtolower($user->first_name), $search) ||
                           str_contains(strtolower($user->last_name), $search);
                });
    
                // Check if personnel matches search
                $matchedPersonnel = $personnelDetails->filter(function ($user) use ($search) {
                    return str_contains(strtolower($user->first_name), $search) ||
                           str_contains(strtolower($user->last_name), $search);
                });
    
                $divisionMatches = str_contains(strtolower($division->division_name), $search) ||
                                   str_contains(strtolower($division->office_location), $search);
    
                if ($search && !$divisionMatches && $matchedStaff->isEmpty() && $matchedPersonnel->isEmpty()) {
                    return null; // Exclude this division if no match found
                }
    
                return [
                    'id' => $division->id,
                    'division_name' => $division->division_name,
                    'office_location' => $division->office_location,
                    'staff' => $matchedStaff->isNotEmpty() ? $matchedStaff->values() : $staffDetails->values(),
                    'personnel' => $matchedPersonnel->isNotEmpty() ? $matchedPersonnel->values() : $personnelDetails->values(),
                    'created_at' => $division->created_at,
                    'updated_at' => $division->updated_at,
                ];
            })->filter()->values(); // Remove nulls
    
            // Manual pagination
            $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
                $filteredDivisions->forPage($page, $perPage),
                $filteredDivisions->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
    
            $response = [
                'isSuccess' => true,
                'message' => 'Archived divisions retrieved successfully.',
                'divisions' => [
                    'data' => $paginated->items(),
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'total' => $paginated->total(),
                    'per_page' => $paginated->perPage(),
                ],
            ];
    
            $this->logAPICalls('getDivisionsArchive', $user->email, $request->all(), [$response]);
    
            return response()->json($response, 200);
    
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve archived divisions.',
                'error' => $e->getMessage(),
            ];
    
            $this->logAPICalls('getDivisionsArchive', $user->email ?? 'unknown', $request->all(), [$response]);
    
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
        $division->update([
            'is_archived' => 1,
            'staff_id' => null,
            'personnel_id' => null
        ]);

        // Capture the updated is_archived value after update
        $afterUpdate = ['is_archived' => $division->is_archived];

        // Audit log only for the is_archived field
        AuditLogger::log(
            'deleteDivision',
            json_encode($beforeUpdate),
            'Deleted'
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

