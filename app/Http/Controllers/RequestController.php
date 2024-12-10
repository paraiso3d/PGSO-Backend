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


            // Create the new request record
            $newRequest = Requests::create([
                'control_no' => $controlNo,
                'request_title'=>$request->input('request_title'),
                'description' => $request->input('description'),
                'location_name' => $request->input('location_name'),
                'category' => $request->input('category'),
                'file_path' => $filePath,
                'status' => $request->input('status', 'Pending'),
                'requested_by' => $user->id,
                'date_requested' => now(),
                'is_archived' => false,
            ]);

            $response = [
                'isSuccess' => true,
                'message' => 'Request successfully created.',
                'request' => [
                    'id' => $newRequest->id,
                    'control_no' => $controlNo,
                    'request_title'=>$newRequest->request_title,
                    'description' => $newRequest->description,
                    'location_name' => $newRequest->location_name,
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
                    'date_requested' => $newRequest->date_requested,
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

            // Update the status
            $requestRecord->status = 'For Review';
            $requestRecord->save();

            // Fetch the user who made the request
            $user = $requestRecord->user;

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
    public function rejectRequest($id)
    {
        try {
            // Find the request by its ID
            $requestRecord = Requests::findOrFail($id);

            // Update the status
            $requestRecord->status = 'Returned';
            $requestRecord->save();

            // Fetch the user who made the request
            $user = $requestRecord->user;

            // Prepare the response
            $response = [
                'isSuccess' => true,
                'message' => 'Request has been rejected and is now Returned.',
                'request' => [
                    'id' => $requestRecord->id,
                    'control_no' => $requestRecord->control_no,
                    'status' => $requestRecord->status,
                    'requested_by' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name ?? 'N/A',
                        'last_name' => $user->last_name ?? 'N/A',
                    ],
                    'date_rejected' => now()->toDateTimeString(),
                ],
            ];

            $this->logAPICalls('rejectRequest', "", [], $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to reject the request.',
                'error' => $e->getMessage(),
            ];

            $this->logAPICalls('rejectRequest', "", [], $response);

            return response()->json($response, 500);
        }
    }


    public function getRequests(Request $request)
    {
        try {
            $userId = $request->user()->id;
            $role = $request->user()->role_name; // Fetch the role directly from the users table
    
            // Pagination settings
            $perPage = $request->input('per_page', 10);
            $searchTerm = $request->input('search', null);
    
            // Initialize query
            $query = Requests::select(
                'requests.id',
                'requests.control_no',
                'requests.request_title',
                'requests.requested_by',
                'users.first_name as requested_by_first_name',
                'users.last_name as requested_by_last_name',
                'requests.description',
                'requests.location_name',
                'requests.file_path',
                'requests.category_id',
                'requests.feedback',
                'requests.status',
                'requests.date_requested',
            )
                ->leftJoin('users', 'users.id', '=', 'requests.requested_by') // Join with users table to get user details
                ->leftJoin('categories', 'categories.id', '=', 'requests.category_id') // Join with categories table to get category name
                ->where('requests.is_archived', '=', '0') // Filter active requests
                ->when($searchTerm, function ($query, $searchTerm) {
                    return $query->where('requests.control_no', 'like', '%' . $searchTerm . '%');
                });
    
            // Apply filters based on input from the request
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
                    $query->whereIn('requests.status', ['Pending', 'For Review']);
                    break;
                case 'staff':
                    $query->where('requests.user_id', $userId);
                    break;
                case 'personnel':
                    $query->where('requests.status', 'On-going');
                    break;
                default:
                    $query->whereRaw('1 = 0');
                    break;
            }
    
            // Execute the query with pagination
            $result = $query->paginate($perPage);
    
            if ($result->isEmpty()) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'No requests found matching the criteria.',
                ];
                $this->logAPICalls('getRequests', "", [], $response);
    
                return response()->json($response, 404);
            }
    
            // Format the response
            $formattedRequests = $result->getCollection()->transform(function ($request) {
                $filePath = $request->file_path;
                return [
                    'id' => $request->id,
                    'control_no' => $request->control_no,
                    'request_title' => $request->request_title,
                    'requested_by' => $request->requested_by,
                    'requested_by_name' => $request->requested_by_first_name . ' ' . $request->requested_by_last_name,
                    'description' => $request->description,
                    'location_name' => $request->location_name,
                    'category_id' => $request->category_id,
                    'category_name' => $request->category_id 
                        ? DB::table('categories')->where('id', $request->category_id)->value('category_name') 
                        : null, // Fetch category name directly if category_id exists
                    'feedback' => $request->feedback,
                    'status' => $request->status,
                    'file_path' => $filePath,
                    'file_url' => $filePath ? asset($filePath) : null,
                    'date_requested'=>$request->date_requested,
                    'updated_at' => $request->updated_at,
                ];
            });
    
            // Prepare the response with pagination
            $response = [
                'isSuccess' => true,
                'message' => 'Requests retrieved successfully.',
                'requests' => $formattedRequests,
                'pagination' => [
                    'total' => $result->total(),
                    'per_page' => $result->perPage(),
                    'current_page' => $result->currentPage(),
                    'last_page' => $result->lastPage(),
                    'url' => url('api/requestList?page=' . $result->currentPage() . '&per_page=' . $result->perPage()),
                ],
            ];
    
            $this->logAPICalls('getRequests', "", [], $response);
    
            return response()->json($response, 200);
    
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve requests.',
                'error' => $e->getMessage(),
            ];
    
            $this->logAPICalls('getRequests', "", [], $response);
    
            return response()->json($response, 500);
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
                'requests.description',
                'requests.location_name',
                'requests.file_path',
                'requests.category_id',
                'categories.category_name', // Fetch category name directly
                'requests.feedback',
                'requests.status',
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
                'description' => $requestDetails->description,
                'location_name' => $requestDetails->location_name,
                'category_id' => $requestDetails->category_id,
                'category_name' => $requestDetails->category_name, // Include category name
                'feedback' => $requestDetails->feedback,
                'status' => $requestDetails->status,
                'file_path' => $requestDetails->file_path,
                'file_url' => $requestDetails->file_path ? asset($requestDetails->file_path) : null,
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
    

    public function assessRequest(Request $request)
    {
        try {
            // Retrieve the currently logged-in user
            $user = auth()->user();

            // Retrieve the record based on the provided request ID
            $requests = Requests::where('id', $request->id)->firstOrFail();

            // Update the status to "For Review"
            $requests->update(['status' => 'For Review']);

            // Prepare the full name of the currently logged-in user
            $fullName = "{$user->first_name} {$user->middle_initial} {$user->last_name}";

            // Prepare the response
            $response = [
                'isSuccess' => true,
                'messsage' => 'Assesing request.',
                'request_id' => $requests->id,
                'status' => $requests->status,
                'user_id' => $user->id,
                'user' => $fullName,
            ];

            // Log the API call
            $this->logAPICalls('assessRequest', $requests->id, [], $response);

            return response()->json($response, 200);

        } catch (Throwable $e) {
            // Prepare the error response
            $response = [
                'isSuccess' => false,
                'message' => "Failed to update the work status.",
                'error' => $e->getMessage(),
            ];

            // Log the API call with failure response
            $this->logAPICalls('assessRequest', $request->id ?? '', [], $response);

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

    //Dropdown Request Status
    public function getDropdownOptionsRequestyear(Request $request)
    {
        try {

            $year = Requests::select('id', 'fiscal_year')
                ->where('is_archived', '0')
                ->get();

            // Build the response
            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown data retrieved successfully.',
                'year' => $year
            ];

            // Log the API call
            $this->logAPICalls('getDropdownOptionsRequestyear', "", [], $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown data.',
                'error' => $e->getMessage()
            ];

            // Log the error
            $this->logAPICalls('getDropdownOptionsRequestyear', "", [], $response);

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
