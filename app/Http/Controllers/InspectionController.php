<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Division;
use App\Models\Requests;
use App\Models\Control_Request;
use Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\Inspection_report;
use Illuminate\Http\Request;
use Throwable;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Session;

class InspectionController extends Controller
{

    // public function __construct(Request $request)
    // {
    //     // Retrieve the authenticated user
    //     $user = $request->user();

    //     // Apply middleware based on the user type
    //     if ($user && $user->user_type === 'Administrator') {
    //         $this->middleware('UserTypeAuth:Administrator')->only(['updateReview', 'getReviews']);
    //     }

    //     if ($user && $user->user_type === 'Supervisor') {
    //         $this->middleware('UserTypeAuth:Supervisor')->only(['updateReview', 'getReviews']);
    //     }

    //     if ($user && $user->user_type === 'TeamLeader') {
    //         $this->middleware('UserTypeAuth:TeamLeader')->only(['updateReview', 'getReviews']);
    //     }

    //     if ($user && $user->user_type === 'Controller') {
    //         $this->middleware('UserTypeAuth:Controller')->only(['updateReview', 'getReviews']);
    //     }

    //     if ($user && $user->user_type === 'DeanHead') {
    //         $this->middleware('UserTypeAuth:DeanHead')->only(['getReviews']);
    //     }
    // }

    public function createInspection(Request $request, $id = null)
    {

        // Validate the incoming request data using the `in` rule with an array
        $validator = Validator::make($request->all(), [
            'description' => 'required|string|max:255',
            'recommendation' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            $response = [
                'isSuccess' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ];
            $this->logAPICalls('createInspection', $id, $request->all(), $response);
            return response()->json($response, 500);
        }

        try {
            // Fetch the existing request using the provided ID or some identifier
            $existingRequest = Control_Request::find($id);

            // If no request is found by ID, return an error response
            if (!$existingRequest) {
                $response = [
                    'isSuccess' => false,
                    'message' => "No request found.",
                ];
                $this->logAPICalls('createInspection', $id, $request->all(), $response);
                return response()->json($response, 404);
            }


            // Prepare the data for creating a new Inspection report
            $inspectionData = [
                'description' => $request->input('description'),
                'recommendation' => $request->input('recommendation'),
                'control_no' => $existingRequest->control_no, // Link to the existing control_no from Requests table
                'control_request_id' =>$existingRequest->id,
            ];

            // Create a new entry in the Inspection_report table
            $newInspection = Inspection_report::create($inspectionData);

            // Response for successful creation in the Inspection_report table
            $response = [
                'isSuccess' => true,
                'message' => 'Inspection report created successfully.',
                'inspection' => $newInspection,
            ];
            $this->logAPICalls('createInspection', $existingRequest->id, $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Response for failed operation
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to create the inspection report.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('createInspection', $id ?? '', $request->all(), $response);
            return response()->json($response, 500);
        }
    }


    public function getInspections(Request $request, $controlRequestId)
    {
        try {
            // Check if the `control_request_id` exists in the Control_Request table
            $controlRequestExists = Control_Request::where('id', $controlRequestId)->exists();
    
            if (!$controlRequestExists) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => "No control request found for this id: {$controlRequestId}.",
                ], 500); // Return a 404 Not Found if no matching control request is found
            }
    
            // Fetch and group inspection reports by 'control_no' using the provided `control_request_id`
            $inspectionReports = Inspection_report::select('control_no', 'id', 'description', 'recommendation')
                ->where('is_archived', 'A')
                ->where('control_request_id', $controlRequestId) // Filter by the provided control_request_id
                ->get()
                ->groupBy('control_no'); // Group records by 'control_no'
    
            // Prepare the grouped data structure
            $groupedInspections = $inspectionReports->map(function ($group) {
                return $group->map(function ($inspection) {
                    return [
                        'id' => $inspection->id,
                        'description' => $inspection->description,
                        'recommendation' => $inspection->recommendation,
                    ];
                });
            });
    
            // Prepare the response
            $response = [
                'isSuccess' => true,
                'message' => 'Inspections retrieved successfully.',
                'inspection' => $groupedInspections,
            ];
    
            // Log API calls
            $this->logAPICalls('getInspections', $controlRequestId, $request->all(), $response);
    
            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Prepare the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve inspections.',
                'error' => $e->getMessage(),
            ];
    
