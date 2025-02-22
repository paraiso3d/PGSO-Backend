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
use App\Models\Division;
use App\Models\Department;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CategoryController extends Controller
{
    /**
     * Create a new Category
     */
    public function createCategory(Request $request)
    {
        try {
            // Validate the request including an array of personnel_ids
            $request->validate([
                'category_name' => 'required|string|unique:categories,category_name',
                'description' => 'required|string',
                'personnel_ids' => 'required|array', // Validate as an array
                'personnel_ids.*' => 'exists:users,id', // Ensure each id exists in users table
            ]);
    
            // Check if all personnel_ids belong to users with the role_name "personnel"
            $personnel = User::whereIn('id', $request->personnel_ids)
                ->where('role_name', 'personnel')
                ->get();
    
            if ($personnel->count() !== count($request->personnel_ids)) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Some personnel_ids do not belong to users with the role of personnel.',
                ], 422);
            }
    
            // Create the category
            $category = Category::create([
                'category_name' => $request->category_name,
                'description' => $request->description,
            ]);
    
            // Attach personnel to the category
            $category->personnel()->attach($request->personnel_ids);
    
            // Prepare the success response with personnel details
            $response = [
                'isSuccess' => true,
                'message' => 'Category successfully created.',
                'category' => [
                    'id' => $category->id,
                    'category_name' => $category->category_name,
                    'description' => $category->description,
                    'personnel' => $personnel->map(function ($p) {
                        return [
                            'id' => $p->id,
                            'name' => $p->first_name . ' ' . $p->last_name,
                        ];
                    }),
                ],
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
    
            // Build the query to fetch category data and load personnel relationship
            $query = Category::with(['personnel:id,first_name,last_name']) // Load related personnel
                ->select('id', 'category_name', 'description')
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
                'categories' => $categories->map(function ($category) {
                    $category->personnel = $category->personnel->map(function ($personnel) {
                        unset($personnel->pivot);
                        return $personnel;
                    });
                    return $category;
                }),
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
            // Find the category
            $category = Category::findOrFail($id);
    
            // Validate the request
            $request->validate([
                'category_name' => 'sometimes|required|string|unique:categories,category_name,' . $id,
                'description' => 'sometimes|required|string',
                'personnel_ids' => 'sometimes|required|array', // Validate as an array
                'personnel_ids.*' => 'exists:users,id', // Ensure each id exists in users table
            ]);
    
            // If personnel_ids are provided, check if they belong to users with role_name "personnel"
            if ($request->has('personnel_ids')) {
                $personnel = User::whereIn('id', $request->personnel_ids)
                    ->where('role_name', 'personnel')
                    ->get();
    
                if ($personnel->count() !== count($request->personnel_ids)) {
                    return response()->json([
                        'isSuccess' => false,
                        'message' => 'Some personnel_ids do not belong to users with the role of personnel.',
                    ], 422);
                }
            } else {
                $personnel = $category->personnel; // Keep existing personnel if not updating
            }
    
            // Update the category
            $category->update([
                'category_name' => $request->category_name ?? $category->category_name,
                'description' => $request->description ?? $category->description,
            ]);
    
            // Update personnel if provided
            if ($request->has('personnel_ids')) {
                $category->personnel()->sync($request->personnel_ids); // Sync instead of attach
            }
    
            // Prepare the success response
            $response = [
                'isSuccess' => true,
                'message' => 'Category successfully updated.',
                'category' => [
                    'id' => $category->id,
                    'category_name' => $category->category_name,
                    'description' => $category->description,
                    'personnel' => $personnel->map(function ($p) {
                        return [
                            'id' => $p->id,
                            'name' => $p->first_name . ' ' . $p->last_name,
                        ];
                    }),
                ],
            ];
    
            // Log API call
            $this->logAPICalls('updateCategory', $id, $request->all(), $response);
    
            return response()->json($response, 200);
    
        } catch (ValidationException $v) {
            // Validation error response
            $response = [
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $v->errors(),
            ];
            $this->logAPICalls('updateCategory', "", $request->all(), $response);
            return response()->json($response, 422);
    
        } catch (Throwable $e) {
            // Exception error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to update the Category.',
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
