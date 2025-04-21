<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLogger;
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
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Unauthorized. Please log in.',
            ], 401);
        }

        // Validate
        $request->validate([
            'category_name' => 'required|string|unique:categories,category_name',
            'description' => 'required|string',
            'personnel_ids' => 'required|array',
            'personnel_ids.*' => 'required|exists:users,id',
            'teamlead_ids' => 'required|array',  // Allow multiple team leads
            'teamlead_ids.*' => 'exists:users,id', // Ensure all team leads are valid users
        ]);

        $personnelIds = $request->personnel_ids;
        $teamleadIds = $request->teamlead_ids;

        // Merge and check for assignment conflicts
        $allIds = array_unique(array_merge($personnelIds, $teamleadIds));

        $alreadyAssigned = DB::table('category_personnel')
            ->whereIn('personnel_id', $allIds)
            ->exists();

        if ($alreadyAssigned) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'One or more personnel are already assigned to another category.',
            ], 422);
        }

        // Validate all are "personnel" role
        $personnel = User::whereIn('id', $allIds)
            ->where('role_name', 'personnel')
            ->get();

        if ($personnel->count() !== count($allIds)) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Some IDs do not belong to users with the role of personnel.',
            ], 422);
        }

        // Create category
        $category = Category::create([
            'category_name' => $request->category_name,
            'description' => $request->description,
            'status' => 'Active',
        ]);

        // Attach personnel and set team leads
        foreach ($personnelIds as $personnelId) {
            $category->personnel()->attach($personnelId, [
                'is_team_lead' => in_array($personnelId, $teamleadIds)
            ]);
        }

        // Build response
        $response = [
            'isSuccess' => true,
            'message' => 'Category successfully created.',
            'category' => [
                'id' => $category->id,
                'category_name' => $category->category_name,
                'description' => $category->description,
                'status' => 'Active',
                'personnel' => $personnel->map(function ($p) use ($teamleadIds) {
                    return [
                        'id' => $p->id,
                        'name' => $p->first_name . ' ' . $p->last_name,
                        'is_team_lead' => in_array($p->id, $teamleadIds),
                    ];
                }),
            ],
        ];

        $this->logAPICalls('createCategory', $user->email, $request->all(), [$response]);
        AuditLogger::log('Created Category', 'N/A', 'Active');

        return response()->json($response, 200);

    } catch (ValidationException $v) {
        $response = [
            'isSuccess' => false,
            'message' => 'Validation failed.',
            'errors' => $v->errors()
        ];
        $this->logAPICalls('createCategory', $user->email ?? 'unknown', $request->all(), [$response]);
        return response()->json($response, 422);

    } catch (Throwable $e) {
        $response = [
            'isSuccess' => false,
            'message' => 'Failed to create the Category.',
            'error' => $e->getMessage()
        ];
        $this->logAPICalls('createCategory', $user->email ?? 'unknown', $request->all(), [$response]);
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

        $search = $validated['search'] ?? null;

        // Build the query to fetch category data and load personnel relationship
        $query = Category::with(['personnel:id,first_name,last_name'])
            ->select('id', 'category_name', 'description', 'created_at', 'updated_at')
            ->where('is_archived', '0');

        // Add enhanced search functionality
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('category_name', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%')
                  ->orWhereHas('personnel', function ($personnelQuery) use ($search) {
                      $personnelQuery->where('first_name', 'like', '%' . $search . '%')
                                     ->orWhere('last_name', 'like', '%' . $search . '%');
                  });
            });
        }

        // Paginate results
        $perPage = $validated['per_page'] ?? 10;
        $categories = $query->paginate($perPage);

        

        // Format the response
        $response = [
            'isSuccess' => true,
            'message' => 'Categories retrieved successfully.',
            'categories' => $categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'category_name' => $category->category_name,
                    'description' => $category->description,
                    'created_at' => $category->created_at ? $category->created_at->format('Y-m-d H:i:s') : null,
                    'updated_at' => $category->updated_at ? $category->updated_at->format('Y-m-d H:i:s') : null,
                    'personnel' => $category->personnel->map(function ($personnel) {
                        return [
                            'id' => $personnel->id,
                            'name' => $personnel->first_name . ' ' . $personnel->last_name,
                            'is_team_lead' => $personnel->pivot->is_team_lead,
                        ];
                    }),
                ];
            }),
            'pagination' => [
                'total' => $categories->total(),
                'per_page' => $categories->perPage(),
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
            ],
        ];

        $this->logAPICalls('getCategory', "", $request->all(), $response);
        return response()->json($response, 200);

    } catch (ValidationException $v) {
        $response = [
            'isSuccess' => false,
            'message' => 'Validation failed.',
            'errors' => $v->errors(),
        ];
        $this->logAPICalls('getCategory', "", $request->all(), $response);
        return response()->json($response, 422);

    } catch (Throwable $e) {
        $response = [
            'isSuccess' => false,
            'message' => 'Failed to retrieve categories.',
            'error' => $e->getMessage(),
        ];
        $this->logAPICalls('getCategory', "", $request->all(), $response);
        return response()->json($response, 500);
    }
}




