<?php

namespace App\Http\Controllers;

use App\Models\CollegeOffice;
use App\Models\ApiLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CollegeOfficeController extends Controller
{
    /**
     * Create a new college office.
     */
    public function createCollegeOffice(Request $request)
    {
        try {
            $request->validate([
                'officename' => ['required', 'string'],
                'abbreviation' => ['required', 'string'],
                'officetype' => ['required', 'in:academic,non-academic'],
            ]);

            $collegeOffice = CollegeOffice::create([
                'officename' => $request->officename,
                'abbreviation' => $request->abbreviation,
                'officetype' => $request->officetype,
            ]);

            $response = [
                'isSuccess' => true,
                'message' => "College Office successfully created.",
                'data' => $collegeOffice
            ];
            $this->logAPICalls('createCollegeOffice', $collegeOffice->id, $request->all(), [$response]);
            return response()->json($response, 201);
        } 
        catch (ValidationException $v) {
            $response = [
                'isSuccess' => false,
                'message' => "Invalid input data.",
                'error' => $v->errors()
            ];
            $this->logAPICalls('createCollegeOffice', "", $request->all(), [$response]);
            return response()->json($response, 422);
        } 
        catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to create the College Office.",
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('createCollegeOffice', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
    }

    /**
     * Update an existing college office.
     */
    public function updateCollegeOffice(Request $request, $id)
    {
        try {
            $collegeOffice = CollegeOffice::findOrFail($id);

            $request->validate([
                'officename' => ['sometimes', 'required', 'string'],
                'abbreviation' => ['sometimes', 'required', 'string'],
                'officetype' => ['sometimes', 'required', 'in:academic,non-academic'],
            ]);

            $collegeOffice->update([
                'officename' => $request->officename,
                'abbreviation' => $request->abbreviation,
                'officetype' => $request->officetype,
            ]);

            $response = [
                'isSuccess' => true,
                'message' => "College Office successfully updated.",
                'data' => $collegeOffice
            ];
            $this->logAPICalls('updateCollegeOffice', $id, $request->all(), [$response]);
            return response()->json($response, 200);
        } 
        catch (ValidationException $v) {
            $response = [
                'isSuccess' => false,
                'message' => "Invalid input data.",
                'error' => $v->errors()
            ];
            $this->logAPICalls('updateCollegeOffice', "", $request->all(), [$response]);
            return response()->json($response, 422);
        } 
        catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to update the College Office.",
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('updateCollegeOffice', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
    }

    /**
     * Get all college offices.
     */
    public function getCollegeOffices()
    {
        try {
            $collegeOffices = CollegeOffice::all();

            $response = [
                'isSuccess' => true,
                'message' => "College Offices list:",
                'data' => $collegeOffices
            ];
            $this->logAPICalls('getCollegeOffices', "", [], [$response]);
            return response()->json($response, 200);
        } 
        catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to retrieve College Offices.",
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('getCollegeOffices', "", [], [$response]);
            return response()->json($response, 500);
        }
    }

    /**
     * Delete a college office.
     */
    public function deleteCollegeOffice($id)
    {
        try {
            $collegeOffice = CollegeOffice::findOrFail($id);

            $collegeOffice->delete();

            $response = [
                'isSuccess' => true,
                'message' => "College Office successfully deleted."
            ];
            $this->logAPICalls('deleteCollegeOffice', $id, [], [$response]);
            return response()->json($response, 204);
        } 
        catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to delete the College Office.",
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('deleteCollegeOffice', "", [], [$response]);
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
        } 
        catch (Throwable $e) {
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
