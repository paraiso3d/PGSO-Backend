<?php
namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\ApiLog;
use Carbon\Carbon;
use App\Models\Session;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Throwable;
use DB;

class BaseController extends Controller
{
    /**
     * Create a new user.
     */
    public function createUser(Request $request)
    {
        try {
            $request->validate([
                'name' => ['required', 'string'],
                'email'=> ['string', 'required', 'email', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8']
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password)
            ]);

            $response = [
                'isSuccess' => true,
                'message' => "User successfully created."
            ];
            $this->logAPICalls('createUser', $user->id, $request->all(), [$response]);
            return response()->json($response, 201);
        }
        catch (ValidationException $v) {
            $response = [
                'isSuccess' => false,
                'message' => "Invalid input data.",
                'error' => $v->errors()
            ];
            $this->logAPICalls('createUser', "", $request->all(), [$response]);
            return response()->json($response, 422);
        }
        catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to create a user. Please try again.",
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('createUser', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
    }

    /**
     * Update an existing user.
     */
    public function updateUser(Request $request, $id)
    {
        try {
           
            $user = User::findOrFail($id); // Find the user or throw 404

            $user->update([
                'name' => $request->name,
                'email' => $request->email
            ]);

            $response = [
                'isSuccess' => true,
                'message' => "User successfully updated."
            ];
            $this->logAPICalls('updateUser', $user->id, $request->all(), [$response]);
            return response()->json($response, 200);
        }
        catch (ValidationException $v) {
            $response = [
                'isSuccess' => false,
                'message' => "Invalid input data.",
                'error' => $v->errors()
            ];
            $this->logAPICalls('updateUser', "", $request->all(), [$response]);
            return response()->json($response, 422);
        }
        catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to update the user.",
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('updateUser', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
    }

    /**
     * Get all active users.
     */
    public function getUsers()
    {
        try {
            $users = User::where('status', 'A')->get(); // Assuming 'A' is for active users

            $response = [
                'isSuccess' => true,
                'message' => "Users list:",
                'data' => $users
            ];
            $this->logAPICalls('getUsers', "", [], [$response]);
            return response()->json($response, 200);
        }
        catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to retrieve users.",
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('getUsers', "", [], [$response]);
            return response()->json($response, 500);
        }
    }

    /**
     * Insert a new session for a user.
     */
    public function insertSession(Request $request)
{
    $request->validate([
        'user_id' => 'required|string|exists:users,id'
    ]);

    $sessionCode = Str::uuid();  
    $dateTime = Carbon::now()->toDateTimeString();

    try {
        Session::create([
            'session_code' => $sessionCode,
            'user_id' => $request->user_id,
            'login_date' => $dateTime
        ]);
        return response()->json(['isSuccess' => true, 'message' => 'Session successfully created.', 'session_code' => $sessionCode], 201);
    } catch (Throwable $e) {
        return response()->json(['isSuccess' => false, 'message' => 'Failed to create session.', 'error' => $e->getMessage()], 500);
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
            return false;
        }
        return true;
    }

    /**
     * Get a setting value by its code.
     */
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

    /**
     * Standard response method.
     */
    public function sendResponse($result, $message)
    {
        $response = [
            'isSuccess' => true,
            'message' => $message,
            'data' => $result
        ];
        return response($response, 200);
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