            // Log the error
            $this->logAPICalls('getInspections', $controlRequestId ?? '', $request->all(), $response);
    
            return response()->json($response, 500);
        }
    }
    



    public function updateInspection(Request $request, $id)
{
    // Validate the incoming request data using Laravel's built-in validation method
    $validator = Validator::make($request->all(), [
        'description' => 'sometimes|string|max:255',
        'recommendation' => 'sometimes|string|max:255',
    ]);

    if ($validator->fails()) {
        $response = [
            'isSuccess' => false,
            'message' => 'Validation error',
            'errors' => $validator->errors(),
        ];
        $this->logAPICalls('updateInspection', $id, $request->all(), $response);
        return response()->json($response, 500);
    }

    try {
        // Fetch the existing inspection report using the provided ID
        $existingInspection = Inspection_report::find($id);

        // If no inspection is found by ID, return an error response
        if (!$existingInspection) {
            $response = [
                'isSuccess' => false,
                'message' => "No inspection report found.",
            ];
            $this->logAPICalls('updateInspection', $id, $request->all(), $response);
            return response()->json($response, 404);
        }

        // Prepare the data for updating the inspection report
        $inspectionData = [
            'description' => $request->input('description'),
            'recommendation' => $request->input('recommendation'),
        ];

        // Update the existing inspection report with the new data
        $existingInspection->update($inspectionData);

        // Response for successful update in the Inspection_report table
        $response = [
            'isSuccess' => true,
            'message' => 'Inspection report updated successfully.',
            'inspection' => $existingInspection,
        ];
        $this->logAPICalls('updateInspection', $existingInspection->id, $request->all(), $response);

        return response()->json($response, 200);
    } catch (Throwable $e) {
        // Response for failed operation
        $response = [
            'isSuccess' => false,
            'message' => 'Failed to update the inspection report.',
            'error' => $e->getMessage(),
        ];
        $this->logAPICalls('updateInspection', $id, $request->all(), $response);
        return response()->json($response, 500);
    }
}


public function deleteInspection(Request $request)
{
    try {
        // Retrieve the inspection report based on the control_request_id provided in the request
        $inspection = Inspection_report::where('id', $request->id)
            ->where('is_archived', 'A') // Ensure the report is not already archived
            ->firstOrFail();

        // Update the `is_archived` status to "I" (assuming "I" means inactive/archived)
        $inspection->update(['is_archived' => 'I']);

        // Prepare the response
        $response = [
            'isSuccess' => true,
            'message' => "Inspection report successfully archived."
        ];

        // Log the API call (assuming this method works properly)
        $this->logAPICalls('deleteInspection', $inspection->control_request_id, $request->all(), $response);

        return response()->json($response, 200);
    } catch (Throwable $e) {
        // Prepare the error response
        $response = [
            'isSuccess' => false,
            'message' => "Failed to archive the inspection report.",
            'error' => $e->getMessage()
        ];

        // Log the API call with failure response
        $this->logAPICalls('deleteInspection', $request->control_request_id ?? '', $request->all(), $response);

        return response()->json($response, 500);
    }
}

public function updateWorkStatus(Request $request)
{
    try {
        // Retrieve the record based on the provided control_no from the request
        $work = Requests::where('id', $request->id)
            ->firstOrFail(); // Throws a 404 error if no matching record is found

        // Update the status to "On-going"
        $work->update(['status' => 'On-going']);

        // Prepare the response
        $response = [
            'isSuccess' => true,
            'request_id' => $work->id,
            'status' => $work->status,
        ];

        // Log the API call (assuming this method works properly)
        $this->logAPICalls('updateWorkStatus', $work->id, $request->all(), $response);

        return response()->json($response, 200);
    } catch (Throwable $e) {
        // Prepare the error response
        $response = [
            'isSuccess' => false,
            'message' => "Failed to update the work status.",
            'error' => $e->getMessage(),
        ];

        // Log the API call with failure response
        $this->logAPICalls('updateWorkStatus', $request->id ?? '', $request->all(), $response);

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