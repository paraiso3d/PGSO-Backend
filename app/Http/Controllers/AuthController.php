<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Session;
use App\Models\ApiLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Throwable;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            //Validate request input
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            // Find the user by email
            $user = User::where('email', $request->email)->first();

            if($user){
                if (Hash::check($request->password, $user->password)) {
                //Generate token based on usertype
                $token = null;
                switch ($user->user_type) {
                    case 'Admin':
                        $token = $user->createToken('admin-token', ['admin'])->plainTextToken;
                        break;
                    case 'Supervisor':
                        $token = $user->createToken('supervisor-token', ['supervisor'])->plainTextToken;
                        break;
                    case 'Teamleader':
                        $token = $user->createToken('teamleader-token', ['teamleader'])->plainTextToken;
                        break;
                    case 'Controller':
                        $token = $user->createToken('controller-token', ['controller'])->plainTextToken;
                        break;
                    case 'Dean':
                        $token = $user->createToken('dean-token', ['dean'])->plainTextToken;
                        break;
                    default:
                        $response = ['message' => 'Unauthorized'];
        
            $this->logAPICalls('login', $request->email, $request->all(), $response); // Log API call
            return response()->json($response, 403);
}

                $sessionResponse = $this->insertSession($request->merge(['id' => $user->id]));

                //Log successful login
                $response = [
                    'message' => ucfirst($user->usertype) . ' logged in successfully',
                    'token' => $token,  
                    'user' => $user->only(['id', 'email']),
                    'usertype' => $user->usertype,
                    'session' => $sessionResponse->getData(),
                ];
            $this->logAPICalls('login', $user->id, $request->all(), $response); // Log API call
            return response()->json($response, 200);

        } else {
                    
            $response = ['message' => 'Invalid credentials'];
            $this->logAPICalls('login', $request->email, $request->all(), $response); 
            return response()->json($response, 401); 
        }
    } else {
        $response = ['message' => 'Invalid credentials'];
        $this->logAPICalls('login', $request->email, $request->all(), $response);
        return response()->json($response, 401); 
    }
} catch (Throwable $e) {
    $response = [
        'message' => 'An error occurred',
        'error' => $e->getMessage() // Return the specific error message
    ];
    $this->logAPICalls('login', $request->email, $request->all(), $response); 
    return response()->json($response, 500);
}
}

    // Method to insert session
    public function insertSession(Request $request)
    {
        try {
            $request->validate([
                'user_id' => 'get|string|exists:user,id' // Ensure this table name is correct
            ]);

            $sessionCode = Str::uuid();  
            $dateTime = Carbon::now()->toDateTimeString();

            Session::create([
                'session_code' => $sessionCode,
                'user_id' => $request->id,
                'login_date' => $dateTime
            ]);

            return response()->json(['isSuccess' => true, 'message' => 'Session successfully created.', 'session_code' => $sessionCode], 201);

        } catch (Throwable $e) {
            return response()->json(['isSuccess' => false, 'message' => 'Failed to create session.', 'error' => $e->getMessage()], 500);
        }
    }

    // Method to log API calls
    public function logAPICalls(string $methodName, string $userId, array $param, array $resp)
    {
        try {
            ApiLog::create([
                'method_name' => $methodName,
                'user_id' => $userId,
                'api_request' => json_encode($param),
                'api_response' => json_encode($resp),
            ]);
        } catch (Throwable $e) {
            return false;
        }
        return true;
    }
}
