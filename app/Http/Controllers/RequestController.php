<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Department;
use App\Models\Location;
use App\Models\User;
use Exception;
use DB;
use App\Models\Division;
use App\Models\Office;
use App\Models\Requests;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;
use Throwable;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;


class RequestController extends Controller
{
    // Method to create a new request

    public function createRequest(Request $request)
    {
        // Validate the incoming data using the Requests model's validateRequest method
        $validator = Requests::validateRequest($request->all());
    
        if ($validator->fails()) {
            $response = [
                'isSuccess' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ];
            $this->logAPICalls('createRequest', '', [], $response);
    
            return response()->json($response, 400);
        }
    
        $controlNo = Requests::generateControlNo();
        $filePath = null;
        $fileUrl = null;
    
        if ($request->hasFile('file_path')) {
            // Get the uploaded file
            $file = $request->file('file_path');
    
            // Define the target directory and file name
            $directory = public_path('img/asset');
            $fileName = 'Request-' . $controlNo . '-' . now()->format('YmdHis') . '.' . $file->getClientOriginalExtension();
    
            // Ensure the directory exists
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }
    
            // Move the file to the target directory
            $file->move($directory, $fileName);
    
            // Generate the relative file path
            $filePath = 'img/asset/' . $fileName;
    
            // Generate the file URL
            $fileUrl = asset($filePath);
        }
    
        try {
            $user = auth()->user();
    
            // Extract user details
            $firstName = $user->first_name ?? 'N/A';
            $lastName = $user->last_name ?? 'N/A';
            $division = $user->division->division_name ?? 'N/A'; // Assumes user has a relation to division
            $department = $user->department->department_name ?? 'N/A'; // Assumes user has a relation to department
            $officeLocation = $user->division->office_location ?? 'N/A'; // Fetching office location
    
            // Get the current timestamp using Carbon
            $dateRequested = Carbon::now();
    
            // Create the new request record
            $newRequest = Requests::create([
                'control_no' => $controlNo,
                'request_title' => $request->input('request_title'),
                'description' => $request->input('description'),
                'category' => $request->input('category'),
                'file_path' => $filePath,
                'status' => $request->input('status', 'Pending'),
                'requested_by' => $user->id,
                'date_requested' => $dateRequested, // Save Carbon instance
                'is_archived' => false,
            ]);
    
            $response = [
                'isSuccess' => true,
                'message' => 'Request successfully created.',
                'request' => [
                    'id' => $newRequest->id,
                    'control_no' => $controlNo,
                    'request_title' => $newRequest->request_title,
                    'description' => $newRequest->description,
                    'category' => $newRequest->category,
                    'file_url' => $fileUrl,
                    'status' => $newRequest->status,
                    'requested_by' => [
                        'id' => $user->id,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'division' => $division,
                        'department' => $department,
                        'office_location' => $officeLocation,
                    ],
                    'date_requested' => $dateRequested->format('d-m-Y'), // Format with day, month, and year
                ],
            ];
    
            $this->logAPICalls('createRequest', $newRequest->id, [], $response);
    
            return response()->json($response, 201);
    
        } catch (Throwable $e) {
            // Handle any exceptions
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to create the request.',
                'error' => $e->getMessage(),
            ];
    
            $this->logAPICalls('createRequest', '', [], $response);
    
