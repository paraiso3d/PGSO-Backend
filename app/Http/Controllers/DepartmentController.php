<?php

namespace App\Http\Controllers;

use App\Models\User;
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
                'department_name' => ['required', 'string'],
                'acronym'  => ['required', 'string'],
                'description' => ['required', 'string'],
                
            ]);

            $collegeOffice = Department::create([
                'department_name' => $request->department_name,
                'acronym' =>  $request->acronym,
                'description' => $request->description,
            ]);

            $response = [
                'isSuccess' => true,
                'message' => "Department successfully created.",
                'office' => $collegeOffice
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
            return response()->json($response, 500);
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
    public function getOffices(Request $request)
    {
        try {
            
            $search = $request->input('search');
            $perPage = $request->input('per_page', 10);

            // Initialize query
            $query = Department::select('id', 'department_name', 'description')
                ->where('is_archived', '0')
                ->when($search, function ($query, $search) {
                    return $query->where(function ($q) use ($search) {
                        $q->where('department_name', 'LIKE', '%' . $search . '%');
                    });
                });

            $result = $query->paginate($perPage);

            // Check if result is empty
            if ($result->isEmpty()) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'No active Offices found matching the criteria',
                ];
                return response()->json($response, 500);
            }

            // Format the paginated results
            $formattedOffices = $result->getCollection()->transform(function ($office) {
                return [
                    'id' => $office->id,
                    'department_name' => $office->department_name,
                    'description' => $office->description,
                ];
            });

            // Prepare response
            $response = [
                'isSuccess' => true,
                'message' => 'Offices list retrieved successfully.',
                'offices' => $formattedOffices,
                'pagination' => [
                    'total' => $result->total(),
                    'per_page' => $result->perPage(),
                    'current_page' => $result->currentPage(),
                    'last_page' => $result->lastPage(),
                ],
            ];

            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve offices list.',
                'error' => $e->getMessage(),
            ];

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
