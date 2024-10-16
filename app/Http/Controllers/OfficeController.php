<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Office;
use App\Models\ApiLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class OfficeController extends Controller
{
    /**
     * Create a new college office.
     */
    public function createOffice(Request $request)
    {
        try {
            $request->validate([
                'officename' => ['required', 'string'],
                'acronym' => ['required', 'string'],
                'office_type' => ['required', 'string', 'in:Academic,Non Academic'],
            ]);

            $collegeOffice = Office::create([
                'officename' => $request->officename,
                'acronym' => $request->acronym,
                'office_type' => $request->office_type,
            ]);

            $response = [
                'isSuccess' => true,
                'message' => "Office successfully created.",
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
            $collegeOffice = Office::findOrFail($id);

            // Validate the request data
            $request->validate([
                'officename' => ['required', 'sometimes', 'string'],
                'acronym' => ['required', 'sometimes', 'string'],
                'office_type' => ['sometimes', 'string'],
            ]);

            // Get the old acronym for cascade update (if needed)
            $oldAcronym = $collegeOffice->acronym;

            // Update the office
            $collegeOffice->update(array_filter([
                'officename' => $request->officename,
                'acronym' => $request->acronym,
                'office_type' => $request->office_type,
            ]));

            // Cascade update the users or other related tables
            if ($oldAcronym !== $request->acronym) {
                User::where('office_id', $id)
                    ->update(['office' => $request->acronym]);  // Ensure 'office' is the correct field
            }

            // Prepare the response
            $response = [
                'isSuccess' => true,
                'message' => "Office successfully updated.",
                'office' => $collegeOffice
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
            // Validate that 'paginate' is provided in the request
            $validate = $request->validate([
                'paginate' => 'required'
            ]);
    
            // Get search term and pagination settings from the request
            $searchTerm = $request->input('search', null);
            $perPage = $request->input('per_page', 10);
    
            // Check if pagination is enabled
            if ($validate['paginate'] == 0) {
                // Fetch all offices without pagination
                $query = Office::select('id', 'office_name', 'acronym', 'office_type', 'is_archived')
                    ->where('is_archived', 'A')
                    ->when($searchTerm, function ($query, $searchTerm) {
                        return $query->where(function ($q) use ($searchTerm) {
                            $q->where('officename', 'like', '%' . $searchTerm . '%')
                                ->orWhere('abbreviation', 'like', '%' . $searchTerm . '%');
                        });
                    })
                    ->get();
    
                // Check if query is empty
                if ($query->isEmpty()) {
                    $response = [
                        'isSuccess' => false,
                        'message' => 'No active Offices found matching the criteria',
                    ];
                    $this->logAPICalls('getOffices', "", $request->all(), $response);
                    return response()->json($response, 500);
                }
    
                // Prepare response without pagination
                $response = [
                    'isSuccess' => true,
                    'message' => 'Offices list retrieved successfully.',
                    'office' => $query
                ];
            } else {
                // Fetch paginated offices
                $query = Office::select('id', 'office_name', 'acronym', 'office_type', 'is_archived')
                    ->where('is_archived', 'A')
                    ->when($searchTerm, function ($query, $searchTerm) {
                        return $query->where(function ($q) use ($searchTerm) {
                            $q->where('officename', 'like', '%' . $searchTerm . '%')
                                ->orWhere('abbreviation', 'like', '%' . $searchTerm . '%');
                        });
                    })
                    ->paginate($perPage);
    
                // Check if query is empty
                if ($query->isEmpty()) {
                    $response = [
                        'isSuccess' => false,
                        'message' => 'No active Offices found matching the criteria',
                    ];
                    $this->logAPICalls('getOffices', "", $request->all(), $response);
                    return response()->json($response, 500);
                }
    
                // Prepare response with pagination
                $response = [
                    'isSuccess' => true,
                    'message' => 'Offices list retrieved successfully.',
                    'office' => $query,
                    'pagination' => [
                        'total' => $query->total(),
                        'per_page' => $query->perPage(),
                        'current_page' => $query->currentPage(),
                        'last_page' => $query->lastPage(),
                    ]
                ];
            }
    
            // Log API call
            $this->logAPICalls('getOffices', "", $request->all(), $response);
            return response()->json($response, 200);
    
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve Offices list.',
                'error' => $e->getMessage()
            ];
    
            // Log API call
            $this->logAPICalls('getOffices', "", $request->all(), $response);
            return response()->json($response, 500);
        }
    }
    
    /**
     * Delete a college office.
     */
    public function deleteOffice(Request $request)
    {
        try {
            $collegeOffice = Office::find($request->id);

            $collegeOffice->update(['is_archived' => "I"]);

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

    // public function getDropdownOptionsOffices(Request $request)
    // {

    // try {
    //     // Fetch distinct office types that are either 'Academic' or 'Non-Academic'
    //     $offices = Office::selectRaw('MIN(id) as id, office_type')
    //     ->whereIn('office_type', ['Academic', 'Non-Academic'])
    //     ->where('is_archived', 'A')
    //     ->groupBy('office_type')
    //     ->get();

    //         // Build the response
    //         $response = [
    //             'isSuccess' => true,
    //             'message' => 'Dropdown data retrieved successfully.',
    //             'offices' => $offices,
    //         ];

    //         // Log the API call
    //         $this->logAPICalls('getDropdownOptionsOffice', "", $request->all(), $response);

    //         return response()->json($response, 200);
    //     } catch (Throwable $e) {
    //         // Handle the error response
    //         $response = [
    //             'isSuccess' => false,
    //             'message' => 'Failed to retrieve dropdown data.',
    //             'error' => $e->getMessage()
    //         ];

    //         // Log the error
    //         $this->logAPICalls('getDropdownOptionsOffice', "", $request->all(), $response);

    //         return response()->json($response, 500);
    //     }
    // }

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