            return response()->json($response, 500);
        }
    }

    public function acceptRequest($id)
    {
        try {
            // Find the request by its ID
            $requestRecord = Requests::findOrFail($id);

            // Update the status and set note column to null
            $requestRecord->status = 'For Process';
            $requestRecord->note = null;
            $requestRecord->save();

            // Fetch the user who made the request
            $user = $requestRecord->user;

            // Fetch user's division and office location
            $division = $user->division ? $user->division->division_name : 'N/A';
            $officeLocation = $user->division ? $user->division->office_location : 'N/A';

            // Prepare the response
            $response = [
                'isSuccess' => true,
                'message' => 'Request has been accepted and is now For Review.',
                'request' => [
                    'id' => $requestRecord->id,
                    'control_no' => $requestRecord->control_no,
                    'status' => $requestRecord->status,
                    'requested_by' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name ?? 'N/A',
                        'last_name' => $user->last_name ?? 'N/A',
                        'division' => $division,
                        'office_location' => $officeLocation,
                    ],
                    'date_accepted' => now()->toDateTimeString(),
                ],
            ];

            $this->logAPICalls('acceptRequest', "", [], $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to accept the request.',
                'error' => $e->getMessage(),
            ];

            $this->logAPICalls('acceptRequest', "", [], $response);

            return response()->json($response, 500);
        }
    }

    // Reject a request and change its status to "Returned"
    public function rejectRequest(Request $request, $id)
    {
        try {
            // Validate the note input
            $request->validate([
                'note' => 'required|string|max:255',
            ]);

            // Find the request by its ID
            $requestRecord = Requests::findOrFail($id);

            // Update the status and note
            $requestRecord->status = 'Returned';
            $requestRecord->note = $request->note; // Save the note to the database
            $requestRecord->save();

            // Fetch the user who made the request
            $user = $requestRecord->user;

            $division = $user->division ? $user->division->division_name : 'N/A';
            $officeLocation = $user->division ? $user->division->office_location : 'N/A';


            // Prepare the response
            $response = [
                'isSuccess' => true,
                'message' => 'Request has been rejected and is now Returned.',
                'request' => [
                    'id' => $requestRecord->id,
                    'control_no' => $requestRecord->control_no,
                    'status' => $requestRecord->status,
                    'note' => $requestRecord->note, // Include the note in the response
                    'requested_by' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name ?? 'N/A',
                        'last_name' => $user->last_name ?? 'N/A',
                        'division' => $division,
                        'office_location' => $officeLocation,
                    ],
                    'date_rejected' => now()->toDateTimeString(),
                ],
            ];

            $this->logAPICalls('rejectRequest', "", $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to reject the request.',
                'error' => $e->getMessage(),
            ];

            $this->logAPICalls('rejectRequest', "", $request->all(), $response);

            return response()->json($response, 500);
        }
    }
    public function getRequests(Request $request)
    {
        try {
            $userId = $request->user()->id;
            $role = $request->user()->role_name;
    
            // Pagination settings
            $perPage = $request->input('per_page', 10);
            $searchTerm = $request->input('search', null);
    
            // Initialize query
            $query = Requests::select(
                'requests.id',
                'requests.control_no',
                'requests.request_title',
                'requests.description',
                'requests.file_path',
                'requests.file_completion',  // Added field
                'requests.category_id',
                'requests.feedback',
                'requests.rating',  // Added field
                'requests.status',
                'requests.date_requested',
                'requests.date_completed',
                'requests.personnel_ids',
                'users.id as requested_by_id',
                'users.first_name',
                'users.last_name',
                'divisions.division_name',
                'divisions.office_location',
                'departments.department_name'
            )
                ->leftJoin('users', 'users.id', '=', 'requests.requested_by')
                ->leftJoin('divisions', 'divisions.id', '=', 'users.division_id')
                ->leftJoin('departments', 'departments.id', '=', 'users.department_id')
                ->where('requests.is_archived', '=', '0')
                ->when($searchTerm, function ($query, $searchTerm) {
                    return $query->where('requests.control_no', 'like', '%' . $searchTerm . '%');
                });
    
            // Apply filters
            if ($request->has('type')) {
                if ($request->type === 'Returned') {
                    $query->where('requests.status', 'Returned');
                } elseif ($request->type === 'For Overtime') {
                    $query->where('requests.overtime', '>', 0);
                }
            }
    
            $query->when($request->filled('location'), function ($query) use ($request) {
                $query->where('requests.location_name', $request->location);
            })
                ->when($request->filled('status'), function ($query) use ($request) {
                    $query->where('requests.status', $request->status);
                })
                ->when($request->filled('category'), function ($query) use ($request) {
                    $query->where('requests.category_id', $request->category);
                })
                ->when($request->filled('year'), function ($query) use ($request) {
                    $query->where('requests.fiscal_year', $request->year);
                });
    
            // Role-based filtering
            switch ($role) {
                case 'admin':
                    break;
                case 'head':
                    $query->whereIn('requests.status', ['Pending']);
                    break;
                case 'staff':
                    $query->where('requests.requested_by', $userId);
                    break;
                case 'personnel':
                    $query->whereIn('requests.status', ['For Completion','Completed']);
                    break;
                default:
                    $query->whereRaw('1 = 0');
                    break;
            }
    
            // Execute query with pagination
            $result = $query->paginate($perPage);
    
            if ($result->isEmpty()) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'No requests found matching the criteria.'
                ], 404);
            }
    
            // Format response
            $formattedRequests = $result->getCollection()->transform(function ($request) {
                $personnelIds = json_decode($request->personnel_ids, true) ?? [];
                $personnelInfo = User::whereIn('id', $personnelIds)
                    ->select('id', DB::raw("CONCAT(first_name, ' ', last_name) as name"))
                    ->get();
                return [
                    'id' => $request->id,
                    'control_no' => $request->control_no,
                    'request_title' => $request->request_title,
                    'description' => $request->description,
                    'file_path' => $request->file_path,
                    'file_url' => $request->file_path ? asset($request->file_path) : null,
                    'file_completion' => $request->file_completion,  // Added field
                    'file_completion_url' => $request->file_completion ? asset($request->file_completion) : null,  // Added field
                    'category_id' => $request->category_id,
                    'category_name' => $request->category_id
                        ? DB::table('categories')->where('id', $request->category_id)->value('category_name')
                        : null,
                    'personnel' => $personnelInfo->map(function ($personnel) {
                        return [
                            'id' => $personnel->id,
                            'name' => $personnel->name,
                        ];
                    }),
                    'feedback' => $request->feedback,
                    'rating' => $request->rating,  // Added field
                    'status' => $request->status,
                    'requested_by' => [
                        'id' => $request->requested_by_id,
                        'first_name' => $request->first_name,
                        'last_name' => $request->last_name,
                        'division' => $request->division_name,
                        'office_location' => $request->office_location,
                        'department' => $request->department_name,
                    ],
                    'date_requested' => $request->date_requested,
                    'date_completed' => $request->date_completed
                ];
            });
    
            return response()->json([
                'isSuccess' => true,
                'message' => 'Requests retrieved successfully.',
                'requests' => $formattedRequests,
                'pagination' => [
                    'total' => $result->total(),
                    'per_page' => $result->perPage(),
                    'current_page' => $result->currentPage(),
                    'last_page' => $result->lastPage(),
                ],
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to retrieve requests.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    




    // Method to delete (archive) a request
    public function getRequestById(Request $request, $id)
    {
        try {
            // Find the request by ID with category and user join
            $requestDetails = Requests::select(
                'requests.id',
                'requests.control_no',
                'requests.request_title',
                'requests.requested_by',
                'users.first_name as requested_by_first_name',
                'users.last_name as requested_by_last_name',
                'users.email as requested_by_email', // Include email
                'requests.description',
                'requests.file_path',
                'requests.file_completion', // Include completion file path
                'requests.category_id',
                'categories.category_name', // Fetch category name directly
                'requests.feedback',
                'requests.status',
                'requests.date_requested',
                'requests.date_completed', // Include date completed
                DB::raw("DATE_FORMAT(requests.updated_at, '%Y-%m-%d') as updated_at")
            )
                ->leftJoin('users', 'users.id', '=', 'requests.requested_by') // Join with users table to fetch user details
                ->leftJoin('categories', 'categories.id', '=', 'requests.category_id')
                ->where('requests.id', $id)
                ->where('requests.is_archived', '=', '0') // Filter out archived requests
                ->first();
    
            // Check if the request exists
            if (!$requestDetails) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'Request not found.',
                ];
                $this->logAPICalls('getRequestById', $id, [], $response);
    
                return response()->json($response, 404);
            }
    
            // Format the response
            $formattedRequest = [
                'id' => $requestDetails->id,
                'control_no' => $requestDetails->control_no,
                'request_title' => $requestDetails->request_title,
                'requested_by' => $requestDetails->requested_by,
                'requested_by_name' => $requestDetails->requested_by_first_name . ' ' . $requestDetails->requested_by_last_name,
                'requested_by_email' => $requestDetails->requested_by_email, // Add requested by email
                'description' => $requestDetails->description,
                'category_id' => $requestDetails->category_id,
                'category_name' => $requestDetails->category_name, // Include category name
                'feedback' => $requestDetails->feedback,
                'status' => $requestDetails->status,
                'file_path' => $requestDetails->file_path,
                'file_url' => $requestDetails->file_path ? asset($requestDetails->file_path) : null,
                'file_completion' => $requestDetails->file_completion, // Add completion file
                'file_completion_url' => $requestDetails->file_completion ? asset($requestDetails->file_completion) : null, // Add completion file URL
                'date_requested' => $requestDetails->date_requested,
                'date_completed' => $requestDetails->date_completed, // Include date completed
                'updated_at' => $requestDetails->updated_at,
            ];
    
            // Successful response
            $response = [
                'isSuccess' => true,
                'message' => 'Request retrieved successfully.',
                'request' => $formattedRequest,
            ];
            $this->logAPICalls('getRequestById', $id, [], $response);
    
            return response()->json($response, 200);
    
        } catch (Throwable $e) {
            // Error handling
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve request.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('getRequestById', $id, [], $response);
    
            return response()->json($response, 500);
        }
    }
    
    public function assessRequest(Request $request, $id)
    {
        try {
            // Retrieve the currently logged-in user
            $user = auth()->user();
    
            // Retrieve the request record using the ID from the URL
            $requests = Requests::where('id', $id)->firstOrFail();
    
            // Validate the input data, including category_id and personnel_ids
            $validatedData = $request->validate([
                'category_id' => 'required|exists:categories,id',
                'personnel_ids' => 'required|array|min:1', // Ensure personnel_ids is an array with at least one entry
                'personnel_ids.*' => 'exists:users,id', // Validate that each personnel_id exists in the users table
                'status' => 'sometimes|in:For Completion', // Optional status parameter
            ]);
    
            // Retrieve only ACTIVE personnel linked to the provided category_id
            $activePersonnelIds = DB::table('category_personnel')
                ->join('users', 'category_personnel.personnel_id', '=', 'users.id')
                ->where('category_personnel.category_id', $validatedData['category_id'])
                ->where('users.status', 'Active') // Filter only Active personnel
                ->pluck('users.id')
                ->toArray();
    
            // Check if all provided personnel_ids are linked to the category and are Active
            $invalidPersonnelIds = array_diff($validatedData['personnel_ids'], $activePersonnelIds);
    
            if (!empty($invalidPersonnelIds)) {
                throw new Exception("The following personnel IDs are either not linked to the category or not active: " . implode(', ', $invalidPersonnelIds));
            }
    
            // Determine the status to update
            $statusToUpdate = $validatedData['status'] ?? 'For Completion';
    
            // Update the request with the fetched category_id and the provided personnel_ids (as JSON)
            $requests->update([
                'status' => $statusToUpdate,
                'category_id' => $validatedData['category_id'],  // Assign the category_id
                'personnel_ids' => json_encode($validatedData['personnel_ids']), // Store multiple personnel IDs as JSON
            ]);
    
            // ✅ Update personnel status to "Assigned"
            User::whereIn('id', $validatedData['personnel_ids'])->update(['status' => 'Assigned']);
    
            // Prepare the full name of the currently logged-in user
            $fullName = trim("{$user->first_name} {$user->middle_initial} {$user->last_name}");
    
            // Prepare the response
            $response = [
                'isSuccess' => true,
                'message' => 'Request assessed successfully. Personnel status updated to Assigned.',
                'request_id' => $requests->id,
                'status' => $requests->status,
                'user_id' => $user->id,
                'user' => $fullName,
                'category' => [
                    'id' => $validatedData['category_id'],
                ],
                'personnel' => array_map(function ($personnelId) {
                    $personnel = User::find($personnelId);
                    return [
                        'id' => $personnel->id,
                        'name' => "{$personnel->first_name} {$personnel->last_name}",
                        'status' => $personnel->status, // Show updated status
                    ];
                }, $validatedData['personnel_ids']),
            ];
    
            // Log the API call
            $this->logAPICalls('assessRequest', $requests->id, $request->all(), $response);
    
            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Prepare the error response
            $response = [
                'isSuccess' => false,
                'message' => "Failed to assess the request.",
                'error' => $e->getMessage(),
            ];
    
            // Log the API call with failure response
            $this->logAPICalls('assessRequest', $id ?? '', $request->all(), $response);
    
            return response()->json($response, 500);
        }
    }
    




    public function submitCompletion(Request $request, $id)
    {
        try {
            // Find the request by its ID
            $requestRecord = Requests::findOrFail($id);
            $user = auth()->user();
            
            // Initialize variables for file path and URL
            $fileCompletionPath = null;
            $fileCompletionUrl = null;
    
            // Check if the request has a file
            if ($request->hasFile('file_completion')) {
                // Get the uploaded file
                $file = $request->file('file_completion');
    
                // Define the target directory and file name
                $directory = public_path('img/asset');
                $fileName = 'Request-' . $requestRecord->control_no . '-' . now()->format('YmdHis') . '.' . $file->getClientOriginalExtension();
    
                // Ensure the directory exists
                if (!file_exists($directory)) {
                    mkdir($directory, 0755, true);
                }
    
                // Move the file to the target directory
                $file->move($directory, $fileName);
    
                // Generate the relative file path
                $fileCompletionPath = 'img/asset/' . $fileName;
    
                // Generate the file URL
                $fileCompletionUrl = asset($fileCompletionPath);
            }
    
            // ✅ Change request status to "For Feedback"
            $requestRecord->file_completion = $fileCompletionPath;
            $requestRecord->status = 'For Feedback';
            $requestRecord->date_completed = now();
            $requestRecord->save();
    
            // ✅ Retrieve and update personnel status back to "Active"
            $personnelIds = json_decode($requestRecord->personnel_ids, true);
            if (!empty($personnelIds)) {
                User::whereIn('id', $personnelIds)->update(['status' => 'Active']);
            }
    
            // Prepare the response
            $response = [
                'isSuccess' => true,
                'message' => 'Completion file has been submitted successfully.',
                'request' => [
                    'id' => $requestRecord->id,
                    'control_no' => $requestRecord->control_no,
                    'file_completion_url' => $fileCompletionUrl, 
                    'file_completion_path' => $fileCompletionPath,
                    'status' => $requestRecord->status,
                    'date_completed' => $requestRecord->date_completed,
                    'submitted_by' => [
                        'id' => $user->id,
                        'name' => $user->first_name . ' ' . $user->last_name,
                        'email' => $user->email,
                    ],
                    'personnel_updated' => $personnelIds,
                ],
            ];
    
            // Log the API call
            $this->logAPICalls('submitCompletion', $requestRecord->id, $request->all(), $response);
    
            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Prepare the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to submit the completion file.',
                'error' => $e->getMessage(),
            ];
    
            // Log the API call
            $this->logAPICalls('submitCompletion', $id ?? '', $request->all(), $response);
    
            return response()->json($response, 500);
        }
    }
    
    public function submitFeedback(Request $request, $id)
{
    try {
        // Validate the input data
        $this->validate($request, [
            'feedback' => 'required|string|max:255',
            'rating' => 'required|numeric|min:0|max:5',
        ]);

        // Find the request by its ID
        $requestRecord = Requests::findOrFail($id);

        // Update the feedback and rating columns
        $requestRecord->feedback = $request->input('feedback');
        $requestRecord->rating = $request->input('rating');
        $requestRecord->status = 'Completed';
        $requestRecord->save();

        // Get the authenticated user
        $user = auth()->user();

        // Prepare the response
        $response = [
            'isSuccess' => true,
            'message' => 'Feedback has been submitted successfully.',
            'request' => [
                'id' => $requestRecord->id,
                'control_no' => $requestRecord->control_no,
                'feedback' => $requestRecord->feedback,
                'rating' => $requestRecord->rating,
                'status'=> $requestRecord->status,
                'rated_by' => [
                    'id' => $user->id,
                    'name' => $user->first_name . ' ' . $user->last_name,
                    'email' => $user->email,
                ],
            ],
        ];

        // Log the API call
        $this->logAPICalls('submitFeedback', $user->id, [], $response);

        return response()->json($response, 200);
    } catch (Throwable $e) {
        // Prepare the error response
        $response = [
            'isSuccess' => false,
            'message' => 'Failed to submit feedback.',
            'error' => $e->getMessage(),
        ];

        // Log the API call
        $this->logAPICalls('submitFeedback', auth()->id(), [], $response);

        return response()->json($response, 500);
    }
}




    //Dropdown Request Location
    public function getDropdownOptionsRequestslocation(Request $request)
    {
        try {

            $location = Location::select('id', 'location_name')
                ->where('is_archived', '0')
                ->get();

            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown data retrieved successfully.',
                'location' => $location
            ];

            // Log the API call
            $this->logAPICalls('getDropdownOptionsRequestslocation', "", [], $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown data.',
                'error' => $e->getMessage()
            ];

            // Log the error
            $this->logAPICalls('getDropdownOptionsRequestslocation', "", [], $response);

            return response()->json($response, 500);
        }
    }

    //Dropdown Request Status
    public function getDropdownOptionsRequeststatus(Request $request)
    {
        try {

            $status = Requests::select(DB::raw('MIN(id) as id'), 'status')
                ->whereIn('status', ['Pending', 'For Inspection', 'On-Going', 'Completed', 'Returned'])
                ->where('is_archived', '0')
                ->groupBy('status')
                ->get();

            // Build the response
            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown data retrieved successfully.',
                'status' => $status
            ];

            // Log the API call
            $this->logAPICalls('getDropdownOptionsRequeststatus', "", [], $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown data.',
                'error' => $e->getMessage()
            ];

            // Log the error
            $this->logAPICalls('getDropdownOptionsRequeststatus', "", [], $response);

            return response()->json($response, 500);
        }

    }



    public function getCategoriesWithPersonnel()
    {
        try {
            // Fetch categories with their associated personnel
            $categoriesWithPersonnel = DB::table('categories')
                ->select('categories.id', 'categories.category_name')
                ->where('categories.is_archived', '0')
                ->leftJoin('category_personnel', 'categories.id', '=', 'category_personnel.category_id')
                ->leftJoin('users', 'category_personnel.personnel_id', '=', 'users.id')
                ->select(
                    'categories.id as category_id',
                    'categories.category_name',
                    'users.id as personnel_id',
                    'users.first_name',
                    'users.last_name',
                )
                ->get()
                ->groupBy('category_id')
                ->map(function ($category) {
                    return [
                        'id' => $category[0]->category_id,
                        'category_name' => $category[0]->category_name,
                        'personnel' => $category->map(function ($personnel) {
                            return $personnel->personnel_id ? [
                                'id' => $personnel->personnel_id,
                                'name' => trim($personnel->first_name . ' ' . $personnel->last_name),
                            ] : null;
                        })->filter() // Remove null entries
                    ];
                })
                ->values();

            $response = [
                'isSuccess' => true,
                'message' => 'Categories with personnel retrieved successfully.',
                'categories' => $categoriesWithPersonnel
            ];

            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve categories and personnel.',
                'error' => $e->getMessage()
            ];

            return response()->json($response, 500);
        }
    }
    //Dropdown Request Status
    public function getUsersByCategory(Request $request)
    {
        try {
            // Validate that category_id is provided
            $request->validate([
                'category_id' => 'required|exists:categories,id'
            ]);

            $categoryId = $request->input('category_id');

            // Directly fetch personnel IDs from the pivot table
            $personnelIds = DB::table('category_personnel')
                ->where('category_id', $categoryId)
                ->pluck('personnel_id');

            // Fetch full user details for those personnel IDs
            $personnel = User::whereIn('id', $personnelIds)
                ->select('id', 'name', 'email')
                ->get();

            $response = [
                'isSuccess' => true,
                'message' => 'Personnel retrieved successfully.',
                'personnel' => $personnel
            ];

            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve personnel.',
                'error' => $e->getMessage()
            ];

            return response()->json($response, 500);
        }
    }
    //Dropdown Request Division
    public function getDropdownOptionsRequestdivision(Request $request)
    {
        try {

            $div_name = Division::select('id', 'div_name')
                ->where('is_archived', '0')
                ->get();

            // Build the response
            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown data retrieved successfully.',
                'div_name' => $div_name
            ];

            // Log the API call
            $this->logAPICalls('getDropdownOptionsRequestdivision', "", [], $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown data.',
                'error' => $e->getMessage()
            ];

            // Log the error
            $this->logAPICalls('getDropdownOptionsRequestdivision', "", [], $response);

            return response()->json($response, 500);
        }

    }

    //  Dropdown Request Category
    public function getDropdownOptionsRequestcategory(Request $request)
    {
        try {

            $category = Category::select('id', 'category_name')
                ->where('is_archived', '0')
                ->get();


            // Build the response
            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown data retrieved successfully.',
                'category' => $category
            ];

            // Log the API call
            $this->logAPICalls('getDropdownOptionsRequestcategory', "", [], $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown data.',
                'error' => $e->getMessage()
            ];

            // Log the error
            $this->logAPICalls('getDropdownOptionsRequestcategory', "", [], $response);

            return response()->json($response, 500);
        }

    }

    //Dropdown Create Request office
    public function getDropdownOptionscreateRequestsoffice(Request $request)
    {
        try {
            $office = Department::select('id', 'department_name')
                ->where('is_archived', '0')
                ->get();

            // Build the response
            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown data retrieved successfully.',
                'office' => $office,
            ];

            // Log the API call
            $this->logAPICalls('getDropdownOptionscreateRequestsoffice', "", [], $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown data.',
                'error' => $e->getMessage()
            ];

            // Log the error
            $this->logAPICalls('getDropdownOptionscreateRequestsoffice', "", [], $response);

            return response()->json($response, 500);
        }
    }

    //Dropdown CreateRequest location
    public function getDropdownOptionscreateRequestslocation(Request $request)
    {
        try {

            $location = Location::select('id', 'location_name')
                ->where('is_archived', '0')
                ->get();

            // Build the response
            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown data retrieved successfully.',
                'location' => $location,
            ];

            // Log the API call
            $this->logAPICalls('getDropdownOptionscreateRequestslocation', "", [], $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown data.',
                'error' => $e->getMessage()
            ];

            // Log the error
            $this->logAPICalls('getDropdownOptionscreateRequestslocation', "", [], $response);

            return response()->json($response, 500);
        }
    }

    public function getSetting(string $code)
    {
        try {
            $value = DB::table('settings')
                ->where('setting_code', $code)
                ->value('setting_value');
        } catch (Throwable $e) {
            return $e->getMessage();
        }
        return $value;
    }

    // Log API calls for requests
    public function logAPICalls(string $methodName, string $requestId, array $param, array $resp)
    {
        try {
            \App\Models\ApiLog::create([
                'method_name' => $methodName,
                'request_id' => $requestId,
                'api_request' => json_encode($param),
                'api_response' => json_encode($resp),
            ]);
        } catch (Throwable $e) {
            return false;
        }
        return true;
    }
}
