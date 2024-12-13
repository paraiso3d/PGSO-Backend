<?php

namespace App\Http\Controllers;
use App\Models\User;
use App\Models\Category;
use App\Models\Division;
use App\Models\ApiLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;
use Illuminate\Database\Eloquent\ModelNotFoundException;


class DivisionController extends Controller
{
    /**
     * Create a new college office.
     */
    public function createDivision(Request $request)
{
    try {
        // Validate input data
        $request->validate([
            'division_name' => 'required|string|unique:divisions,division_name',
            'office_location' => 'required|string',
            'staff_id' => 'nullable|array', // Expecting an array of staff IDs
            'staff_id.*' => 'exists:users,id', // Validate each ID exists in the users table
        ]);

        // Fetch the assigned category
        $assignedCategory = Category::select('id', 'category_name')
            ->findOrFail($request->category_id);

        // Validate and fetch staff details
        $staffDetails = [];
        if (!empty($request->staff_id)) {
            $staffDetails = User::whereIn('id', $request->staff_id)
                ->where('role_name', 'staff') // Ensure role is 'staff'
                ->get(['id', 'first_name', 'last_name'])
                ->toArray();

            // Check if all provided staff_ids are valid staff
            $validStaffIds = array_column($staffDetails, 'id');
            if (count($validStaffIds) !== count($request->staff_id)) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'One or more user IDs do not have the staff role.',
                ], 400);
            }
        }

        // Create the division
        $division = Division::create([
            'division_name' => $request->division_name,
            'office_location' => $request->office_location,
            'staff_id' => json_encode(array_column($staffDetails, 'id')), // Save as JSON-encoded string
        ]);

        // Prepare the success response
        $response = [
            'isSuccess' => true,
            'message' => 'Division created successfully.',
            'division' => [
                'id' => $division->id,
                'division_name' => $division->division_name,
                'office_location' => $division->office_location,
                'staff' => $staffDetails, // Include staff details (id, first_name, last_name)
            ],
        ];

        // Log API call
        $this->logAPICalls('createDivision', "", $request->all(), [$response]);

        return response()->json($response, 200);
    } catch (ValidationException $v) {
        // Validation error response
        $response = [
            'isSuccess' => false,
            'message' => 'Invalid input data.',
            'error' => $v->errors(),
        ];
        $this->logAPICalls('createDivision', "", $request->all(), [$response]);
        return response()->json($response, 400);
    } catch (Throwable $e) {
        // General error response
        $response = [
            'isSuccess' => false,
            'message' => 'Failed to create the Division.',
            'error' => $e->getMessage(),
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
            // Find the division by its ID
            $division = Division::findOrFail($id);

            // Validate the incoming request
            $request->validate([
                'div_name' => ['sometimes', 'required', 'string'],
                'note' => ['sometimes', 'string'],
                'categories' => ['sometimes', 'required', 'array'],
                'categories.*' => 'exists:categories,id',
                'user_id' => 'required|exists:users,id'
            ]);

            // Update the division
            $division->update([
                'div_name' => $request->div_name ?? $division->div_name,
                'note' => $request->note ?? $division->note,
                'category_id' => json_encode($request->categories ?? json_decode($division->category_id, true)),
                'user_id' => $request->user_id
            ]);

            // Fetch the assigned categories
            $assignedCategories = Category::whereIn('id', json_decode($division->category_id, true))
                ->select('id', 'category_name')
                ->get();

            // Retrieve the supervisor's information
            $supervisor = User::select('id', 'first_name', 'last_name', 'middle_initial')
                ->find($request->user_id);

            $response = [
                'isSuccess' => true,
                'message' => "Division successfully updated.",
                'division' => [
                    'id' => $division->id,
                    'div_name' => $division->div_name,
                    'note' => $division->note,
                    'user_id' => $supervisor, // Include supervisor details
                    'categories' => $assignedCategories, // Include categories
                ],
            ];

            // Log the API call
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

    public function getdropdownCategories(Request $request)
    {
        try {

            $categories = Category::select('id', 'category_name')
                ->where('is_archived', '0')
                ->get();

            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown options retrieved successfully.',
                'category' => $categories,
            ];


            $this->logAPICalls('getDropdownOptions', "", [], [$response]);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown options.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('getDropdownOptions', "", [], [$response]);
            return response()->json($response, 500);
        }
    }

    public function dropdownSupervisor(Request $request)
    {
        try {
            // Retrieve the Supervisor user type ID
            $supervisorTypeId = DB::table('user_types')->where('name', 'Supervisor')->value('id');

            if (!$supervisorTypeId) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Supervisor type not found.'
                ], 404);
            }

            // Fetch active team leaders
            $supervisors = User::where('user_type_id', $supervisorTypeId)
                ->where('is_archived', '0')
                ->get()
                ->map(function ($leader) {
                    // Concatenate full name and return it only
                    return [
                        'id' => $leader->id,
                        'full_name' => trim($leader->first_name . ' ' . $leader->middle_initial . ' ' . $leader->last_name),
                    ];
                });

            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown options retrieved successfully.',
                'supervisor' => $supervisors,
            ];

            // Log the API call
            $this->logAPICalls('dropdownSupervisor', "", $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown options.',
                'error' => $e->getMessage(),
            ];

            $this->logAPICalls('dropdownSupervisor', "", $request->all(), $response);
            return response()->json($response, 500);
        }

    }

    /**
     * Get all college offices.
     */
    public function getDivisions()
{
    try {
        // Fetch all divisions with their related category details
        $divisions = Division::with('category:id,category_name')->get()->map(function ($division) {
            // Decode the JSON-encoded staff IDs
            $staffIds = json_decode($division->staff_id, true);

            // Fetch staff details for the decoded IDs
            $staffDetails = !empty($staffIds)
                ? User::whereIn('id', $staffIds)->get(['id', 'first_name', 'last_name'])->map(function ($staff) {
                    return [
                        'id' => $staff->id,
                        'name' => $staff->first_name . ' ' . $staff->last_name,
                    ];
                })->toArray()
                : [];

            // Return the structured division data
            return [
                'id' => $division->id,
                'division_name' => $division->division_name,
                'office_location' => $division->office_location,
                'staff' => $staffDetails, // Include decoded staff details
            ];
        });

        // Build success response
        $response = [
            'isSuccess' => true,
            'message' => 'Divisions retrieved successfully.',
            'divisions' => $divisions,
        ];

        // Log API call
        $this->logAPICalls('getDivisions', "", [], [$response]);

        return response()->json($response, 200);

    } catch (Throwable $e) {
        // Handle errors
        $response = [
            'isSuccess' => false,
            'message' => 'Failed to retrieve divisions.',
            'error' => $e->getMessage(),
        ];

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

            $divname->update(['is_archived' => "1"]);

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

    /*
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

