<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\ApiLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;


class DivisionController extends Controller
{
    /**
     * Create a new college office.
     */
    public function createDivision(Request $request)
    {
        try {
            $request->validate([
                'div_name' => ['required', 'string', 'unique:divisions,div_name,except,division_id'],
                'note' => ['required', 'string'],
                'is_archived' => ['nullable', 'in: A, I']
            ]);

            $divname = Division::create([
                'div_name' => $request->div_name,
                'note' => $request->note,
            ]);

            $response = [
                'isSuccess' => true,
                'message' => "Division successfully created.",
                'division' => $divname
            ];
            $this->logAPICalls('createDivision', $divname->id, $request->all(), [$response]);
            return response()->json($response, 201);
        } catch (ValidationException $v) {
            $response = [
                'isSuccess' => false,
                'message' => "Invalid input data.",
                'error' => $v->errors()
            ];
            $this->logAPICalls('createDivision', "", $request->all(), [$response]);
            return response()->json($response, 422);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to create the Division.",
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('createDivision', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
    }

    /**
     * Update an existing college office.
     */
    public function updateDivision(Request $request, $division_id)
    {
        try {
            $divname = Division::findOrFail($division_id);

            $request->validate([
                'div_name' => ['sometimes','required', 'string', 'unique:divisions,div_name,except,division_id'],
                'note' => ['sometimes', 'required', 'string'],
            ]);

            $divname->update([
                'div_name' => $request->div_name,
                'note' => $request->note,
            ]);

            $response = [
                'isSuccess' => true,
                'message' => "Division successfully updated.",
                'division' => $divname
            ];
            $this->logAPICalls('updateDivision', $division_id, $request->all(), [$response]);
            return response()->json($response, 200);
        } catch (ValidationException $v) {
            $response = [
                'isSuccess' => false,
                'message' => "Invalid input data.",
                'error' => $v->errors()
            ];
            $this->logAPICalls('updateDivision', "", $request->all(), [$response]);
            return response()->json($response, 422);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to update the Division.",
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('updateDivision', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
    }

    /**
     * Get all college offices.
     */
    public function getDivisions(Request $request)
{
    try {
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search'); // Get the search term from the request

        // Create the query to select divisions
        $query = Division::select('division_id', 'div_name', 'note')
            ->where('is_archived', 'A');

        // Apply search filter if search term is provided
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('div_name', 'LIKE', '%' . $search . '%')
                  ->orWhere('note', 'LIKE', '%' . $search . '%'); // Search in 'div_name' and 'note'
            });
        }

        // Paginate the results
        $divnames = $query->paginate($perPage);

        $response = [
            'isSuccess' => true,
            'message' => "Division names list:",
            'division' => $divnames, // Get the paginated items
            'pagination' => [
                'total' => $divnames->total(),
                'per_page' => $divnames->perPage(),
                'current_page' => $divnames->currentPage(),
                'last_page' => $divnames->lastPage(),
                'next_page_url' => $divnames->nextPageUrl(),
                'prev_page_url' => $divnames->previousPageUrl(),
            ]
        ];

        // Log API calls
        $this->logAPICalls('getDivisions', "", $request->all(), [$response]);

        return response()->json($response, 200);
    } catch (Throwable $e) {
        // Prepare the error response
        $response = [
            'isSuccess' => false,
            'message' => "Failed to retrieve Division Names.",
            'error' => $e->getMessage()
        ];

        // Log API calls
        $this->logAPICalls('getDivisions', "", $request->all(), [$response]);

        return response()->json($response, 500);
    }
}

    
    /**
     * Delete a college office.
     */
    public function deleteDivision(Request $request)
    {
        try {
            $divname = Division::find($request->id);

            $divname->update(['isarchive' => "I"]);

            $response = [
                'isSuccess' => true,
                'message' => "Division successfully deleted."
            ];
            $this->logAPICalls('deleteDivision', $divname->id, [], [$response]);
            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to delete the Division Name.",
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('deleteDivision', "", [], [$response]);
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

