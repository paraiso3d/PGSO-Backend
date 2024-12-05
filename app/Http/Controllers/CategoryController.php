<?php

namespace App\Http\Controllers;
use App\Models\User;
use App\Models\Category;
use App\Models\ApiLog;
use App\Models\Requests;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;
use DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CategoryController extends Controller
{
    /**
     * Create a new Category
     */
    public function createCategory(Request $request)
    {
        try {
            // Validate the request using division_id and supervisor
            $request->validate([
                'category_name' => 'required|string|unique:categories,category_name',
                'description' => 'required|string', 
            ]);

            // Create the category
            $category = Category::create([
                'category_name' => $request->category_name,
                'description' => $request->description,
            ]);

            // Prepare the success response with division and supervisor details
            $response = [
                'isSuccess' => true,
                'message' => 'Category successfully created.',
                'category' => [
                    'id' => $category->id,
                    'category_name' => $category->category_name,
                    'description' => $category->description,
                ]
            ];

            // Log API call
            $this->logAPICalls('createCategory', $category->id, $request->all(), $response);

            return response()->json($response, 200);

        } catch (ValidationException $v) {
            // Prepare the validation error response
            $response = [
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $v->errors()
            ];
            $this->logAPICalls('createCategory', "", $request->all(), $response);
            return response()->json($response, 422);

        } catch (Throwable $e) {
            // Prepare the error response in case of an exception
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to create the Category.',
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('createCategory', "", $request->all(), $response);
            return response()->json($response, 500);
        }
    }

    public function getCategory(Request $request)
    {
        try {
            // Validate the request
            $validated = $request->validate([
                'per_page' => 'nullable|integer|min:1',
                'search' => 'nullable|string',
            ]);
    
            // Build the query to fetch category data
            $query = Category::select('id', 'category_name', 'description')
                ->where('is_archived', '0'); // Only include active (non-archived) categories
    
            // Add search functionality
            if (!empty($validated['search'])) {
                $query->where('category_name', 'like', '%' . $validated['search'] . '%');
            }
    
            // Paginate results
            $perPage = $validated['per_page'] ?? 10;
            $categories = $query->paginate($perPage);
    
            // Check if any categories were found
            if ($categories->isEmpty()) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'No categories found matching the criteria.',
                ], 404);
            }
    
            // Format the response
            $response = [
                'isSuccess' => true,
                'message' => 'Categories retrieved successfully.',
                'categories' => $categories->items(), // Return only the relevant category fields
                'pagination' => [
                    'total' => $categories->total(),
                    'per_page' => $categories->perPage(),
                    'current_page' => $categories->currentPage(),
                    'last_page' => $categories->lastPage(),
                ],
            ];
    
            // Log the API call
            $this->logAPICalls('getCategory', "", $request->all(), $response);
    
            return response()->json($response, 200);
    
        } catch (ValidationException $v) {
            // Handle validation errors
            $response = [
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $v->errors(),
            ];
            $this->logAPICalls('getCategory', "", $request->all(), $response);
            return response()->json($response, 422);
    
        } catch (Throwable $e) {
            // Handle other exceptions
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve categories.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('getCategory', "", $request->all(), $response);
            return response()->json($response, 500);
        }
    }
    
    /**
      Update an existing Category
     */
    public function updateCategory(Request $request, $id)
    {
        try {

            $category = Category::findOrFail($id);


            $request->validate([
                'category_name' => 'sometimes|required|string',
                'division_id' => 'sometimes|required|integer|exists:divisions,id',
                'user_id' => 'sometimes|required|exists:users,id'
            ]);


            $division = $request->has('division_id') ? Division::findOrFail($request->division_id) : Division::find($category->division_id);
            $teamleader = $request->has('team_leader') ? User::select('id', 'first_name', 'last_name', 'middle_initial')
                ->findOrFail($request->team_leader) : User::find($category->user_id);


            $category->update([
                'category_name' => $request->category_name ?? $category->category_name,
                'division_id' => $division->id ?? $category->division_id,
                'user_id' => $teamleader->id ?? $category->user_id
            ]);


            $response = [
                'isSuccess' => true,
                'message' => "Category successfully updated",
                'category' => [
                    'id' => $category->id,
                    'category_name' => $category->category_name,
                    'division_name' => $division->div_name,
                    'division_id' => $division->id,
                    'teamleader' => [
                        'id' => $teamleader->id,
                        'first_name' => $teamleader->first_name,
                        'last_name' => $teamleader->last_name,
                        'middle_initial' => $teamleader->middle_initial,
                    ]
                ]
            ];


            $this->logAPICalls('updateCategory', $id, $request->all(), $response);
            return response()->json($response, 200);

        } catch (ValidationException $v) {

            $response = [
                'isSuccess' => false,
                'message' => "Validation failed.",
                'errors' => $v->errors(),
            ];
            $this->logAPICalls('updateCategory', "", $request->all(), $response);
            return response()->json($response, 422);

        } catch (Throwable $e) {

            $response = [
                'isSuccess' => false,
                'message' => "Failed to update the Category.",
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('updateCategory', "", $request->all(), $response);
            return response()->json($response, 500);
        }
    }


    /**
     * Delete a Category by ID
     */
    public function deleteCategory($id)
    {
        try {
            $category = Category::findOrFail($id);
            $category->update(['is_archived' => "1"]);

            $response = [
                'isSuccess' => true,
                'message' => 'Category successfully deleted.'
            ];
            $this->logAPICalls('deleteCategory', $id, [], $response);
            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to delete the Category.',
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('deleteCategory', "", [], $response);
            return response()->json($response, 500);
        }
    }

    public function getDropdownOptionsCategory(Request $request)
    {
        try {
            $divisions = Department::select('id', 'div_name')
                ->where('is_archived', '0')
                ->get();

            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown data retrieved successfully.',
                'div_name' => $divisions,
            ];


            $this->logAPICalls('getDropdownOptionsCategory', "", $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {

            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown data.',
                'error' => $e->getMessage()
            ];


            $this->logAPICalls('getDropdownOptionsCategory', "", $request->all(), $response);

            return response()->json($response, 500);
        }
    }


    public function getdropdownteamleader(Request $request)
    {
        try {
            $teamleaderTypeId = DB::table('user_types')->where('name', 'TeamLeader')->value('id');

            if (!$teamleaderTypeId) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'TeamLeader type not found.'
                ], 404);
            }

            // Fetch active team leaders
            $teamLeaders = User::where('user_type_id', $teamleaderTypeId)
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
                'teamleader' => $teamLeaders,
            ];

            // Log the API call
            $this->logAPICalls('dropdownUserCategory', "", $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown options.',
                'error' => $e->getMessage(),
            ];

            $this->logAPICalls('dropdownUserCategory', "", $request->all(), $response);
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
