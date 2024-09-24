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
                $this->logAPICalls('login', $user->id, $request->except(['password']), $response);
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

public function viewProfile(Request $request)
{
try {
    $user = $request->user(); // Get the authenticated user

    // Return the user's profile information
    return response()->json([
        'message' => 'Profile retrieved successfully',
        'user' => $user->only([ 'email', 'last_name', 'first_name', 'middle_initial','profile_image','signature']), // Include new fields
    ], 200);
} catch (Throwable $e) {
    // Prepare the error response
    $response = [
        'message' => 'Failed to retrieve profile',
        'error' => $e->getMessage(),
    ];

    // Log API call with error information
    $this->logAPICalls('viewProfile', $request->user()->email, $request->all(), $response);

    return response()->json($response, 500);
}
}

public function editProfile(Request $request)
{
    $user = $request->user(); // Get the authenticated user

    // Validate request input
    $request->validate([
        'lastname' => 'required|string',
        'firstname' => 'required|string',
        'middleinitial' => 'nullable|string',
        'email' => 'required|email|unique:user_accounts,email,' . $user->id,
        'profile_image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120', // Adjust size as needed
        'signature' => 'nullable|image|mimes:jpeg,png,jpg|max:5120', // Adjust size as needed
    ]);

    try {
        
        // Update user information
        $user->update($request->only(['last_name', 'first_name', 'middle_initial', 'email','profile_image','signature']));

        // Handle profile image upload
        if ($request->hasFile('profile_image')) {
        $profileImagePath = $request->file('profile_image')->store('profile_images', 'public');
        $dataToUpdate['profile_image'] = $profileImagePath; // Add the path to the data to update
        }

        // Handle signature upload
        if ($request->hasFile('signature')) {
        $signaturePath = $request->file('signature')->store('signatures', 'public');
        $dataToUpdate['signature'] = $signaturePath; // Add the path to the data to update
        }

        // Prepare response
        $response = [
            'message' => 'Profile updated successfully',
            'user' => $user->only(['last_name', 'first_name', 'middle_initial', 'email']),
        ];

        // Log API call
        $this->logAPICalls('editProfile', $user->email, $request->all(), $response); 

        return response()->json($response, 200);
    } catch (Throwable $e) {
        $response = [
            'message' => 'An error occurred',
            'error' => $e->getMessage(),
        ];

        // Log API call
        $this->logAPICalls('editProfile', $user->email, $request->all(), $response); 

        return response()->json($response, 500);
    }
}

public function changePassword(Request $request)
{
$user = $request->user(); // Get the authenticated user

// Validate the input
$request->validate([
    'password' => 'required',
    'new_password' => 'required|min:8|confirmed', 
    'new_password_confirmation'=> 'required|min:8',
]);

try {
    // Check if the current password is correct
    if (!Hash::check($request->password, $user->password)) {
        $response = ['message' => 'Current password is incorrect'];
        $this->logAPICalls('changePassword', $user->email, $request->except(['password', 'new_password', 'new_password_confirmation']), $response); // Log API call
        return response()->json($response, 400); // 400 Bad Request
    }

    // Update the password
    $user->password = Hash::make($request->new_password); // Hash the new password
    $user->save(); // Save the updated user data

    // Prepare response
    $response = ['message' => 'Password changed successfully'];

    // Exclude passwords from being logged
    $this->logAPICalls('changePassword', $user->email, $request->except(['password','new_password', 'new_password_confirmation']), $response); // Log API call
    return response()->json($response, 200); // 200 OK
} catch (Throwable $e) {
    // Prepare error response
    $response = [
        'message' => 'Failed to change password',
        'error' => $e->getMessage(),
    ];

    // Exclude passwords from being logged
    $this->logAPICalls('changePassword', $user->email, $request->except(['password','new_password', 'new_password_confirmation']), $response);

    return response()->json($response, 500); // 500 Internal Server Error
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
