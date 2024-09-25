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
                'div_name' => ['required', 'string'],
                'note' => ['required', 'string'],
            ]);

            $divname = Division::create([
                'div_name' => $request->div_name,
                'note' => $request->note,
            ]);

            $response = [
                'isSuccess' => true,
                'message' => "Division successfully created.",
                'data' => $divname
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
    public function updateDivision(Request $request, $id)
    {
        try {
            $divname = Division::findOrFail($id);

            $request->validate([
                'div_name' => ['sometimes', 'required', 'string'],
                'note' => ['sometimes', 'required', 'string'],
            ]);

            $divname->update([
                'div_name' => $request->div_name,
                'note' => $request->note,
            ]);

            $response = [
                'isSuccess' => true,
                'message' => "Division successfully updated.",
                'data' => $divname
            ];
            $this->logAPICalls('updateDivision', $id, $request->all(), [$response]);
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
            // Set the number of items per page (default to 10 if not provided)
            $perPage = $request->input('per_page', 10);

            // Fetch paginated division names
            $divnames = Division::paginate($perPage);

            $response = [
                'isSuccess' => true,
                'message' => "Division names list:",
                'data' => $divnames
            ];

            // Log API calls
            $this->logAPICalls('getDivisions', "", [], [$response]);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to retrieve Division Names.",
                'error' => $e->getMessage()
            ];

            // Log API calls
            $this->logAPICalls('getDivisions', "", [], [$response]);

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

