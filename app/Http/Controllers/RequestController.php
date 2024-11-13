<?php

namespace App\Http\Controllers;

use App\Models\Category;
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
    // Method to create a new request.    
    public function createRequest(Request $request)
    {

        $validator = Requests::validateRequest($request->all());


        if ($validator->fails()) {
            $response = [
                'isSuccess' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ];
            $this->logAPICalls('createRequest', '', [], $response);

            return response()->json($response, 500);
        }


        $controlNo = Requests::generateControlNo();


        $filePath = null;
        $fileUrl = null;


        if ($request->hasFile('file_path')) {
            // Get the uploaded file
            $file = $request->file('file_path');

            // Convert the uploaded file to base64
            $fileContents = file_get_contents($file->getRealPath());
            $base64Image = 'data:image/' . $file->extension() . ';base64,' . base64_encode($fileContents);

            // Call your saveImage method to handle the base64 image
            $path = $this->getSetting("ASSET_IMAGE_PATH");
            $fdateNow = now()->format('Y-m-d');
            $ftimeNow = now()->format('His');
            $filePath = (new AuthController)->saveImage($base64Image, 'asset', 'Asset-' . $controlNo, $fdateNow . '_' . $ftimeNow);


            $fileUrl = asset('storage/' . $filePath);
        }


        $status = $request->input('status', 'Pending');

        try {

            $user = auth()->user();
        

            $locationId = $request->input('location_id');
            $officeId = $request->input('office_id');

            $location = Location::findOrFail($locationId);
            $office = Office::findOrFail($officeId);

            // Create the new request record
            $newRequest = Requests::create([
                'control_no' => $controlNo,
                'description' => $request->input('description'),
                'overtime' => $request->input('overtime'),
                'area' => $request->input('area'),
                'fiscal_year' => $request->input('fiscal_year'),
                'file_path' =>$filePath,
                'status' => $status,
                'office_id' => $office->id,
                'location_id' => $location->id,
                'user_id' => $user->id,
            ]);

            $response = [
                $response = [
                    'isSuccess' => true,
                    'message' => 'Request successfully created.',
                    'request' => [
                        'id' => $newRequest->id,
                        'control_no' => $controlNo,
                        'description' => $request->input('description'),
                        'overtime' => $request->input('overtime'),
                        'area' => $request->input('area'),
                        'fiscal_year' => $request->input('fiscal_year'),
                        'status' => $status,
                        'office_id' => $office->id,
                        'office_name' => $office->office_name,
                        'location_id' => $location->id,
                        'location_name' => $location->location_name,
                        'user_id' => $user->id,
                        'file_path' => $filePath,
                        'file_url' => $fileUrl,
                    ]
                ]
            ];

            $this->logAPICalls('createRequest', $newRequest->id, [], $response);

            return response()->json($response, 200);

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

    public function updateReturn(Request $request, $id)
    {
        $existingRequest = Requests::findOrFail($id);

        // Check if the request status is "Returned"
        if ($existingRequest->status !== 'Returned') {
            $response = [
                'isSuccess' => false,
                'message' => 'Only requests with the status "Returned" can be updated.',
            ];
            $this->logAPICalls('updateRequest', '', [], $response);
            return response()->json($response, 403);
        }

        $validator = Requests::validateRequest($request->all());

        if ($validator->fails()) {
            $response = [
                'isSuccess' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ];
            $this->logAPICalls('updateRequest', '', [], $response);
            return response()->json($response, 500);
        }

        $filePath = $existingRequest->file_path;
        $fileUrl = $filePath ? asset('storage/' . $filePath) : null;

        if ($request->hasFile('file_path')) {
            // Get the uploaded file
            $file = $request->file('file_path');

            // Convert the uploaded file to base64
            $fileContents = file_get_contents($file->getRealPath());
            $base64Image = 'data:image/' . $file->extension() . ';base64,' . base64_encode($fileContents);

            // Call your saveImage method to handle the base64 image
            $path = $this->getSetting("ASSET_IMAGE_PATH");
            $fdateNow = now()->format('Y-m-d');
            $ftimeNow = now()->format('His');
            $filePath = (new AuthController)->saveImage($base64Image, 'asset', 'Asset-' . $existingRequest->control_no, $fdateNow . '_' . $ftimeNow);

            $fileUrl = asset('storage/' . $filePath);
        }

        try {
            $user = auth()->user();

            $locationId = $request->input('location_id', $existingRequest->location_id);
            $officeId = $request->input('office_id', $existingRequest->office_id);

            $location = Location::findOrFail($locationId);
            $office = Office::findOrFail($officeId);

            // Update the existing request record with status set to "Pending"
            $existingRequest->update([
                'description' => $request->input('description', $existingRequest->description),
                'overtime' => $request->input('overtime', $existingRequest->overtime),
                'area' => $request->input('area', $existingRequest->area),
                'fiscal_year' => $request->input('fiscal_year', $existingRequest->fiscal_year),
                'file_path' => $filePath,
                'status' => 'Pending', // Automatically update status to "Pending"
                'office_id' => $office->id,
                'location_id' => $location->id,
                'user_id' => $user->id,
            ]);

            // Prepare the success response
            $response = [
                'isSuccess' => true,
                'message' => 'Request successfully updated.',
                'returnRequest' => [
                    'id' => $existingRequest->id,
                    'control_no' => $existingRequest->control_no,
                    'description' => $existingRequest->description,
                    'overtime' => $existingRequest->overtime,
                    'area' => $existingRequest->area,
                    'fiscal_year' => $existingRequest->fiscal_year,
                    'status' => 'Pending',
                    'office_id' => $office->id,
                    'office_name' => $office->office_name,
                    'location_id' => $location->id,
                    'location_name' => $location->location_name,
                    'user_id' => $user->id,
                    'file_path' => $filePath,
                    'file_url' => $fileUrl,
                ]
            ];

            $this->logAPICalls('updateRequest', $existingRequest->id, [], $response);

            return response()->json($response, 200);

        } catch (Throwable $e) {
            // Handle any exceptions
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to update the request.',
                'error' => $e->getMessage(),
            ];

            $this->logAPICalls('updateRequest', '', [], $response);

            return response()->json($response, 500);
        }
    }

    public function getRequests(Request $request)
    {
        try {
            $userId = $request->user()->id;
            $userTypeId = $request->user()->user_type_id;

            $role = DB::table('user_types')
                ->where('id', $userTypeId)
                ->value('name');

            // Pagination settings
            $perPage = $request->input('per_page', 10);
            $searchTerm = $request->input('search', null);
            $perPage = $request->input('per_page', 10);
            $searchTerm = $request->input('search', null);

            // Initialize query
            $query = Requests::select(
                'requests.id',
                'requests.control_no',
                'requests.description',
                'offices.office_name',
                'locations.location_name',
                'requests.overtime',
                'requests.file_path',
                'requests.area',
                'requests.fiscal_year',
                'requests.status',
                'requests.office_id',
                'requests.location_id',
                DB::raw("DATE_FORMAT(requests.updated_at, '%Y-%m-%d') as updated_at")
            )
                ->leftJoin('offices', 'requests.office_id', '=', 'offices.id')
                ->leftJoin('locations', 'requests.location_id', '=', 'locations.id')
                ->where('requests.is_archived', '=', '0') // Filter active requests
                ->when($searchTerm, function ($query, $searchTerm) {
                    // Apply search filter if a search term is provided
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

            if ($request->has('status') && $request->status !== 'All Status') {
                $query->where('requests.status', $request->status);
            }

            if ($request->has('location') && $request->location !== 'All Location') {
                $query->where('requests.location_id', $request->location);
            }

            if ($request->has('division') && $request->division !== 'All Division') {
                $query->where('requests.office_id', $request->division);
            }

            if ($request->has('category') && $request->category !== 'All Category') {
                $query->whereJsonContains('requests.category_id', $request->category);
            }

            if ($request->has('year') && $request->year !== 'All Year') {
                $query->whereYear('requests.fiscal_year', $request->year);
            }

            // Role-based filtering
            switch ($role) {
                case 'Administrator':
                    // Admin gets all requests, no extra filter needed
                    break;
                case 'Controller':
                    $query->where('requests.status', 'Pending');
                    break;
                case 'DeanHead':
                    $query->where('requests.user_id', $userId);
                    break;
                case 'TeamLeader':
                    $query->where('requests.status', 'On-going');
                    break;
                case 'Supervisor':
                    $query->where('requests.status', 'For Inspection');
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

                return response()->json($response, 500);
            }

            // Format the response
            $formattedRequests = $result->getCollection()->transform(function ($request) {
                return [
                    'id' => $request->id,
                    'control_no' => $request->control_no,
                    'description' => $request->description,
                    'office_name' => $request->office_name,
                    'location_name' => $request->location_name,
                    'overtime' => $request->overtime,
                    'area' => $request->area,
                    'fiscal_year' => $request->fiscal_year,
                    'status' => $request->status,
                    'file_path' => $request->file_path,
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
    public function deleteRequest($id)
    {
        try {
            $requestRecord = Requests::findOrFail($id);
            $requestRecord->update(['is_archived' => '1']);

            $response = [
                'isSuccess' => true,
                'message' => 'Request successfully archived.',
            ];
            $this->logAPICalls('deleteRequest', $id, [], $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to archive the request.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('deleteRequest', $id, [], $response);
            return response()->json($response, 500);
        }
    }

    public function assessRequest(Request $request)
    {
        try {
            // Retrieve the currently logged-in user
            $user = auth()->user();

            // Retrieve the record based on the provided request ID
            $work = Requests::where('id', $request->id)->firstOrFail();

            // Update the status to "On-going"
            $work->update(['status' => 'For Review']);

            // Prepare the full name of the currently logged-in user
            $fullName = "{$user->first_name} {$user->middle_initial} {$user->last_name}";

            // Prepare the response
            $response = [
                'isSuccess' => true,
                'messsage' => 'Assesing request.',
                'request_id' => $work->id,
                'status' => $work->status,
                'user_id' => $user->id,
                'user' => $fullName,
            ];

            // Log the API call
            $this->logAPICalls('assessRequest', $work->id, [], $response);

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
            $office = Office::select('id', 'office_name')
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
