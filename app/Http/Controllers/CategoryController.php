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
            // Validate the incoming request
            $validated = $request->validate([
                'per_page' => 'nullable|integer',
                'search' => 'nullable|string',   // Search term
            ]);

            // Start building the query to select categories
            $query = Category::select('id', 'category_name', 'description', 'division');

            // Always filter by the hidden is_archived field (default to 'A' for active categories)
            $query->where('is_archived', 'A');

            // If a search term is provided, search in both category_name and division
            if (!empty($validated['search'])) {
                $query->where(function ($q) use ($validated) {
                    $q->where('category_name', 'like', '%' . $validated['search'] . '%')
                        ->orWhere('division', 'like', '%' . $validated['search'] . '%');
                });
            }

            // Set pagination: use provided per_page value or default to 10
            $perPage = $validated['per_page'] ?? 10;
            $categories = $query->paginate($perPage);

            // Create the response with pagination details
            $response = [
                'isSuccess' => true,
                'message' => 'Categories retrieved successfully.',
                'categories' => $categories, // Return the paginated items
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
                'division' => $division->div_name,  // Setting division name based on division_id
                'division_id' => $division->id      // Use the division_id
            ]);

            // Prepare success response
            $response = [
                'isSuccess' => true,
                'message' => "Category successfully updated",
                'category' => $category, // Return the updated category
            ];

            // Log the API call and return the success response
            $this->logAPICalls('updateCategory', $id, $request->all(), $response);
            return response()->json($response, 200);  // 200 for successful updates
        } catch (Throwable $e) {
            // Prepare error response in case of an exception
            $response = [
                'isSuccess' => false,
                'message' => "Failed to update the Category.",
                'error' => $e->getMessage(),
            ];

            // Log the error and return the error response
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
