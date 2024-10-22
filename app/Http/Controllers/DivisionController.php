<?php

namespace App\Http\Controllers;
use App\Models\Category;
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
            // Validate the request data
            $request->validate([
                'div_name' => ['required', 'string', 'unique:divisions,div_name'], // Corrected unique rule
                'note' => ['required', 'string'],
                'is_archived' => ['nullable', 'in: A, I']
            ]);

            // Create the division
            $divname = Division::create([
                'div_name' => $request->div_name,
                'note' => $request->note,
            ]);

            // Prepare a success response
            $response = [
                'isSuccess' => true,
                'message' => "Division successfully created.",
                'division' => $divname
            ];
            // Log the API call
            $this->logAPICalls('createDivision', $divname->id, $request->all(), [$response]);

            // Return the success response
            return response()->json($response, 201);
        } catch (ValidationException $v) {
            // Handle validation errors
            $response = [
                'isSuccess' => false,
                'message' => "Invalid input data.",
                'error' => $v->errors()
            ];
            // Log the API call with validation errors
            $this->logAPICalls('createDivision', "", $request->all(), [$response]);

            // Return the validation error response
            return response()->json($response, 422);
        } catch (Throwable $e) {
            // Handle any other exceptions
            $response = [
                'isSuccess' => false,
                'message' => "Failed to create the Division.",
                'error' => $e->getMessage()
            ];
            // Log the API call with error
            $this->logAPICalls('createDivision', "", $request->all(), [$response]);

            // Return the internal server error response
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
                'note' => ['sometimes','string'],
            ]);

            // Store the old division name before updating
            $oldDivName = $division->div_name;

            // Update the division
            $division->update([
                'div_name' => $request->div_name,
                'note' => $request->note,
            ]);

            if ($oldDivName !== $division->div_name) {
                DB::table('categories')
                    ->where('division', $oldDivName)
                    ->update(['division' => $division->div_name]);
            }

            // Prepare the success response
            $response = [
                'isSuccess' => true,
                'message' => "Division successfully updated, and associated categories updated.",
                'division' => $division, // Return the updated division
            ];

            // Log the API call
            $this->logAPICalls('updateDivision', $id, $request->all(), [$response]);

            // Return the success response
            return response()->json($response, 200);
        } catch (ValidationException $v) {
            // Prepare the validation error response
            $response = [
                'isSuccess' => false,
                'message' => "Invalid input data.",
                'error' => $v->errors()
            ];
            $this->logAPICalls('updateDivision', "", $request->all(), [$response]);
            return response()->json($response, 422);
        } catch (Throwable $e) {
            // Prepare the error response in case of an exception
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
       
        $validated = $request->validate([
            'per_page' => 'nullable|integer',
            'search' => 'nullable|string',
        ]);

       
        $query = Category::with(['divisions:id,div_name,note']) 
            ->where('is_archived', 'A')
            ->select('id', 'category_name', 'division_id', 'is_archived');

        // Apply search filter
        if (!empty($validated['search'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('category_name', 'like', '%' . $validated['search'] . '%')
                  ->orWhereHas('divisions', function ($q) use ($validated) {
                      $q->where('div_name', 'like', '%' . $validated['search'] . '%');
                  });
            });
        }

        
        $perPage = $validated['per_page'] ?? 10;
        $categories = $query->paginate($perPage);

       
        if ($categories->isEmpty()) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'No active categories found matching the criteria.',
            ], 500);
        }

       
        $groupedCategories = $categories->getCollection()->groupBy(function ($category) {
            return optional($category->divisions)->id;
        });


        $formattedResponse = [];
        foreach ($groupedCategories as $divisionId => $group) {
            $formattedResponse[] = [
                'division_id' => $divisionId,
                'division_name' => optional($group->first()->divisions)->div_name,
                'note' => optional($group->first()->divisions)->note, 
                'categories' => $group->map(function ($division) {
                    return [
                        'id' => $division->id,
                        'category_name' => $division->category_name,
                        'is_archived' => $division->is_archived,
                    ];
                }),
            ];
        }

        // Structure the full response with pagination details
        $response = [
            'isSuccess' => true,
            'message' => 'Divisions retrieved successfully.',
            'division' => $formattedResponse,
            'pagination' => [
                'total' => $categories->total(),
                'per_page' => $categories->perPage(),
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'url' => url('api/categoryList?page=' . $categories->currentPage() . '&per_page=' . $categories->perPage()),
            ]
        ];

        // Log the API call
        $this->logAPICalls('getDivisions', "", $request->all(), $response);

        // Return the successful response
        return response()->json($response, 200);

    } catch (Throwable $e) {
        // Handle any exceptions and return a failure message
        $response = [
            'isSuccess' => false,
            'message' => 'Failed to retrieve the categories.',
            'error' => $e->getMessage(),
        ];
        $this->logAPICalls('getDivisions', "", $request->all(), $response);

        // Return the error response
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

            $divname->update(['is_archived' => "I"]);

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

