<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Division;
use App\Models\Department;
use App\Models\ApiLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class DepartmentController extends Controller
{
    /**
     * Create a new college office.
     */
    public function createOffice(Request $request)
    {
        try {
            $request->validate([
                'department_name' => ['required', 'string', 'unique:departments,department_name'],
                'acronym' => ['required', 'string'],
                'division_id' => ['required', 'array'], // Expect an array of division IDs
                'division_id.*' => ['integer'] // Each element in the array should be an integer
            ]);
    
            $collegeOffice = Department::create([
                'department_name' => $request->department_name,
                'acronym' => $request->acronym,
                'division_id' => json_encode($request->division_id), // Store as JSON
            ]);
    
            $divisions = Division::whereIn('id', $request->division_id)->get(['id', 'division_name']);
    
            $response = [
                'isSuccess' => true,
                'message' => "Department successfully created.",
                'department' => $collegeOffice,
                'divisions' => $divisions
            ];
    
            $this->logAPICalls('createOffice', $collegeOffice->id, $request->all(), [$response]);
    
            return response()->json($response, 200);
        } catch (ValidationException $v) {
            $response = [
                'isSuccess' => false,
                'message' => "Invalid input data.",
                'error' => $v->errors()
            ];
            $this->logAPICalls('createOffice', "", $request->all(), [$response]);
            return response()->json($response, 400);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to create the Office.",
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('createOffice', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
    }
    
    
    
    /**
     * Update an existing college office.
     */

    public function updateOffice(Request $request, $id)
    {
        try {
            // Find the office
            $collegeOffice = Department::findOrFail($id);

            // Validate the request datas
            $request->validate([
                'department_name' => ['sometimes', 'string'],
                'description' => ['sometimes', 'string'],
            ]);

            // Get the old acronym for cascade update (if needed)
            $oldAcronym = $collegeOffice->acronym;

            // Update the office
            $collegeOffice->update(array_filter([
                'department_name' => $request->department_name,
                'description' => $request->description,
            ]));

            // Prepare the response
            $response = [
                'isSuccess' => true,
                'message' => "Office successfully updated.",
                'department' => $collegeOffice
            ];
            $this->logAPICalls('updateOffice', $id, $request->all(), [$response]);
            return response()->json($response, 200);
        } catch (ValidationException $v) {
            $response = [
                'isSuccess' => false,
                'message' => "Invalid input data.",
                'error' => $v->errors()
            ];
            $this->logAPICalls('updateOffice', "", $request->all(), [$response]);
            return response()->json($response, 422);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to update the College Office.",
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('updateOffice', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
    }

    /**
     * Get all college offices.
     */
    public function getOffices()
    {
        try {
            // Retrieve all departments
            $departments = Department::all()->map(function ($department) {
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
                    'divisions' => $divisions
                ];
            });
    
            $response = [
                'isSuccess' => true,
                'message' => "Departments retrieved successfully.",
                'departments' => $departments
            ];
    
            // Log the API call
            $this->logAPICalls('getOffices',  "", [], [$response]);
    
            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to retrieve departments.",
                'error' => $e->getMessage()
            ];
    
            // Log the API call with the error
            $this->logAPICalls('getOffices',  "", [], [$response]);
    
            return response()->json($response, 500);
        }
    }
    


    /**
     * Delete a college office.
     */
    public function deleteOffice(Request $request)
    {
        try {
            $collegeOffice = Department::find($request->id);

            $collegeOffice->update(['is_archived' => "1"]);

            $response = [
                'isSuccess' => true,
                'message' => "Office successfully deleted."
            ];
            $this->logAPICalls('deleteOffice', $collegeOffice->id, [], [$response]);
            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to delete the Office.",
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('deleteOffice', "", [], [$response]);
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
