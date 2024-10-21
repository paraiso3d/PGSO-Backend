<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ApiLog;
use App\Models\Division;
use App\Models\Requests;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class CategoryController extends Controller
{
    /**
     * Create a new Category
     */
    public function createCategory(Request $request)
    {
        try {
            // Validate the request using division_id
            $validator = Category::validateCategory($request->all());

            if ($validator->fails()) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors()
                ];
                $this->logAPICalls('createCategory', "", $request->all(), $response);
                return response()->json($response, 500); // Return 422 for validation errors
            }

            // Find the division based on the division_id
            $division = Division::findOrFail($request->input('division_id'));

            // Create the category, setting the division_name based on division_id
            $category = Category::create([
                'category_name' => $request->category_name,
                'division' => $division->div_name, // Setting div_name based on division_id
                'division_id' => $division->id,    // Use the division_id
            ]);

            $response = [
                'isSuccess' => true,
                'message' => 'Category successfully created.',
                'category' => $category
            ];
            $this->logAPICalls('createCategory', "", $request->all(), $response);
            return response()->json($response, 200);  // 201 for successful resource creation
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to create the Category.',
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('createCategory', "", $request->all(), $response);
            return response()->json($response, 500);  // 500 for internal server error
        }
    }

    public function getCategory(Request $request)
    {
        try {
            // Validate the incoming request for pagination and search term
            $validated = $request->validate([
                'per_page' => 'nullable|integer',  // Number of items per page if pagination is enabled
                'search' => 'nullable|string',     // Search term
            ]);
    
            // Start building the query to select categories
            $query = Category::select('id', 'category_name', 'division', 'division_id')
                ->where('is_archived', 'A'); // Always filter by active categories (is_archived = 'A')
    
            // Apply search filter if search term is provided
            if (!empty($validated['search'])) {
                $query->where(function ($q) use ($validated) {
                    $q->where('category_name', 'like', '%' . $validated['search'] . '%')
                        ->orWhere('division', 'like', '%' . $validated['search'] . '%');
                });
            }
    
            // Set pagination: use provided per_page value or default to 10
            $perPage = $validated['per_page'] ?? 10;
            $categories = $query->paginate($perPage);
    
            if ($categories->isEmpty()) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'No active categories found matching the criteria.',
                ], 500);
            }
    
            // Prepare response with pagination
            $response = [
                'isSuccess' => true,
                'message' => 'Categories retrieved successfully.',
                'categories' => $categories,
                'pagination' => [
                    'total' => $categories->total(),
                    'per_page' => $categories->perPage(),
                    'current_page' => $categories->currentPage(),
                    'last_page' => $categories->lastPage(),
                    'url' => url('api/categoryList?page=' . $categories->currentPage() . '&per_page=' . $categories->perPage()),
                ]
            ];
    
            // Log the API call
            $this->logAPICalls('getCategory', "", $request->all(), $response);
    
            // Return the response as JSON
            return response()->json($response, 200);
    
        } catch (Throwable $e) {
            // Handle any exception and return an error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve the categories.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('getCategory', "", $request->all(), $response);
    
            // Return the error response as JSON
            return response()->json($response, 500);
        }
    }
    
    

    /**
     * Update an existing Category
     */
    public function updateCategory(Request $request, $id)
    {
        try {
            // Find the category by its ID
            $category = Category::findOrFail($id);

            // Validate the incoming request using the custom validation method
            $validator = Category::updatevalidateCategory($request->all());

            // Check if the validation fails
            if ($validator->fails()) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ];
                $this->logAPICalls('updateCategory', "", $request->all(), $response);
                return response()->json($response, 500);  // Return 422 for validation errors
            }

            // Find the division based on the division_id
            $division = Division::findOrFail($request->input('division_id'));

            // Update the category with the new data, including the division_name based on division_id
            $category->update([
                'category_name' => $request->input('category_name'),
                'division' => $division->div_name, 
                'division_id' => $division->id      
            ]);

            // Prepare success response
            $response = [
                'isSuccess' => true,
                'message' => "Category successfully updated",
                'category' => $category, 
            ];

            // Log the API call and return the success response
            $this->logAPICalls('updateCategory', $id, $request->all(), $response);
            return response()->json($response, 200);  
        } catch (Throwable $e) {
            // Prepare error response in case of an exception
            $response = [
                'isSuccess' => false,
                'message' => "Failed to update the Category.",
                'error' => $e->getMessage(),
            ];

            // Log the error and return the error response
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
            $category->update(['is_archived' => "I"]);

            $response = [
                'isSuccess' => true,
                'message' => 'Category successfully deleted.'
            ];
            $this->logAPICalls('deleteCategory', $id, [], $response);
            return response()->json($response, 200);  // 200 for success
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to delete the Category.',
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('deleteCategory', "", [], $response);
            return response()->json($response, 500);  // 500 for internal server error
        }
    }

    public function getDropdownOptionsCategory(Request $request)
    {
        try {


            $divisions = Division::select('id', 'div_name')
                ->where('is_archived', 'A')
                ->get();

            // Build the response
            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown data retrieved successfully.',
                'div_name' => $divisions,
            ];

            // Log the API call
            $this->logAPICalls('getDropdownOptionsCategory', "", $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown data.',
                'error' => $e->getMessage()
            ];

            // Log the error
            $this->logAPICalls('getDropdownOptionsCategory', "", $request->all(), $response);

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