public function getCategoryArchive(Request $request)
{
    try {
        // Validate the request
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1',
            'search' => 'nullable|string',
        ]);

        $search = $validated['search'] ?? null;

        // Build the query to fetch category data and load personnel relationship
        $query = Category::with(['personnel:id,first_name,last_name'])
            ->select('id', 'category_name', 'description', 'created_at', 'updated_at')
            ->where('is_archived', '1');

        // Add enhanced search functionality
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('category_name', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%')
                  ->orWhereHas('personnel', function ($personnelQuery) use ($search) {
                      $personnelQuery->where('first_name', 'like', '%' . $search . '%')
                                     ->orWhere('last_name', 'like', '%' . $search . '%');
                  });
            });
        }

        // Paginate results
        $perPage = $validated['per_page'] ?? 10;
        $categories = $query->paginate($perPage);

        // Format the response
        $response = [
            'isSuccess' => true,
            'message' => 'Categories retrieved successfully.',
            'categories' => $categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'category_name' => $category->category_name,
                    'description' => $category->description,
                    'created_at' => $category->created_at ? $category->created_at->format('Y-m-d H:i:s') : null,
                    'updated_at' => $category->updated_at ? $category->updated_at->format('Y-m-d H:i:s') : null,
                    'personnel' => $category->personnel->map(function ($personnel) {
                        return [
                            'id' => $personnel->id,
                            'name' => $personnel->first_name . ' ' . $personnel->last_name,
                            'is_team_lead' => $personnel->pivot->is_team_lead,
                        ];
                    }),
                ];
            }),
            'pagination' => [
                'total' => $categories->total(),
                'per_page' => $categories->perPage(),
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
            ],
        ];

        $this->logAPICalls('getCategoryArchive', "", $request->all(), $response);
        return response()->json($response, 200);

    } catch (ValidationException $v) {
        $response = [
            'isSuccess' => false,
            'message' => 'Validation failed.',
            'errors' => $v->errors(),
        ];
        $this->logAPICalls('getCategoryArchive', "", $request->all(), $response);
        return response()->json($response, 422);

    } catch (Throwable $e) {
        $response = [
            'isSuccess' => false,
            'message' => 'Failed to retrieve categories.',
            'error' => $e->getMessage(),
        ];
        $this->logAPICalls('getCategoryArchive', "", $request->all(), $response);
        return response()->json($response, 500);
    }
}





    /**
     Update an existing Category
     */
    public function updateCategory(Request $request, $id)
    {
        try {
            $user = auth()->user();
    
            if (!$user) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Unauthorized. Please log in.',
                ], 401);
            }
    
            // Find the category
            $category = Category::findOrFail($id);
    
            // Validate the request
            $request->validate([
                'category_name' => 'sometimes|required|string|unique:categories,category_name,' . $id,
                'description' => 'sometimes|required|string',
                'personnel_ids' => 'sometimes|required|array',
                'personnel_ids.*' => 'exists:users,id',
                'teamlead_ids' => 'sometimes|required|array',
                'teamlead_ids.*' => 'exists:users,id',
            ]);
    
            $personnelIds = $request->personnel_ids ?? $category->personnel->pluck('id')->toArray();
            $teamleadIds = $request->teamlead_ids ?? [];
    
            $allIds = array_unique(array_merge($personnelIds, $teamleadIds));
    
            // Validate all are personnel
            $personnel = User::whereIn('id', $allIds)
                ->where('role_name', 'personnel')
                ->get();
    
            if ($personnel->count() !== count($allIds)) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Some IDs do not belong to users with the role of personnel.',
                ], 422);
            }
    
            // Check for assignment conflicts (excluding current category)
            $alreadyAssigned = DB::table('category_personnel')
                ->whereIn('personnel_id', $allIds)
                ->where('category_id', '!=', $id)
                ->exists();
    
            if ($alreadyAssigned) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'One or more personnel are already assigned to another category.',
                ], 422);
            }
    
            // Store old data for audit
            $oldData = json_encode($category->toArray());
    
            // Update the category fields
            $category->update([
                'category_name' => $request->category_name ?? $category->category_name,
                'description' => $request->description ?? $category->description,
            ]);
    
            // Sync personnel with team lead flags
            $syncData = [];
            foreach ($personnelIds as $personnelId) {
                $syncData[$personnelId] = [
                    'is_team_lead' => in_array($personnelId, $teamleadIds),
                ];
            }
            $category->personnel()->sync($syncData);
    
            // Prepare response
            $response = [
                'isSuccess' => true,
                'message' => 'Category successfully updated.',
                'category' => [
                    'id' => $category->id,
                    'category_name' => $category->category_name,
                    'description' => $category->description,
                    'personnel' => $personnel->map(function ($p) use ($teamleadIds) {
                        return [
                            'id' => $p->id,
                            'name' => $p->first_name . ' ' . $p->last_name,
                            'is_team_lead' => in_array($p->id, $teamleadIds),
                        ];
                    }),
                ],
            ];
    
            $this->logAPICalls('updateCategory', $user->email, $request->all(), [$response]);
    
            AuditLogger::log(
                'Updated Category',
                $oldData,
                'test',
                'Active'
            );
    
            return response()->json($response, 200);
    
        } catch (ValidationException $v) {
            $response = [
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $v->errors(),
            ];
            $this->logAPICalls('updateCategory', $user->email ?? 'unknown', $request->all(), [$response]);
            return response()->json($response, 422);
    
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to update the Category.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('updateCategory', $user->email ?? 'unknown', $request->all(), [$response]);
            return response()->json($response, 500);
        }
    }
    


    /**
     * Delete a Category by ID
     */
    public function deleteCategory($id)
    {
        try {
            // Get authenticated user
            $user = auth()->user();
    
            if (!$user) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Unauthorized. Please log in.',
                ], 401);
            }
    
            // Find the category
            $category = Category::findOrFail($id);
    
            // Store old data for audit log
            $oldData = json_encode($category->toArray());
    
            // Mark category as archived
            $category->update(['is_archived' => "1"]);
    
            // Update pivot table: set is_team_lead and personnel_id to null
            DB::table('category_personnel')
                ->where('category_id', $category->id)
                ->update([
                    'is_team_lead' => null,
                    'personnel_id' => null,
                ]);
    
            // Prepare success response
            $response = [
                'isSuccess' => true,
                'message' => 'Category successfully deleted.',
            ];
    
            // Log API call
            $this->logAPICalls('deleteCategory', $user->email, [], $response);
    
            // Log Audit Trail
            AuditLogger::log(
                'Deleted Category',
                $oldData, // Old category data before deletion
                'Deleted'
            );
    
            return response()->json($response, 200);
    
        } catch (Throwable $e) {
            // Prepare error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to delete the Category.',
                'error' => $e->getMessage()
            ];
    
            // Log API call
            $this->logAPICalls('deleteCategory', $user->email ?? 'unknown', [], $response);
            return response()->json($response, 500);
        }
    }
    


    public function restoreCategory($id)
    {
        try {
            // Get authenticated user
            $user = auth()->user();

            if (!$user) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Unauthorized. Please log in.',
                ], 401);
            }

            // Find the category
            $category = Category::findOrFail($id);

            // Store old data for audit log
            $oldData = json_encode($category->toArray());

            // Mark category as archived
            $category->update(['is_archived' => "0"]);

            // Prepare success response
            $response = [
                'isSuccess' => true,
                'message' => 'Category successfully restore.'
            ];

            // Log API call
            $this->logAPICalls('restoreCategory', $user->email, [], $response);
            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Prepare error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to restore the Category.',
                'error' => $e->getMessage()
            ];

            // Log API call
            $this->logAPICalls('restoreCategory', $user->email ?? 'unknown', [], $response);
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
