<?php

namespace App\Http\Controllers;

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
                'office_type' => ['required', 'string', 'in: Academic, non Acadmic'],
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
            return response()->json($response, 201);
        } catch (ValidationException $v) {
            $response = [
                'isSuccess' => false,
                'message' => "Invalid input data.",
                'error' => $v->errors()
            ];
            $this->logAPICalls('createOffice', "", $request->all(), [$response]);
            return response()->json($response, 422);
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
            $collegeOffice = Office::findOrFail($id);

            $request->validate([
                'officename' => ['sometimes', 'required', 'string'],
                'acronym' => ['sometimes', 'required', 'string'],
                'office_type' => ['sometimes', 'required', 'string'],
            ]);

            $collegeOffice->update([
                'officename' => $request->officename,
                'acronym' => $request->acronym,
                'office_type' => $request->office_type,
            ]);

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
            $perPage = $request->input('per_page', 10);
            $searchTerm = $request->input('search', null);

            // Create query to fetch active college offices
            $query = Office::where('is_archived', 'A');

            // Add search condition if search term is provided
            if ($searchTerm) {
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('officename', 'like', "%{$searchTerm}%")
                        ->orWhere('abbreviation', 'like', "%{$searchTerm}%");
                });
            }

            // Paginate the result
            $collegeOffices = $query->paginate($perPage);

            // Prepare the response
            $response = [
                'isSuccess' => true,
                'message' => 'Offices list:',
                'office' => $collegeOffices,
                'pagination' => [
                    'total' => $collegeOffices->total(),
                    'per_page' => $collegeOffices->perPage(),
                    'current_page' => $collegeOffices->currentPage(),
                    'last_page' => $collegeOffices->lastPage(),
                    'next_page_url' => $collegeOffices->nextPageUrl(),
                    'prev_page_url' => $collegeOffices->previousPageUrl(),
                ]
            ];

            // Log API calls
            $this->logAPICalls('getOffices', "", [], [$response]);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve College Offices.',
                'error' => $e->getMessage()
            ];

            // Log API calls
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
            $collegeOffice = Office::find($request->id);

            $collegeOffice->update(['isarchive' => "I"]);

            $response = [
                'isSuccess' => true,
                'message' => "Office successfully deleted."
            ];
            $this->logAPICalls('deleteOffice', $collegeOffice->id, [], [$response]);
            return response()->json($response, 204);
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
