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
                'office_name' => ['required', 'string'],
                'acronym' => ['required', 'string'],
                'office_type' => ['required', 'string', 'in:Academic,Non Academic'],
            ]);

            $collegeOffice = Office::create([
                'office_name' => $request->office_name,
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
                'office_name' => ['required', 'sometimes', 'string'],
                'acronym' => ['required', 'sometimes', 'string'],
                'office_type' => ['sometimes', 'string'],
            ]);

            // Get the old acronym for cascade update (if needed)
            $oldAcronym = $collegeOffice->acronym;

            // Update the office
            $collegeOffice->update(array_filter([
                'office_name' => $request->office_name,
                'acronym' => $request->acronym,
                'office_type' => $request->office_type,
            ]));

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
            
            $search = $request->input('search');
            $perPage = $request->input('per_page', 10);

            // Initialize query
            $query = Office::select('id', 'office_name', 'acronym', 'office_type')
                ->where('is_archived', 'A')
                ->when($search, function ($query, $search) {
                    return $query->where(function ($q) use ($search) {
                        $q->where('office_name', 'LIKE', '%' . $search . '%')
                            ->orWhere('acronym', 'LIKE', '%' . $search . '%');
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
                    'office_name' => $office->office_name,
                    'acronym' => $office->acronym,
                    'office_type' => $office->office_type,
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
                    'next_page_url' => $result->nextPageUrl(),
                    'prev_page_url' => $result->previousPageUrl(),
                    'url' => url('api/officeList?page=' . $result->currentPage() . '&per_page=' . $result->perPage()),
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
