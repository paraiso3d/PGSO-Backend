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
use Throwable;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;


class RequestController extends Controller
{
    // public function __construct(Request $request)
    // {
    //     // Retrieve the authenticated user
    //     $user = $request->user();

    //     // Apply middleware based on the user type
    //     if ($user && $user->user_type === 'Administrator') {
    //         $this->middleware('UserTypeAuth:Administrator')->only(['createRequest', 'updateRequest', 'getRequests', 'getRequestById']);
    //     }

    //     if ($user && $user->user_type === 'Supervisor') {
    //         $this->middleware('UserTypeAuth:Supervisor')->only(['getRequests']);
    //     }

    //     if ($user && $user->user_type === 'TeamLeader') {
    //         $this->middleware('UserTypeAuth:TeamLeader')->only(['getRequests']);
    //     }

    //     if ($user && $user->user_type === 'Controller') {
    //         $this->middleware('UserTypeAuth:Controller')->only(['getRequests']);
    //     }

    //     if ($user && $user->user_type === 'DeanHead') {
    //         $this->middleware('UserTypeAuth:DeanHead')->only(['createRequest', 'getRequests']);
    //     }
    // }

    
    public function getSetting(string $code)
    {
        try {
            $value = DB::table('settings')
                ->where('setting_code', $code)
                ->value('setting_value');
        }
        catch (Throwable $e) {
            return $e->getMessage();
        }
        return $value;
    }





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
            $this->logAPICalls('createRequest', '', $request->all(), $response);
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
            
            $locationId = $request->input('location_id');
            $officeId = $request->input('office_id');
    
            $location = Location::findOrFail($locationId);
            $office = Office::findOrFail($officeId);
    
            // Create the new request record
            $newRequest = Requests::create([
                'control_no' => $controlNo,
                'description' => $request->input('description'),
                'office_name' => $office->office_name,
                'location_name' => $location->location_name,
                'overtime' => $request->input('overtime'),
                'area' => $request->input('area'),
                'fiscal_year' => $request->input('fiscal_year'),
                'file_path' => $path, $filePath, 
                'status' => $status,
                'office_id' => $office->id,
                'location_id' => $location->id,
            ]);
    
            // Prepare the success response
            $response = [
                'isSuccess' => true,
                'message' => 'Request successfully created.',
                'request' => $newRequest,
                'file_url' => $fileUrl, // Return the public URL of the uploaded file
            ];
    
            $this->logAPICalls('createRequest', $newRequest->id, $request->all(), $response);
    
