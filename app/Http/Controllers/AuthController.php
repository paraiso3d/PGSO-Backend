<?php
namespace App\Http\Controllers;

use Auth;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\user_type;
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

            if ($user) {
                if (Hash::check($request->password, $user->password)) {
                    //Generate token based on usertype
                    $token = null;
                    switch ($user->user_type) {
                        case 'Administrator':
                            $token = $user->createToken('admin-token', ['Administrator'])->plainTextToken;
                            break;
                        case 'Supervisor':
                            $token = $user->createToken('supervisor-token', ['Supervisor'])->plainTextToken;
                            break;
                        case 'TeamLeader':
                            $token = $user->createToken('teamleader-token', ['TeamLeader'])->plainTextToken;
                            break;
                        case 'Controller':
                            $token = $user->createToken('controller-token', ['Controller'])->plainTextToken;
                            break;
                        case 'DeanHead':
                            $token = $user->createToken('dean-token', ['DeanHead'])->plainTextToken;
                            break;
                        default:
                            $response = ['message' => 'Unauthorized'];

                            $this->logAPICalls('login', $request->email, $request->all(), $response); // Log API call
                            return response()->json($response, 403);
                    }

                    $sessionResponse = $this->insertSession($request->merge(['id' => $user->id]));

                    //Log successful login
                    $response = [
                        'isSuccess' => true,
                        'message' => ucfirst($user->user_type) . ' logged in successfully',
                        'token' => $token,
                        'user' => $user->only(['id', 'email']),
                        'user_type' => $user->user_type,
                        'session' => $sessionResponse->getData(),
                    ];
                    $this->logAPICalls('login', $user->id, $request->except(['password']), $response);
                    return response()->json($response, 200);

                } else {

                    $response = ['message' => 'Invalid credentials'];
                    $this->logAPICalls('login', $request->email, $request->except(['password']), $response);
                    return response()->json($response, 401);
                }
            } else {
                $response = ['message' => 'Invalid credentials'];
                $this->logAPICalls('login', $request->email, $request->except(['password']), $response);
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

    public function editProfile(Request $request)
    {
        $user = $request->user(); // Get the authenticated user

        // Validate request input
        $request->validate([
            'last_name' => 'required|string',
            'first_name' => 'required|string',
            'middle_initial' => 'nullable|string',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120', // Adjust size as needed
            'signature' => 'nullable|image|mimes:jpeg,png,jpg|max:5120', // Adjust size as needed
        ]);

        try {
            // Update user information
            $user->update($request->only(['last_name', 'first_name', 'middle_initial', 'email']));

            // Handle profile image upload
            if ($request->hasFile('profile_image')) {
                $profileImagePath = $request->file('profile_image')->store('profile_images', 'public');
                $user->update(['profile_image' => $profileImagePath]);
            }

            // Handle signature upload
            if ($request->hasFile('signature')) {
                $signaturePath = $request->file('signature')->store('signatures', 'public');
                $user->update(['signature' => $signaturePath]);
            }

            // Prepare response
            $response = [
                'isSuccess' => true,
                'message' => 'Profile updated successfully',
                'user' => $user->only(['last_name', 'first_name', 'middle_initial', 'email']),
            ];

            // Log API call
            $this->logAPICalls('editProfile', $user->email, $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
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
            'new_password_confirmation' => 'required|min:8',
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
            $this->logAPICalls('changePassword', $user->email, $request->except(['password', 'new_password', 'new_password_confirmation']), $response); // Log API call
            return response()->json($response, 200); // 200 OK
        } catch (Throwable $e) {
            // Prepare error response
            $response = [
                'isSuccess' => true,
                'message' => 'Failed to change password',
                'error' => $e->getMessage(),
            ];

            // Exclude passwords from being logged
            $this->logAPICalls('changePassword', $user->email, $request->except(['password', 'new_password', 'new_password_confirmation']), $response);

            return response()->json($response, 500); // 500 Internal Server Error
        }
    }

    public function logout(Request $request)
    {
        try {
            $user = Auth::user();  // Get the authenticated user using the Auth facade

            if ($user) {
                Log::info('User logging out:', ['email' => $user->email]);

                // Find the latest session for this user with a null logout_date
                $session = Session::where('user_id', $user->id)
                    ->whereNull('logout_date')  // Find session where logout hasn't been set
                    ->latest()  // Get the latest session
                    ->first();

                if ($session) {
                    // Update the session with the current logout date
                    $session->update([
                        'logout_date' => Carbon::now()->toDateTimeString(),  // Set logout date to current time
                    ]);
                }

                // Revoke the user's current access token (token-based auth)
                if ($user->currentAccessToken()) {
                    $user->currentAccessToken()->delete();
                }

                $response = ['message' => 'User logged out successfully'];
                $this->logAPICalls('logout', $user->id, [], $response);

                return response()->json($response, 200);
            }

            // If no authenticated user is found, return unauthenticated response
            return response()->json(['message' => 'Unauthenticated'], 401);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Failed to log out',
                'error' => $e->getMessage(),
            ], 500);
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

            return response()->json(['message' => 'Session successfully created.', 'session_code' => $sessionCode], 201);

        } catch (Throwable $e) {
            return response()->json(['message' => 'Failed to create session.', 'error' => $e->getMessage()], 500);
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
