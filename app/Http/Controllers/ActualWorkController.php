<?php

namespace App\Http\Controllers;

use App\Models\Actual_work;
use App\Models\ManpowerDeployment;
use App\Models\Manpower;
use App\Models\Requests;
use Illuminate\Http\Request;
use Validator;
use Throwable;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Session;
use Illuminate\Support\Facades\Auth;



class ActualWorkController extends Controller
{

    //CREATE WORK REPORT
    public function createWorkreport(Request $request, $id = null)
    {

        // Validate the incoming request data using the `in` rule with an array
        $validator = Validator::make($request->all(), [
            'recommended_action' => 'required|string|max:255',
            'remarks' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            $response = [
                'isSuccess' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ];
            $this->logAPICalls('createWorkreport', $id, $request->all(), $response);
            return response()->json($response, 500);
        }

        try {
            // Fetch the existing request using the provided ID or some identifier
            $existingRequest = Requests::find($id);

            if (!$existingRequest) {
                $response = [
                    'isSuccess' => false,
                    'message' => "No request found.",
                ];
                $this->logAPICalls('createWorkreport', $id, $request->all(), $response);
                return response()->json($response, 404);
            }


            // Prepare the data for creating a new Inspection report
            $actualworkData = [
                'recommended_action' => $request->input('recommended_action'),
                'remarks' => $request->input('remarks'),
                'control_no' => $existingRequest->control_no, // Link to the existing control_no from Requests table
                'request_id' => $existingRequest->id,
            ];

            // Create a new entry in the Actual_work table
            $newWorkreport = Actual_work::create($actualworkData);

            // Response for successful creation in the Actual_work table
            $response = [
                'isSuccess' => true,
                'message' => 'Actual work report created successfully.',
                'actualwork' => $newWorkreport,
            ];
            $this->logAPICalls('createInspection', $existingRequest->id, $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Response for failed operation
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to create the actual work report.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('createInspection', $id ?? '', $request->all(), $response);
            return response()->json($response, 500);
        }
    }

    //UPDATE WORK REPORT
    public function updateWorkreport(Request $request, $id)
    {
        // Validate the incoming request data using Laravel's built-in validation method
        $validator = Validator::make($request->all(), [
            'recommended_action' => 'sometimes|string|max:255',
            'remarks' => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            $response = [
                'isSuccess' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ];
            $this->logAPICalls('updateWorkreport', $id, $request->all(), $response);
            return response()->json($response, 500);
        }

        try {
            // Fetch the existing inspection report using the provided ID
            $existingWorkreport = Actual_work::find($id);

            // If no inspection is found by ID, return an error response
            if (!$existingWorkreport) {
                $response = [
                    'isSuccess' => false,
                    'message' => "No actual work report found.",
                ];
                $this->logAPICalls('updateWorkreport', $id, $request->all(), $response);
                return response()->json($response, 404);
            }

            // Prepare the data for updating the inspection report
            $actualworkData = [
                'recommended_action' => $request->input('recommended_action') ? $request->input('recommended_action') : $existingWorkreport->recommended_action,
                'remarks' => $request->input('remarks') ? $request->input('remarks') : $existingWorkreport->remarks,
            ];

            // Update the existing inspection report with the new data
            $existingWorkreport->update($actualworkData);

            // Response for successful update in the Inspection_report table
            $response = [
                'isSuccess' => true,
                'message' => 'Actual work report updated successfully.',
                'actualwork' => $existingWorkreport,
            ];
            $this->logAPICalls('updateWorkreport', $existingWorkreport->id, $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Response for failed operation
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to update the actual work report.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('updateWorkreport', $id, $request->all(), $response);
            return response()->json($response, 500);
        }
    }

    //GET WORK REPORT
    public function getWorkreports(Request $request, $Request_id)
    {
        try {
            // Check if the `request_id` exists in the Control_Request table
            $Requestexists = Requests::where('id', $Request_id)->exists();

            if (!$Requestexists) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => "No control request found for this id: {$Request_id}.",
                ], 500); // Return a 404 Not Found if no matching control request is found
            }

            // Fetch and group inspection reports by 'control_no' using the provided `request_id`
            $actualworkReports = Actual_work::select('control_no', 'id', 'recommended_action', 'remarks')
                ->where('is_archived', 'A')
                ->where('request_id', $Request_id) // Filter by the provided request_id
                ->get()
                ->groupBy('control_no'); // Group records by 'control_no'

            // Prepare the grouped data structure
            $groupedworkReports = $actualworkReports->map(function ($group) {
                return $group->map(function ($actualwork) {
                    return [
                        'id' => $actualwork->id,
                        'recommended_action' => $actualwork->recommended_action,
                        'remarks' => $actualwork->remarks,
                    ];
                });
            });

            // Prepare the response
            $response = [
                'isSuccess' => true,
                'message' => 'Actual work report retrieved successfully.',
                'actualwork' => $groupedworkReports,
            ];

            // Log API calls
            $this->logAPICalls('getWorkreports', $Request_id, $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Prepare the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve actual work report.',
                'error' => $e->getMessage(),
            ];

            // Log the error
            $this->logAPICalls('getWorkreports', $Request_id ?? '', $request->all(), $response);

            return response()->json($response, 500);
        }
    }

    //ADD MANPOWER DEPLOYMENT
    public function addManpowerDeploy(Request $request)
    {
        // Validate input
        $request->validate([
            'manpower_id' => 'required|exists:manpowers,id', // Assuming you have a manpower ID
            'rating' => 'required|numeric|between:0,100',
        ]);

        try {
            // Fetch the manpower record using the provided ID
            $manpower = Manpower::findOrFail($request->input('manpower_id'));

            // Clean the rating input (removing the '%' sign)
            $ratingInput = $request->input('rating');
            $numericRating = str_replace('%', '', $ratingInput);

            // Prepare the rating for storage
            $ratingToStore = $numericRating . '%';

            // Create a new manpower deployment record
            $newManpowerDeploy = ManpowerDeployment::create([
                'first_name' => $manpower->first_name,
                'last_name' => $manpower->last_name,
                'rating' => $ratingToStore,
                'manpower_id' => $manpower->id
            ]);

            // Prepare the response
            $response = [
                'isSuccess' => true,
                'message' => 'Manpower successfully added.',
                'manpowerdeployment' => $newManpowerDeploy,
            ];

            // Log the API call
            $this->logAPICalls('addManpowerDeploy', $newManpowerDeploy->id, $request->all(), $response);

            // Return a 200 Created response
            return response()->json($response, 200);

        } catch (Throwable $e) {
            // Handle any exceptions that may occur
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to create the Actual work report.',
                'error' => $e->getMessage(),
            ];

            // Log the API call
            $this->logAPICalls('addManpowerDeploy', '', $request->all(), $response);

            // Return a 500 Internal Server Error response
            return response()->json($response, 500);
        }
    }


