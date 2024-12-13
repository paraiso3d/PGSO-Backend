<?php
namespace App\Http\Controllers;

use Auth;
use DB;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\user_type;
use App\Models\Session;
use Exception;
use App\Models\ApiLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Throwable;
use Illuminate\Support\Facades\Storage;


class AuthController extends Controller
{

    public function login(Request $request)
    {
        try {
            // Fetch the user by email
            $user = User::where('email', $request->email)->first();
    
            if ($user) {
                // Verify the password
                if (Hash::check($request->password, $user->password)) {
                    // Generate token
                    $token = $user->createToken('auth-token')->plainTextToken;
    
                    // Attempt to create session
                    $session = $this->insertSession($user->id);
                    if (!$session) {
                        // Return error if session creation fails
                        return response()->json(['isSuccess' => false, 'message' => 'Failed to create session.'], 500);
                    }
    
                    // Get user type name
                    $roleName = $user->role_name;
    
                    // Prepare response
                    $response = [
                        'isSuccess' => true,
                        'user' => [
                            'id' => $user->id,
                            'email' => $user->email,
                            'name' => $user->first_name . ' ' . $user->last_name, // Concatenate first and last name
                        ],
                        'token' => $token,
                        'sessionCode' => $session,
                        'role' => $roleName,
                        'message' => 'Logged in successfully'
                    ];
    
                    // Log the API call
                    $this->logAPICalls('login', $user->email, $request->except(['password']), $response);
    
                    // Return success response
                    return response()->json($response, 200);
    
                } else {
                    return $this->sendError('Invalid Credentials.');
                }
            } else {
                return $this->sendError('Provided email address does not exist.');
            }
    
        } catch (Throwable $e) {
            // Handle errors during login
            $response = [
                'isSuccess' => false,
                'message' => 'An error occurred during login.',
                'error' => $e->getMessage(),
            ];
    
            $this->logAPICalls('login', $request->email ?? 'unknown', $request->except(['password']), $response);
    
            // Return error response
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
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'signature' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        try {
            // Update user information except profile image and signature
            $user->update($request->only(['last_name', 'first_name', 'middle_initial', 'email']));

            $profileImagePath = null;
            $signaturePath = null;

            // Handle profile image upload in base64 format
            if ($request->hasFile('profile_image')) {
                $profileImageContents = file_get_contents($request->file('profile_image')->getRealPath());
                $base64ProfileImage = 'data:image/' . $request->file('profile_image')->extension() . ';base64,' . base64_encode($profileImageContents);

                // Save the image using saveImage method
                $path = $this->getSetting("ASSET_IMAGE_PATH");
                $fdateNow = now()->format('Y-m-d');
                $ftimeNow = now()->format('His');
                $profileImagePath = (new AuthController)->saveImage($base64ProfileImage, 'profile', 'Profile-' . $user->id, $fdateNow . '_' . $ftimeNow);

                // Update the profile image path in user record
                $user->update(['profile_image' => $profileImagePath]);
            }

            // Handle signature upload in base64 format
            if ($request->hasFile('signature')) {
                $signatureContents = file_get_contents($request->file('signature')->getRealPath());
                $base64Signature = 'data:image/' . $request->file('signature')->extension() . ';base64,' . base64_encode($signatureContents);

                // Save the signature using saveImage method
                $signaturePath = (new AuthController)->saveImage($base64Signature, 'signature', 'Signature-' . $user->id, $fdateNow . '_' . $ftimeNow);

                // Update the signature path in user record
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
            // Get the authenticated user
            $user = $request->user();

            if ($user) {
                // Find the user's active session
                $session = Session::where('user_id', $user->id)
                    ->whereNull('logout_date')
                    ->latest()
                    ->first();

                if ($session) {
                    // Update the session's logout date
                    $session->update([
                        'logout_date' => Carbon::now()->toDateTimeString(),
                    ]);
                }

                // Revoke only the current access token
                $user->currentAccessToken()->delete();

                // Prepare the response with only required fields
                $response = [
                    'isSuccess' => true,
                    'message' => 'Logged out successfully',
                    'sessionCode' => [
                        'session_code' => $session->session_code,
                        'logout_date' => $session->logout_date,
                    ],
                    'user' => $user->only(['id', 'email']),
                ];

                // Log the API call for auditing
                $this->logAPICalls('logout', $user->email, $request->all(), $response);

                return response()->json($response, 200);
            } else {
                return $this->sendError('User not found or already logged out.', 500);
            }
        } catch (Throwable $e) {
            // Define the error response
            $response = [
                'isSuccess' => false,
                'message' => 'An error occurred during logout.',
            ];

            $this->logAPICalls('logout', 'unknown', $request->all(), $response);
            return response()->json($response, 500);
        }
    }

    // // Method to insert session
    public function insertSession(int $userId)
    {
        try {
            $sessionCode = Str::uuid(); // Generate a unique session code
            $dateTime = Carbon::now()->toDateTimeString();

            // Insert session record into the database
            Session::create([
                'session_code' => $sessionCode,
                'user_id' => $userId,
                'login_date' => $dateTime,
                'logout_date' => null, // Initially set logout_date to null
            ]);

            return $sessionCode; // Return the generated session code

        } catch (Throwable $e) {
            Log::error('Failed to create session.', ['error' => $e->getMessage()]);
            return null; // Return null if session creation fails
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

    public function saveImage($image, string $path, string $empno, string $ln)
    {
        \Log::info('Base64 Image Data: ', ['image' => $image]); // Log the image data
        if (preg_match('/^data:(image|application)\/(\w+);base64,/', $image, $type)) {
            $image = substr($image, strpos($image, ',') + 1);
            $type = strtolower($type[2]);
    
            if (!in_array($type, ['mp4','pdf', 'jpg', 'jpeg', 'gif', 'png', 'svg'])) {
                throw new Exception('The provided file or image is invalid.');
            }
    
            $image = str_replace(' ', '+', $image);
            $image = base64_decode($image);
    
            if ($image === false) {
                throw new Exception('Unable to process the image.');
            }
        } else {
            throw new Exception('Please make sure the type you are uploading is an image or PDF file.');
        }
    
        $dir = 'img/' . $path . '/';
        $file = $empno . '-' . $ln . '.' . $type;
        $absolutePath = public_path($dir);
    
        if (!File::exists($absolutePath)) {
            File::makeDirectory($absolutePath, 0755, true);
        }
    
        file_put_contents($absolutePath . $file, $image);
    
        return $dir . $file; // Return the relative path for URL generation
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

    public function test()
    {
        return response()->json([
            'isSuccess' => true,
            'message' => 'Test successful'
        ], 200);
    }

    public function sendError($error, $errorMessages = [], $code = 404)
    {
        $response = [
            'isSuccess' => false,
            'message' => $error,
        ];

        if (!empty($errorMessages)) {
            $response['data'] = $errorMessages;
        }

        return response()->json($response, $code);
    }
}

