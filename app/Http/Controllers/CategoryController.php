<?php


namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ApiLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CategoryController extends Controller
{
    /**
     * Create a new college office.
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
                return response()->json($response, 500);
            }

            $userAccount = Category::create([
                'category_name' => $request->category_name,
                'division' => $request->division,
            ]);

            $response = [
                'isSuccess' => true,
                'message' => 'Category successfully created.',
                'data' => $userAccount
            ];
            $this->logAPICalls('createCategory', $userAccount->id, $request->all(), $response);
            return response()->json($response, 200);
        }
        catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to create the Category.',
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('createCategory', "", $request->all(), $response);
            return response()->json($response, 500);
        }
    }

    /**
     * Update an existing college office.
     */
    public function updateCategory(Request $request, $id)
    {
        try {
            $categoryname = Category::findOrFail($id);

            $request->validate([
                'category_name' => ['sometimes', 'required', 'string'],
                'division' => ['sometimes', 'required', 'string'],
            ]);

            $categoryname->update([
                'category_name' => $request->category_name,
                'division' => $request->division,
            ]);

            $response = [
                'isSuccess' => true,
                'message' => "Category successfully updated.",
                'data' => $categoryname
            ];
            $this->logAPICalls('updateCategory', $id, $request->all(), [$response]);
            return response()->json($response, 200);
        } 
        catch (ValidationException $v) {
            $response = [
                'isSuccess' => false,
                'message' => "Invalid input data.",
                'error' => $v->errors()
            ];
            $this->logAPICalls('updateCategory', "", $request->all(), [$response]);
            return response()->json($response, 500);
        } 
        catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to update the Category.",
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('updateCategory', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
    }

    /**
     * Get all college offices.
     */
    public function getCategories()
    {
        try {
            $categoryname = Category::all();

            $response = [
                'isSuccess' => true,
                'message' => "Category names list:",
                'data' => $categoryname
            ];
            $this->logAPICalls('getCategories', "", [], [$response]);
            return response()->json($response, 200);
        } 
        catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to retrieve Category Names.",
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('list_of_category', "", [], [$response]);
            return response()->json($response, 500);
        }
    }

    /**
     * Delete a college office.
     */
    public function deleteCategory($id)
    {
        try {
            $categoryname = Category::findOrFail($id);

            $categoryname->delete();

            $response = [
                'isSuccess' => true,
                'message' => "Category successfully deleted."
            ];
            $this->logAPICalls('deleteCategory', $id, [], [$response]);
            return response()->json($response, 200);
        } 
        catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to delete the Category Name.",
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('deleteCategory', "", [], [$response]);
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
