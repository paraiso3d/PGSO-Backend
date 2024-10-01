<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ApiLog;
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
            $validator = Category::validateCategory($request->all());

            if ($validator->fails()) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors()
                ];
                $this->logAPICalls('createCategory', "", $request->all(), $response);
                return response()->json($response, 422); 
            }

           {
                $categories[] = Category::create([
                    'category_name' => $request->category_name,
                    'division' => $request->division,
                ]);
            }

            $response = [
                'isSuccess' => true,
                'message' => 'Categories successfully created.',
                'category' => $categories
            ];
            $this->logAPICalls('createCategory', "", $request->all(), $response);
            return response()->json($response, 201);  // 201 for successful resource creation
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to create the Categories.',
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('createCategory', "", $request->all(), $response);
            return response()->json($response, 500);  // 500 for internal server error
        }
    }

    public function getCategory(Request $request)
    {
        try {
            $validated = $request->validate([
                'per_page' => 'nullable|integer',
                'is_archived' => 'nullable|in:A,I', 
                'search' => 'nullable|string',      
            ]);
            
        
            $query = Category::select('id', 'category_name', 'description', 'division');
    
         
            if (!empty($validated['is_archived'])) {
                $query->where('is_archived', $validated['is_archived']);
            } else {
        
                $query->where('is_archived', 'A');
            }
    
            if (!empty($validated['search'])) {
                $query->where('category_name', 'like', '%' . $validated['search'] . '%');
            }
    
    
            $perPage = $validated['per_page'] ?? 10;  

            $categories = $query->paginate($perPage);
    
            $response = [
                'isSuccess' => true,
                'message' => 'Categories retrieved successfully.',
                'categories' => $categories->items(), // Get the paginated items
                'pagination' => [
                    'total' => $categories->total(),
                    'per_page' => $categories->perPage(),
                    'current_page' => $categories->currentPage(),
                    'last_page' => $categories->lastPage(),
                    'next_page_url' => $categories->nextPageUrl(),
                    'prev_page_url' => $categories->previousPageUrl(),
                ]
            ];
    
            // Log the API call
            $this->logAPICalls('getCategory', "", $request->all(), $response);
    
            return response()->json($response, 200);
    
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve the categories.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('getCategory', "", $request->all(), $response);
    
            return response()->json($response, 500);
        }
    }
    
    /**
     * Update an existing Category
     */
    public function updateCategory(Request $request, $id)
    {
        try {
            $category = Category::findOrFail($id);

            // Validate the request using the model's static method
            $validator = Category::validateCategory($request->all());

            // Check if validation fails
            if ($validator->fails()) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors()
                ];
                $this->logAPICalls('updateCategory', "", $request->all(), $response);
                return response()->json($response, 422);  // 422 for validation errors
            }

            // Update category (handles single category updates)
            $category->update([
                'category_name' => $request->category_name[0],  // Updating the first name in case of array
                'division' => $request->division,
            ]);

            $response = [
                'isSuccess' => true,
                'message' => "Category successfully updated.",
                'category' => $category
            ];
            $this->logAPICalls('updateCategory', $id, $request->all(), $response);
            return response()->json($response, 200);  // 200 for successful updates
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to update the Category.",
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('updateCategory', "", $request->all(), $response);
            return response()->json($response, 500);  // 500 for internal server error
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
