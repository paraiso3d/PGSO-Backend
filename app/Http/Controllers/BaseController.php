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

    public function saveImage($image,string $path,string $empno, string $ln) {

        if(preg_match('/^data:image\/(\w+);base64,/', $image, $type)) 
        {
            $image = substr($image, strpos($image,',') + 1);
            $type = strtolower($type[1]);
            
            if(!in_array($type, ['jpg', 'jpeg', 'gif', 'png','svg'])) {
                throw new \Exception('The provided image is invalid.');
            }

            $image = str_replace(' ', '+', $image);
            $image = base64_decode($image);

            if($image === false) {
                throw new \Exception('Unable to process the image.');
            }
        }else 
        {
            throw new \Exception('Please make sure the type you are uploading is an image file.');
        }

        $dir = 'img/' . $path . '/';
        $file = $empno . '-' . $ln . '.' . $type;
        $absolutePath = public_path($dir);
        $relativePath = $dir . $file;
        if(!File::exists($absolutePath)) 
        {
            File::makeDirectory($absolutePath, 0755, true);
        }
        file_put_contents($relativePath, $image);

        return $file;
    }

public function addAsset(Request $req)
    {
        $dateNow =  Carbon::now();
        $fdateNow = $dateNow->format('Y-m-d');
        $ftimeNow = $dateNow->format('His');
        try
        {
            if($req->asset_photo) 
            {
                $asset = Assets::create([
                    'asset_name' => $req->asset_name,
                    'asset_model_no' => $req->asset_model_no,
                    'asset_specification' => $req->asset_specification,
                    'asset_price' => $req->asset_price,
                    'asset_date_acquired' => $req->asset_date_acquired,
                    'asset_supplier_id' => $req->asset_supplier_id,
                    'asset_photo' => $path . $file,
                    'asset_category_id' => $req->asset_category_id,
                    'asset_quantity' => $req->asset_quantity,
                    'asset_date_warranty' => $req->asset_date_warranty,
                    'asset_department_id' => $req->asset_department_id,
                    'asset_office_id' => $req->asset_office_id,
                 ]);
                 $path = $this->getSetting("ASSET_IMAGE_PATH");
                $name = str_replace(" ","",$req->asset_name);
                $file = (new AuthController)->saveImage($req->asset_photo,"asset",'Asset-' . $asset->id,$fdateNow . '_' . $ftimeNow);
                      
            return $this->sendResponse($req->all(),"You have added an asset.");     
			}
		}
	}



}