    //GET MANPOWER DEPLOYMENT
    public function getManpowerDeploy(Request $request)
    {

        try {
            // Fetch all manpower deployment records
            $manpowerDeployments = ManpowerDeployment::select('id', 'first_name', 'last_name', 'rating')
                ->where('is_archived', 'A')
                ->get();


            // Prepare the response
            $response = [
                'isSuccess' => true,
                'message' => 'Manpower deployments retrieved successfully.',
                'manpowerdeployment' => $manpowerDeployments,
            ];

            // Log the API call
            $this->logAPICalls('getManpowerDeploy', '', $request->all(), $response);

            return response()->json($response, 200);

        } catch (Throwable $e) {

            // Prepare the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve manpower deployments.',
                'error' => $e->getMessage(),
            ];

            // Log the API call
            $this->logAPICalls('getManpowerDeploy', '', $request->all(), $response);

            return response()->json($response, 500);
        }
    }

    //DELETE MANPOWER DEPLOYMENT
    public function deletemanpowerdeployment(Request $request)
    {
        try {

            $manpowerdeployment = ManpowerDeployment::findOrFail($request->id);
            $manpowerdeployment->update(['is_archived' => "I"]);
            $response = [
                'isSuccess' => true,
                'message' => "ManpowerDeployment successfully deleted."
            ];

            // Log the API call (assuming this method works properly)
            $this->logAPICalls('deletemanpowerdeployment', $manpowerdeployment->id, [], [$response]);
            return response()->json($response, 200);

        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to delete the ManpowerDeployment.",
                'error' => $e->getMessage()
            ];

            // Log the API call with failure response
            $this->logAPICalls('deletemanpowerdeployment', "", [], [$response]);

            return response()->json($response, 500);
        }
    }

    public function getDropdownOptionsManpower(Request $request)
    {
        try {


            $Manpowerdeploy = ManpowerDeployment::select('id', 'first_name', 'last_name')
                ->where('is_archived', 'A')
                ->get();

            // Build the response
            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown data retrieved successfully.',
                'div_name' => $Manpowerdeploy,
            ];

            // Log the API call
            $this->logAPICalls('getDropdownOptionsActualwork', "", $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown data.',
                'error' => $e->getMessage()
            ];

            // Log the error
            $this->logAPICalls('getDropdownOptionsActualwork', "", $request->all(), $response);

            return response()->json($response, 500);
        }
    }

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