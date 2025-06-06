<?php

namespace App\Http\Controllers;

use Auth;
use App\Helpers\AuditLogger;
use App\Models\User;
use App\Models\Division;
use App\Models\Department;
use App\Models\ApiLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Throwable;

class DepartmentController extends Controller
{
    /**
     * Create a new college office.
     */
    public function createOffice(Request $request)
    {
        try {
            $user = Auth::user();
    
            if (!$user) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Unauthorized. Please log in.',
                ], 401);
            }
    
            $request->validate([
                'department_name' => ['required', 'string', 'unique:departments,department_name'],
                'head_id' => ['required', 'integer', 'exists:users,id'],
            ]);
    
            $headUser = User::where('id', $request->head_id)
                ->where('role_name', 'head')
                ->first();
    
            if (!$headUser) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'The selected user is invalid.',
                ], 422);
            }
    
            $existingHead = Department::where('head_id', $request->head_id)->first();
            if ($existingHead) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'The selected head is already assigned to another department.',
                ], 422);
            }
    
            // Generate acronym from department name
            $words = preg_split("/[\s,]+/", $request->department_name);
            $acronym = strtoupper(implode('', array_map(fn($word) => $word[0], $words)));
    
            // Create the new department with acronym
            $collegeOffice = Department::create([
                'department_name' => $request->department_name,
                'head_id' => $request->head_id,
                'acronym' => $acronym,
            ]);
    
    
            AuditLogger::log('Created Office', 'N/A', 'Created Department: ' . $collegeOffice->department_name);
    
            return response()->json([
                'isSuccess' => true,
                'message' => "Department successfully created.",
                'department' => $collegeOffice,
                'head' => $headUser,
            ], 200);
    
        } catch (ValidationException $v) {
            return response()->json([
                'isSuccess' => false,
                'message' => "Invalid input data.",
                'error' => $v->errors()
            ], 400);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => "Failed to create the Office.",
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    
            


    
    /**
     * Update an existing college office.
     */

     public function updateOffice(Request $request, $id)
     {
         try {
             $user = auth()->user();
     
             if (!$user) {
                 return response()->json([
                     'isSuccess' => false,
                     'message' => 'Unauthorized. Please log in.',
                 ], 401);
             }
     
             $collegeOffice = Department::findOrFail($id);
     
             // Validate inputs
             $request->validate([
                 'department_name' => [
                     'sometimes', 'string',
                     Rule::unique('departments', 'department_name')->ignore($id)
                 ],
                 'acronym' => [
                     'sometimes', 'string',
                     Rule::unique('departments', 'acronym')->ignore($id)
                 ],
                 'head_id' => ['sometimes', 'integer', 'exists:users,id'],
             ]);
     
             if ($request->filled('head_id')) {
                 $headUser = User::where('id', $request->head_id)
                     ->where('role_name', 'head')
                     ->first();
     
                 if (!$headUser) {
                     return response()->json([
                         'isSuccess' => false,
                         'message' => 'The selected head is invalid or not assigned the role "head".',
                     ], 422);
                 }
     
                 $isHeadAssigned = Department::where('id', '!=', $id)
                     ->where('head_id', $request->head_id)
                     ->exists();
     
                 if ($isHeadAssigned) {
                     return response()->json([
                         'isSuccess' => false,
                         'message' => 'The selected head is already assigned to another department.',
                     ], 422);
                 }
             }
     
     
             $oldData = $collegeOffice->toArray();
     
             // Handle department_name and acronym
             $departmentName = $request->department_name ?? $collegeOffice->department_name;
     
             if ($departmentName !== $collegeOffice->department_name) {
                 $acronym = $this->generateAcronym($departmentName);
             } else {
                 $acronym = $request->acronym ?? $collegeOffice->acronym;
             }
     
             // Update data
             $updateData = array_filter([
                 'department_name' => $departmentName,
                 'acronym' => $acronym,
                 'head_id' => $request->head_id,
             ], fn($value) => !is_null($value));
     
             $collegeOffice->update($updateData);
     
     
             $response = [
                 'isSuccess' => true,
                 'message' => "Office successfully updated.",
                 'department' => $collegeOffice,
             ];
     
             $this->logAPICalls('updateOffice', $user->email, $request->all(), [$response]);
     
             AuditLogger::log(
                 'Updated Office',
                 json_encode($oldData),
                 json_encode($collegeOffice->toArray()),
                 'Active'
             );
     
             return response()->json($response, 200);
     
         } catch (ValidationException $v) {
             $response = [
                 'isSuccess' => false,
                 'message' => "Invalid input data.",
                 'error' => $v->errors(),
             ];
     
             $this->logAPICalls('updateOffice', $user->email ?? 'unknown', $request->all(), [$response]);
     
             return response()->json($response, 422);
     
         } catch (Throwable $e) {
             $response = [
                 'isSuccess' => false,
                 'message' => "Failed to update the Office.",
                 'error' => $e->getMessage(),
             ];
     
             $this->logAPICalls('updateOffice', $user->email ?? 'unknown', $request->all(), [$response]);
     
             return response()->json($response, 500);
         }
     }
     
     
     // Helper method to generate acronym
     protected function generateAcronym($departmentName)
     {
         $words = explode(' ', $departmentName);
         $acronym = '';
         
         foreach ($words as $word) {
             if (strlen($word) > 0) {
                 $acronym .= strtoupper($word[0]);
             }
         }
         
         return $acronym;
     }
     
     

     
    /**
     * Get all college offices.
     */
    public function getOffices(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);
            $search = $request->input('search');
            $divisionId = $request->input('division_id');
    
            // Query for departments with joins as before
            $query = Department::where('departments.is_archived', 0)
                ->leftJoin('users as heads', 'departments.head_id', '=', 'heads.id')
                ->leftJoin('divisions', 'departments.id', '=', 'divisions.department_id') // Use direct join on department_id
                ->select(
                    'departments.id',
                    'departments.department_name',
                    'departments.acronym',
                    'departments.head_id',
                    'departments.created_at',
                    'departments.updated_at',
                    'heads.first_name as head_first_name',
                    'heads.last_name as head_last_name',
                    'heads.email as head_email',
                    'divisions.id as division_id',
                    'divisions.division_name',
                    'divisions.staff_id'
                );
    
            // Apply search filter
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('departments.department_name', 'like', "%$search%")
                      ->orWhere('departments.acronym', 'like', "%$search%")
                      ->orWhere('heads.first_name', 'like', "%$search%")
                      ->orWhere('heads.last_name', 'like', "%$search%");
                });
            }
    
            // Filter by division_id if provided
            if ($divisionId) {
                $query->where('divisions.id', $divisionId);
            }
    
            // Pagination
            $paginatedDepartments = $query->paginate($perPage, ['*'], 'page', $page);
    
            // Group by department ID and merge divisions and staff
            $groupedDepartments = collect($paginatedDepartments->items())->groupBy('id')->map(function ($departmentGroup) {
                // Assume the first record is representative of the department (all records will have the same basic department details)
                $department = $departmentGroup->first();
    
                // Merge all divisions
                $divisions = $departmentGroup->flatMap(function ($department) {
                    return $department->divisions;
                })->unique('id'); // Remove duplicates
    
                // Merge all staff (staff_id references should be aggregated)
                $staffIds = $departmentGroup->flatMap(function ($department) {
                    return json_decode($department->staff_id, true);
                })->unique();
    
                // Fetch staff data
                $staff = User::whereIn('id', $staffIds)
                    ->where('is_archived', 0)
                    ->get(['id', 'first_name', 'last_name', 'email']);
    
                return [
                    'id' => $department->id,
                    'department_name' => $department->department_name,
                    'acronym' => $department->acronym,
                    'head' => [
                        'id' => $department->head_id,
                        'first_name' => $department->head_first_name,
                        'last_name' => $department->head_last_name,
                    ],
                    'divisions' => $divisions,
                    'staff' => $staff,
                    'created_at' => $department->created_at,
                    'updated_at' => $department->updated_at
                ];
            });
    
            $response = [
                'isSuccess' => true,
                'message' => "Departments retrieved successfully.",
                'departments' => $groupedDepartments->values(),
                'pagination' => [
                    'current_page' => $paginatedDepartments->currentPage(),
                    'last_page' => $paginatedDepartments->lastPage(),
                    'per_page' => $paginatedDepartments->perPage(),
                    'total' => $paginatedDepartments->total()
                ]
            ];
    
            $this->logAPICalls('getOffices', "", $request->all(), [$response]);
    
            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to retrieve departments.",
                'error' => $e->getMessage()
            ];
    
            $this->logAPICalls('getOffices', "", $request->all(), [$response]);
    
            return response()->json($response, 500);
        }
    }
    
    
    
    

    

    public function getStaffsPersonnelForHead()
{
    try {
        $authId = auth()->id();

        // Step 1: Get the department where this user is the head
        $department = Department::where('head_id', $authId)->first();

        if (!$department) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'No department found for this user.'
            ], 404);
        }

        // Step 2: Get all divisions under this department
        $divisions = Division::where('department_id', $department->id)
            ->where('is_archived', 0)
            ->get(['id', 'division_name', 'office_location', 'staff_id']);

        // Step 3: Map staff to their divisions
        $staffDivisionMap = [];
        foreach ($divisions as $division) {
            $staffIds = json_decode($division->staff_id, true) ?? [];
            foreach ($staffIds as $staffId) {
                $staffDivisionMap[$staffId] = [
                    'division_id' => $division->id,
                    'division_name' => $division->division_name,
                    'office_location' => $division->office_location,
                ];
            }
        }

        // Step 4: Get the user info for each staff
        $staffIds = array_keys($staffDivisionMap);
        $staff = User::whereIn('id', $staffIds)
            ->where('is_archived', 0)
            ->get(['id', 'first_name', 'last_name', 'number', 'email'])
            ->map(function ($user) use ($staffDivisionMap) {
                return [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'number' => $user->number,
                    'email' => $user->email,
                    'division' => $staffDivisionMap[$user->id] ?? null,
                ];
            });

        return response()->json([
            'isSuccess' => true,
            'message' => 'Staff and personnel fetched successfully.',
            'department' => [
                'id' => $department->id,
                'department_name' => $department->department_name,
            ],
            'staff' => $staff
        ], 200);

    } catch (Throwable $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Error fetching personnel.',
            'error' => $e->getMessage()
        ], 500);
    }
}

    public function getDivisionsForHead()
    {
        try {
            $authId = auth()->id();
    
            // Find the department for the authenticated head
            $department = Department::where('head_id', $authId)->first();
    
            if (!$department) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'No department found for this user.'
                ], 404);
            }
    
            // Fetch divisions tied to this department via department_id
            $divisions = Division::where('department_id', $department->id)
                ->where('is_archived', 0)
                ->get(['id', 'division_name', 'office_location']);
    
            return response()->json([
                'isSuccess' => true,
                'message' => 'Divisions fetched successfully.',
                'department' => [
                    'id' => $department->id,
                    'department_name' => $department->department_name,
                ],
                'divisions' => $divisions
            ], 200);
    
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Error fetching divisions.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getDropdownDepartments(Request $request)
{
    try {
        $departments = DB::table('departments')
            ->select('id', 'department_name')
            ->where('is_archived', '0')
            ->get();

        $response = [
            'isSuccess' => true,
            'message' => 'Department dropdown options retrieved successfully.',
            'department' => $departments,
        ];

        $this->logAPICalls('getDropdownDepartments', "", [], [$response]);

        return response()->json($response, 200);
    } catch (\Throwable $e) {
        $response = [
            'isSuccess' => false,
            'message' => 'Failed to retrieve department dropdown options.',
            'error' => $e->getMessage(),
        ];
        $this->logAPICalls('getDropdownDepartments', "", [], [$response]);
        return response()->json($response, 500);
    }
}

    

