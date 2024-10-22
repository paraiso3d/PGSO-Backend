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
                return response()->json($response, 500);
            }
            $divisionId = $request->input('division_id');
            $division = Division::findOrFail($divisionId);

            // Create the category, setting the division_name based on division_id
            $category = Category::create([
                'category_name' => $request->category_name,
                'division_id' => $division->id,
            ]);

            $response = [
                $response = [
                    'isSuccess' => true,
                    'message' => 'UserAccount successfully created.',
                    'category' => [
                        'id' => $category->id,
                        'category_name' => $category->category_name,
                        'division_name' => $division->div_name,
                        'division_id' => $division->id,
                    ]
                ]
            ];
            $this->logAPICalls('createCategory', "", $request->all(), $response);
            return response()->json($response, 200);
        } catch (Throwable $e) {
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
            // Validate request parameters
            $validated = $request->validate([
                'per_page' => 'nullable|integer',
                'search' => 'nullable|string',
            ]);

            // Build the query to retrieve categories with their related division
            $query = Category::with(['divisions:id,div_name'])
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

            // Pagination settings
            $perPage = $validated['per_page'] ?? 10;
            $categories = $query->paginate($perPage);

            // If no categories found, return an error message
            if ($categories->isEmpty()) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'No active categories found matching the criteria.',
                ], 500);
            }

            // Prepare the formatted response to group by categories first
            $formattedResponse = $categories->getCollection()->map(function ($category) {
                return [
                    'id' => $category->id,
                    'category_name' => $category->category_name,
                    'is_archived' => $category->is_archived,
                    'division_name' => optional($category->divisions)->div_name, // Fetch the division name
                ];
            });

            // Structure the full response with pagination details
            $response = [
                'isSuccess' => true,
                'message' => 'Categories retrieved successfully.',
                'category' => $formattedResponse,
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

            // Return the successful response
            return response()->json($response, 200);

        } catch (Throwable $e) {
            // Handle any exceptions and return a failure message
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve the categories.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('getCategory', "", $request->all(), $response);

            // Return the error response
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


            $validator = Category::updatevalidateCategory($request->all());


            if ($validator->fails()) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ];
                $this->logAPICalls('updateCategory', "", $request->all(), $response);
                return response()->json($response, 422);  // Return 422 for validation errors
            }


            $divisionId = $request->input('division_id');
            $division = Division::findOrFail($divisionId);


            $category->update([
                'category_name' => $request->input('category_name', $category->category_name), // Keep existing if not provided
                'division_id' => $division->id
            ]);


            $response = [
                'isSuccess' => true,
                'message' => "Category successfully updated",
                'category' => [
                    'id' => $category->id,
                    'category_name' => $category->category_name,
                    'division_name' => $division->div_name,
                    'division_id' => $division->id,
                ]
            ];


            $this->logAPICalls('updateCategory', $id, $request->all(), $response);
            return response()->json($response, 200);

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
            $category->update(['is_archived' => "I"]);

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
            $divisions = Division::select('id', 'div_name')
                ->where('is_archived', 'A')
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
