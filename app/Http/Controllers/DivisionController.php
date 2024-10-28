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
            
            $request->validate([
                'div_name' => 'required|string|unique:divisions,div_name',
                'note' => 'required|string',
                'categories' => 'required|array',
            ]);
    
           
            $division = Division::create([
                'div_name' => $request->div_name,
                'note' => $request->note,
                'category_id' => json_encode($request->categories),
            ]);
    
            $assignedCategories = Category::whereIn('id', $request->categories)
                ->select('id', 'category_name')
                ->get();
    
            
            $response = [
                'isSuccess' => true,
                'message' => 'Division created successfully.',
                'division' => [
                    'id' => $division->id,
                    'div_name' => $division->div_name,
                    'note' => $division->note,
                    'categories' => $assignedCategories,
                ],
            ];
    
           
            $this->logAPICalls('createDivision', $division->id, $request->all(), [$response]);
    
           
            return response()->json($response, 200);
    
        } catch (ValidationException $v) {
          
            $response = [
                'isSuccess' => false,
                'message' => 'Invalid input data.',
                'error' => $v->errors(),
            ];
            $this->logAPICalls('createDivision', "", $request->all(), [$response]);
            return response()->json($response, 500);
    
        } catch (Throwable $e) {
            
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
                'note' => ['sometimes','string'],
                'categories' => ['sometimes', 'required', 'array']
            ]); 

            // Store the old division name before updating
            $oldDivName = $division->div_name;

            // Update the division
            $division->update([
                'div_name' => $request->div_name,
                'note' => $request->note,
                'category_id' => json_encode($request->categories),
            ]);

            $categoryIds = json_decode($division->category_id, true);
            $categories = Category::whereIn('id', $categoryIds)
                ->select('id', 'category_name')
                ->get();

                $response = [
                    'isSuccess' => true,
                    'message' => "Division successfully updated, and associated categories updated.",
                    'division' => [
                        'id' => $division->id,
                        'div_name' => $division->div_name,
                        'note' => $division->note,
                        'is_archived' => $division->is_archived,
                        'category_id' => $division->category_id,
                        'categories' => $categories,
                        'manpower_id' => $division->manpower_id,
                        'inspection_report_id' => $division->inspection_report_id,
                        'teamleader_user_id' => $division->teamleader_user_id,
                        'created_at' => $division->created_at,
                        'updated_at' => $division->updated_at,
                    ],
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



    public function getdropdownCategory()
{
    try {
        // Retrieve categories with relevant fields
        $categories = Category::select('id', 'category_name')->get();

        $response = [
            'isSuccess' => true,
            'message' => 'Dropdown options retrieved successfully.',
            'data' => $categories,
        ];

        // Log the API call
        $this->logAPICalls('getDropdownOptions', null, [], [$response]);

        return response()->json($response, 200);
    } catch (Throwable $e) {
        $response = [
            'isSuccess' => false,
            'message' => 'Failed to retrieve dropdown options.',
            'error' => $e->getMessage(),
        ];
        $this->logAPICalls('getDropdownOptions', null, [], [$response]);
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
        $division = $query->paginate($perPage);

       
        if ($division->isEmpty()) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'No active categories found matching the criteria.',
            ], 500);
        }

       
        $groupedCategories = $division->getCollection()->groupBy(function ($category) {
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
                'total' => $division->total(),
                'per_page' => $division->perPage(),
                'current_page' => $division->currentPage(),
                'last_page' => $division->lastPage(),
                'next_page_url' => $division->nextPageUrl(), 
                'prev_page_url' => $division->previousPageUrl(), 
                'url' => url('api/categoryList?page=' . $division->currentPage() . '&per_page=' . $division->perPage()),
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

