<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\location;
use App\Models\ApiLog;
use Throwable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LocationController extends Controller
{
    /**
     * Create a new user type.
     */
    public function createlocation(Request $request)
    {
        try {
            $request->validate([
                'location_name' => ['required'],
                'note' => ['required'],
            ]);

            $location = location::create([
                'location_name' => $request->location_name,
                'note' => $request->note,
            ]);

            $response = [
                'isSuccess' => true,
                'message' => "location successfully created.",
                'location' => $location
            ];
            $this->logAPICalls('createlocation', $location->id, $request->all(), [$response]);
            return response()->json($response, 200);
        } catch (ValidationException $v) {
            $response = [
                'isSuccess' => false,
                'message' => "Invalid input data.",
                'error' => $v->errors()
            ];
            $this->logAPICalls('createlocation', "", $request->all(), [$response]);
            return response()->json($response, 500);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to create the location.",
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('createlocation', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
    }

    /**
     * Update an existing user type.
     */
    public function updatelocation(Request $request, $id)
    {
        try {
            $location = location::findOrFail($id); // Find the user type or throw 404

            $request->validate([
                'location_name' => ['required', 'string'],
                'note' => ['required', 'string'],
            ]);

            $location->update([
                'location_name' => $request->location_name,
                'note' => $request->note,
            ]);

            $response = [
                'isSuccess' => true,
                'message' => "UserType successfully updated.",
                'location' => $location
            ];
            $this->logAPICalls('updatelocation', $id, $request->all(), [$response]);
            return response()->json($response, 200);
        } catch (ValidationException $v) {
            $response = [
                'isSuccess' => false,
                'message' => "Invalid input data.",
                'error' => $v->errors()
            ];
            $this->logAPICalls('updatelocation', "", $request->all(), [$response]);
            return response()->json($response, 500);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to update the location.",
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('updatelocation', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
    }


    /**
     * Get all user types.
     */
    public function getlocations(Request $request)
    {
        try {
            // Set the number of items per page (default to 10 if not provided)
            $perPage = $request->input('per_page', 10);

            // Fetch paginated locations
            $locations = Location::paginate($perPage);
            $locations = location::select('category_name','division');

            $response = [
                'isSuccess' => true,
                'message' => "Location list:",
                'location' => $locations
            ];

            // Log API calls
            $this->logAPICalls('getlocations', "", [], [$response]);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to retrieve Location.",
                'error' => $e->getMessage()
            ];

            // Log API calls
            $this->logAPICalls('getlocations', "", [], [$response]);

            return response()->json($response, 500);
        }
    }

    /**
     * Delete a user type.
     */
    public function deletelocation(Request $request)
    {
        try {
            $location = location::find($request->id); // Find or throw 404

            $location->update(['isarchive' => "I"]);

            $response = [
                'isSuccess' => true,
                'message' => "Location successfully deleted."
            ];
            $this->logAPICalls('deletelocation', $request->id, [], [$response]);
            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to delete the Location.",
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('deletelocation', "", [], [$response]);
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
