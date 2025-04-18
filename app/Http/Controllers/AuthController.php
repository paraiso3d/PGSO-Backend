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
                // Check if the user is archived or inactive
                if ($user->is_archived == 1 || $user->status != 'Active') {
                    return $this->sendError('Your account is not active or has been archived.');
                }
    
                // Verify the password
                if (Hash::check($request->password, $user->password)) {
                    // Generate token
                    $token = $user->createToken('auth-token')->plainTextToken;
    
                    // Attempt to create session
                    $session = $this->insertSession($user->id);
                    if (!$session) {
                        return response()->json(['isSuccess' => false, 'message' => 'Failed to create session.'], 500);
                    }
    
                    // Get user role
                    $roleName = $user->role_name;
    
                    // Prepare response
                    $response = [
                        'isSuccess' => true,
                        'user' => [
                            'id' => $user->id,
                            'email' => $user->email,
                            'first_name' => $user->first_name,
                            'last_name' => $user->last_name,
                            'avatar' => $user->profile,
                            'age' => $user->age,
                            'gender' => $user->gender,
                            'number' => $user->number,
                        ],
                        'token' => $token,
                        'sessionCode' => $session,
                        'role' => $roleName,
                        'message' => 'Logged in successfully'
                    ];
    
                    $this->logAPICalls('login', $user->email, $request->except(['password']), $response);
    
                    return response()->json($response, 200);
                } else {
                    return $this->sendError('Wrong Password.');
                }
            } else {
                return $this->sendError('Provided email address does not exist.');
            }
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'An error occurred during login.',
                'error' => $e->getMessage(),
            ];
    
            $this->logAPICalls('login', $request->email ?? 'unknown', $request->except(['password']), $response);
    
            return response()->json($response, 500);
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