public function getdropdowndivisions()
{
    try {
        // Get the authenticated user's ID
        $authId = auth()->id();

        // Find the department where the authenticated user is the head
        $department = Department::where('head_id', $authId)->first();

        if (!$department) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'No department found for this user.'
            ], 404);
        }

        // Fetch divisions belonging to this department
        $divisions = Division::where('department_id', $department->id)
            ->where('is_archived', 0)
            ->get(['id', 'division_name']);

        return response()->json([
            'isSuccess' => true,
            'message' => 'Divisions for head fetched successfully.',
            'divisions' => $divisions
        ], 200);
    } catch (Throwable $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Error fetching divisions.',
            'error' => $e->getMessage()
        ], 500);
    }
}


    


    
    


    public function getOfficesArchive(Request $request)
    {
        try {
            $user = auth()->user();
    
            if (!$user) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Unauthorized. Please log in.',
                ], 401);
            }
    
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);
            $search = $request->input('search');
            $filterDivisionId = $request->input('division_id'); // For division filtering
    
            // Base query with join to users table for head search
            $query = Department::where('departments.is_archived', 1)
                ->leftJoin('users as heads', 'departments.head_id', '=', 'heads.id')
                ->select('departments.*', 'heads.first_name as head_first_name', 'heads.last_name as head_last_name');
    
            // Apply search if present
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('departments.department_name', 'like', "%$search%")
                        ->orWhere('departments.acronym', 'like', "%$search%")
                        ->orWhere('heads.first_name', 'like', "%$search%")
                        ->orWhere('heads.last_name', 'like', "%$search%");
                });
            }
    
            // Filter by division_id if provided
            if ($filterDivisionId) {
                $query->whereRaw("JSON_CONTAINS(departments.division_id, '\"$filterDivisionId\"')");
            }
    
            // Paginate the filtered + searched departments
            $paginatedDepartments = $query->paginate($perPage, ['*'], 'page', $page);
    
            // Map the results
            $departments = $paginatedDepartments->getCollection()->map(function ($department) {
                $divisionIds = json_decode($department->division_id, true) ?? [];
    
                $divisions = !empty($divisionIds)
                    ? Division::whereIn('id', $divisionIds)
                        ->where('is_archived', 0)
                        ->get(['id', 'division_name', 'staff_id'])
                    : collect();
    
                $staffIds = $divisions->flatMap(function ($div) {
                    $ids = json_decode($div->staff_id, true);
                    return is_array($ids) ? $ids : [];
                })->filter()->unique();
    
                $staff = User::whereIn('id', $staffIds)
                    ->where('is_archived', 0)
                    ->get(['id', 'first_name', 'last_name', 'email']);
    
                $head = null;
                if ($department->head_id) {
                    $head = [
                        'id' => $department->head_id,
                        'first_name' => $department->head_first_name,
                        'last_name' => $department->head_last_name,
                    ];
                }
    
                return [
                    'id' => $department->id,
                    'department_name' => $department->department_name,
                    'acronym' => $department->acronym,
                    'divisions' => $divisions,
                    'staff' => $staff,
                    'head' => $head,
                    'created_at' => $department->created_at,
                    'updated_at' => $department->updated_at
                ];
            });
    
            $paginatedDepartments->setCollection($departments);
    
            $response = [
                'isSuccess' => true,
                'message' => "Departments archive retrieved successfully.",
                'departments' => [
                    'data' => $departments,
                    'current_page' => $paginatedDepartments->currentPage(),
                    'last_page' => $paginatedDepartments->lastPage(),
                    'total' => $paginatedDepartments->total(),
                    'per_page' => $paginatedDepartments->perPage(),
                ],
            ];
    
            $this->logAPICalls('getOfficesArchive', $user->email, $request->all(), [$response]);
    
            return response()->json($response, 200);
    
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to retrieve departments.",
                'error' => $e->getMessage()
            ];
    
            $this->logAPICalls('getOfficesArchive', $user->email ?? 'unknown', $request->all(), [$response]);
    
            return response()->json($response, 500);
        }
    }
    
    
    /**
     * Delete a college office.
     */
  
     public function deleteOffice(Request $request)
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

        // Find the office
        $collegeOffice = Department::find($request->id);

        if (!$collegeOffice) {
            return response()->json([
                'isSuccess' => false,
                'message' => "Department not found.",
            ], 404);
        }

        // Store old data for logging
        $oldData = $collegeOffice->toArray();

        // Soft delete (archive the office) and nullify head_id and division_id
        $collegeOffice->update([
            'is_archived' => 1,
            'head_id' => null,
            'division_id' => null
        ]);

        // Log API call and audit event
        $this->logAPICalls('deleteOffice', $user->email, [], [['isSuccess' => true, 'message' => "Office successfully deleted."]]);

        AuditLogger::log(
            'Deleted Office',
            json_encode($oldData), // Log old data before deletion
            'Deleted',
            'Inactive'
        );

        return response()->json([
            'isSuccess' => true,
            'message' => "Department successfully deleted."
        ], 200);
    } catch (Throwable $e) {
        $response = [
            'isSuccess' => false,
            'message' => "Failed to delete the Department.",
            'error' => $e->getMessage()
        ];

        $this->logAPICalls('deleteOffice', $user->email ?? 'unknown', [], [$response]);

        return response()->json($response, 500);
    }
}


    public function restoreOffice(Request $request)
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
    
            // Find the office
            $collegeOffice = Department::find($request->id);
    
            if (!$collegeOffice) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => "Department not found.",
                ], 404);
            }
    
            // Store old data for logging
            $oldData = $collegeOffice->toArray();
    
            // Soft delete (archive the office)
            $collegeOffice->update(['is_archived' => "0"]);
    
            // Log API call and audit event
            $this->logAPICalls('restoreOffice', $user->email, [], [['isSuccess' => true, 'message' => "Office successfully deleted."]]);
            return response()->json([
                'isSuccess' => true,
                'message' => "Department successfully restored."
            ], 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to restore the Department.",
                'error' => $e->getMessage()
            ];
    
            $this->logAPICalls('restoreOffice', $user->email ?? 'unknown', [], [$response]);
    
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
            // Handle logging error if necessary
            return false;
        }
        return true;
    }


}
