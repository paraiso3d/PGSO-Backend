<?php

namespace App\Http\Controllers;

use App\Models\Actual_work;
use App\Models\ManpowerDeployment;
use App\Models\Manpower;
use Illuminate\Http\Request;
use Throwable;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Session;
use Illuminate\Support\Facades\Auth;



class ActualWorkController extends Controller
{

    //CREATE WORK REPORT

    public function createWorkreport(Request $request)
    {
        // Validate the incoming request data using the built-in validation method
        $request->validate([
            'recommended_action' => 'required|string|max:255',
            'remarks' => 'required|string|max:255',
        ]);

        // Store the validated request data
        try {
            // Create a new Actual work report record using the validated data
            $newWorkreport = Actual_work::create([
                'recommended_action' => $request->input('recommended_action'),
                'remarks' => $request->input('remarks'),
            ]);

            $response = [
                'isSuccess' => true,
                'message' => 'Actual work report successfully created.',
                'actualwork' => $newWorkreport,
            ];

            // Log the API call (assuming `logAPICalls` is a defined method in your class)
            $this->logAPICalls('createWorkreport', $newWorkreport->id, $request->all(), $response);

            // Return a 201 Created response
            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle any exceptions that may occur
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to create the Actual work report.',
                'error' => $e->getMessage(),
            ];

            // Log the API call (assuming `logAPICalls` is a defined method in your class)
            $this->logAPICalls('createWorkreport', '', $request->all(), $response);

            // Return a 500 Internal Server Error response
            return response()->json($response, 500);
        }
    }

    //UPDATE WORK REPORT

    public function updateWorkreport(Request $request, $id)
    {
        // Validate the incoming request data using Laravel's built-in validation method
        $request->validate([
            'recommended_action' => 'required|string|max:255',
            'remarks' => 'required|string|max:255',
        ]);

        try {
            // Find the existing inspection report by ID or throw a 404 error
            $existingRequest = Actual_work::findOrFail($id);

            // Update the request data
            $existingRequest->update([
                'recommended_action' => $request->input('recommended_action'),
                'remarks' => $request->input('remarks'),
            ]);

            $response = [
                'isSuccess' => true,
                'message' => 'Actual work report updated successfully.',
                'actualwork' => $existingRequest,
            ];

            $this->logAPICalls('updateWorkreport', $id, $request->all(), $response);
            return response()->json($response, 200); // Return a 200 OK response
        } catch (Throwable $e) {
            // Handle any errors during update
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to update the actual work report.',
                'error' => $e->getMessage(),
            ];

            $this->logAPICalls('updateWorkreport', $id, $request->all(), $response);
            return response()->json($response, 500); // Return a 500 Internal Server Error response
        }
    }

    //GET WORK REPORT

    public function getWorkreport(Request $request)
    {
        try {
            // Fetch all actual work reports without filters or pagination
            $workreport = Actual_work::all();


            $response = [
                'isSuccess' => true,
                'message' => 'Actual work report retrieved successfully.',
                'actualwork' => $workreport,
            ];

            $this->logAPICalls('getWorkreport', '', $request->all(), $response);

            return response()->json($response, 200);

        } catch (Throwable $e) {

            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve the actual work reports.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('getWorkreport', '', $request->all(), $response);

            return response()->json($response, 500);
        }
    }

    //ADD MANPOWER DEPLOYMENT

    public function addManpowerDeploy(Request $request)
    {
        $ManpowerDeploy = Manpower::pluck('first_name')->toArray();
        $Manpowerlastname = Manpower::pluck('last_name')->toArray();

        $ratingInput = $request->input('rating');
        $numericRating = str_replace('%', '', $ratingInput);

        $request->validate([
            'first_name' => ['required', 'alpha_spaces', 'in:' . implode(',', $ManpowerDeploy)],
            'last_name' => ['required', 'alpha_spaces', 'in:' . implode(',', $Manpowerlastname)],
            'rating' => 'required|numeric|between:0,100',
        ]);

        try {

            $ratingInput = $request->input('rating');
            $numericRating = str_replace('%', '', $ratingInput);

            $validatedData = $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'rating' => 'required|numeric|between:0,100',
            ]);


            $ratingToStore = $numericRating . '%';

            $newManpowerDeploy = ManpowerDeployment::create([
                'first_name' => $validatedData['first_name'],
                'last_name' => $validatedData['last_name'],
                'rating' => $ratingToStore,
            ]);

            $response = [
                'isSuccess' => true,
                'message' => 'Manpower successfully added.',
                'manpowerdeployment' => $newManpowerDeploy,
            ];


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

            // Log the API call (assuming `logAPICalls` is a defined method in your class)
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