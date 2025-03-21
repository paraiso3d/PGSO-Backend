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
        // Ensure user is authenticated
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Unauthorized. Please log in.',
            ], 401);
        }

        $request->validate([
            'department_name' => ['required', 'string', 'unique:departments,department_name'],
            'acronym' => ['required', 'string'],
            'division_id' => ['required', 'array'],
            'division_id.*' => ['integer']
        ]);

        $collegeOffice = Department::create([
            'department_name' => $request->department_name,
            'acronym' => $request->acronym,
            'division_id' => json_encode($request->division_id),
        ]);

        $divisions = Division::whereIn('id', $request->division_id)->get(['id', 'division_name']);

        AuditLogger::log('Created Office', 'N/A', 'Created Department: '.$collegeOffice->department_name);

        return response()->json([
            'isSuccess' => true,
            'message' => "Department successfully created.",
            'department' => $collegeOffice,
            'divisions' => $divisions
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
            // Get authenticated user
            $user = auth()->user();
    
            if (!$user) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Unauthorized. Please log in.',
                ], 401);
            }
    
            // Find the office
            $collegeOffice = Department::findOrFail($id);
    
            // Validate the request data
            $request->validate([
                'department_name' => [
                    'sometimes', 'string', 
                    Rule::unique('departments', 'department_name')->ignore($id) // Ensure uniqueness except for the current record
                ],
                'acronym' => ['sometimes', 'string'],
                'division_id' => ['sometimes', 'array'], // Expect an array of division IDs
                'division_id.*' => ['integer'], // Each element should be an integer
            ]);
    
            // Store the old data before update
            $oldData = $collegeOffice->toArray();
    
            // Update the office
            $collegeOffice->update(array_filter([
                'department_name' => $request->department_name,
                'acronym' => $request->acronym,
                'division_id' => $request->has('division_id') ? json_encode($request->division_id) : $collegeOffice->division_id,
            ]));
    
            // Fetch updated divisions
            $divisions = $request->has('division_id')
                ? Division::whereIn('id', $request->division_id)->get(['id', 'division_name'])
                : Division::whereIn('id', json_decode($collegeOffice->division_id, true))->get(['id', 'division_name']);
    
            // Prepare the response
            $response = [
                'isSuccess' => true,
                'message' => "Office successfully updated.",
                'department' => $collegeOffice,
                'divisions' => $divisions,
            ];
    
            // Log API call and audit event
            $this->logAPICalls('updateOffice', $user->email, $request->all(), [$response]);
    
            AuditLogger::log(
                'Updated Office', 
                json_encode($oldData), // Log the old data before the update
                json_encode($collegeOffice->toArray()), // Log the new data after the update
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
            AuditLogger::log('Failed Office Update - Validation Error', 'N/A', 'N/A');
    
            return response()->json($response, 422);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to update the Office.",
                'error' => $e->getMessage(),
            ];
    
            $this->logAPICalls('updateOffice', $user->email ?? 'unknown', $request->all(), [$response]);
            AuditLogger::log('Error Updating Office', 'N/A', 'N/A');
    
            return response()->json($response, 500);
        }
    }
    

     
    /**
     * Get all college offices.
     */
    public function getOffices()
{
    try {
        // Retrieve all departments that are not archived
        $departments = Department::where('is_archived', 0)->get()->map(function ($department) {
            // Decode division_id JSON, default to an empty array if null or invalid
            $divisionIds = json_decode($department->division_id, true) ?? [];

            // Fetch related division names only if $divisionIds is not empty
            $divisions = !empty($divisionIds)
                ? Division::whereIn('id', $divisionIds)->get(['id', 'division_name'])
                : collect();

            // Format the response for each department
            return [
                'id' => $department->id,
                'department_name' => $department->department_name,
                'acronym' => $department->acronym,
                'divisions' => $divisions,
                'created_at' => $department->created_at,
                'updated_at' => $department->updated_at
            ];
        });

        $response = [
            'isSuccess' => true,
            'message' => "Departments retrieved successfully.",
            'departments' => $departments
        ];

        // Log the API call
        $this->logAPICalls('getOffices', "", [], [$response]);

        return response()->json($response, 200);
    } catch (Throwable $e) {
        $response = [
            'isSuccess' => false,
            'message' => "Failed to retrieve departments.",
            'error' => $e->getMessage()
        ];

        // Log the API call with the error
        $this->logAPICalls('getOffices', "", [], [$response]);

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
                    'message' => "Office not found.",
                ], 404);
            }
    
            // Store old data for logging
            $oldData = $collegeOffice->toArray();
    
            // Soft delete (archive the office)
            $collegeOffice->update(['is_archived' => "1"]);
    
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
                'message' => "Office successfully deleted."
            ], 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to delete the Office.",
                'error' => $e->getMessage()
            ];
    
            $this->logAPICalls('deleteOffice', $user->email ?? 'unknown', [], [$response]);
            AuditLogger::log('Error Deleting Office', 'N/A', 'N/A', 'Error');
    
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