            return response()->json($response, 200);
    
        } catch (Throwable $e) {
            // Handle any exceptions
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to create the request.',
                'error' => $e->getMessage(),
            ];
    
            $this->logAPICalls('createRequest', '', $request->all(), $response);
    
            return response()->json($response, 500);
        }
    }
    
    

    // Method to retrieve all requests

    public function getRequests(Request $request)
    {
        try {
            // Initialize query
            $query = Requests::query();
    
            // Select specific fields from the requests table with formatted updated_at
            $query->select(
                'requests.id',
                'requests.control_no',
                'requests.description',
                'requests.office_name',
                'requests.location_name',
                'requests.overtime',
                'requests.file_path',
                'requests.area',
                'requests.fiscal_year',
                'requests.status',
                'requests.office_id',
                'requests.location_id',
                DB::raw("DATE_FORMAT(requests.updated_at, '%Y-%m-%d') as updated_at") 
            )
                // Only get active requests (is_archived = 'A')
                ->where('requests.is_archived', 'A');
    
            // Search by control_no if provided
            if ($request->has('search') && !empty($request->input('search'))) {
                $query->where('requests.control_no', 'like', '%' . $request->input('search') . '%');
            }
    
            // Pagination
            $perPage = $request->input('per_page', 10);
    
            // Sort by control_no or any other field    if needed
            $requests = $query->orderBy('requests.control_no', 'asc')->paginate($perPage);
    
            // Response
            $response = [
                'isSuccess' => true,
                'message' => 'Requests retrieved successfully.',
                'request' => $requests,
            ];
    
            $this->logAPICalls('getRequests', '', $request->all(), $response);
    
            return response()->json($response, 200);
    
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve the requests.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('getRequests', '', $request->all(), $response);
    
            return response()->json($response, 500);
        }
    }
    

    // Method to delete (archive) a request
    public function deleteRequest($id)
    {
        try {
            $requestRecord = Requests::findOrFail($id);
            $requestRecord->update(['is_archived' => 'I']);

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

    //Dropdown Request Location

    public function getDropdownOptionsRequestslocation(Request $request)
    {
        try {

            $location = Location::select('id', 'location_name')
                ->where('is_archived', 'A')
                ->get();

            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown data retrieved successfully.',
                'location' => $location
            ];

            // Log the API call
            $this->logAPICalls('getDropdownOptionsRequestslocation', "", $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown data.',
                'error' => $e->getMessage()
            ];

            // Log the error
            $this->logAPICalls('getDropdownOptionsRequestslocation', "", $request->all(), $response);

            return response()->json($response, 500);
        }
    }


    //Dropdown Request Status

    public function getDropdownOptionsRequeststatus(Request $request)
    {
        try {

            $status = Requests::select(DB::raw('MIN(id) as id'), 'status')
                ->whereIn('status', ['Pending', 'For Inspection', 'On-Going', 'Completed', 'Returned'])
                ->where('is_archived', 'A')
                ->groupBy('status')
                ->get();

            // Build the response
            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown data retrieved successfully.',
                'status' => $status
            ];

            // Log the API call
            $this->logAPICalls('getDropdownOptionsRequeststatus', "", $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown data.',
                'error' => $e->getMessage()
            ];

            // Log the error
            $this->logAPICalls('getDropdownOptionsRequeststatus', "", $request->all(), $response);

            return response()->json($response, 500);
        }

    }

    //Dropdown Request Status

    public function getDropdownOptionsRequestyear(Request $request)
    {
        try {

            $year = Requests::select('id', 'fiscal_year')
                ->where('is_archived', 'A')
                ->get();

            // Build the response
            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown data retrieved successfully.',
                'year' => $year
            ];

            // Log the API call
            $this->logAPICalls('getDropdownOptionsRequestyear', "", $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown data.',
                'error' => $e->getMessage()
            ];

            // Log the error
            $this->logAPICalls('getDropdownOptionsRequestyear', "", $request->all(), $response);

            return response()->json($response, 500);
        }

    }

    //Dropdown Request Division

    public function getDropdownOptionsRequestdivision(Request $request)
    {
        try {

            $div_name = Division::select('id', 'div_name')
                ->where('is_archived', 'A')
                ->get();

            // Build the response
            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown data retrieved successfully.',
                'div_name' => $div_name
            ];

            // Log the API call
            $this->logAPICalls('getDropdownOptionsRequestdivision', "", $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown data.',
                'error' => $e->getMessage()
            ];

            // Log the error
            $this->logAPICalls('getDropdownOptionsRequestdivision', "", $request->all(), $response);

            return response()->json($response, 500);
        }

    }

    //  Dropdown Request Category

    public function getDropdownOptionsRequestcategory(Request $request)
    {
        try {

            $category = Category::select('id', 'category_name')
                ->where('is_archived', 'A')
                ->get();


            // Build the response
            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown data retrieved successfully.',
                'category' => $category
            ];

            // Log the API call
            $this->logAPICalls('getDropdownOptionsRequestcategory', "", $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown data.',
                'error' => $e->getMessage()
            ];

            // Log the error
            $this->logAPICalls('getDropdownOptionsRequestcategory', "", $request->all(), $response);

            return response()->json($response, 500);
        }

    }

    //Dropdown Create Request office

    public function getDropdownOptionscreateRequestsoffice(Request $request)
    {
        try {
            $office = Office::select('id', 'office_name')
                ->where('is_archived', 'A')
                ->get();

            // Build the response
            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown data retrieved successfully.',
                'office' => $office,
            ];

            // Log the API call
            $this->logAPICalls('getDropdownOptionscreateRequestsoffice', "", $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown data.',
                'error' => $e->getMessage()
            ];

            // Log the error
            $this->logAPICalls('getDropdownOptionscreateRequestsoffice', "", $request->all(), $response);

            return response()->json($response, 500);
        }
    }

    //Dropdown CreateRequest location

    public function getDropdownOptionscreateRequestslocation(Request $request)
    {
        try {

            $location = Location::select('id', 'location_name')
                ->where('is_archived', 'A')
                ->get();

            // Build the response
            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown data retrieved successfully.',
                'location' => $location,
            ];

            // Log the API call
            $this->logAPICalls('getDropdownOptionscreateRequestslocation', "", $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown data.',
                'error' => $e->getMessage()
            ];

            // Log the error
            $this->logAPICalls('getDropdownOptionscreateRequestslocation', "", $request->all(), $response);

            return response()->json($response, 500);
        }
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
