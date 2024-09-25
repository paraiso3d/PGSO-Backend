<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DivisionController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\UsertypeController;
use App\Http\Controllers\OfficeController;
use App\Http\Controllers\ManpowerController;
use App\Http\Controllers\FileUploadController;
use App\Http\Controllers\AuthController;




 

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


Route::options('/{any}', function (Request $request) {
    return response()->json(['status' => 'OK'], 200);
})->where('any', '.*');



//---- TEST FILE --\\
Route::post('upload  ', [FileUploadController::class, 'upload']);
<<<<<<< HEAD
=======
Route::post('test', [AuthController::class,'test']);
>>>>>>> f3b7c4cefab5cafc00037331c3698ba4dddac415




/*
|--------------------Division API-----------------------\
*/



Route::controller( DivisionController::class)->group( function (){
    Route::post('createDivision', 'createDivision');        // For creating a user
    Route::post('updateDivision/{id}', 'updateDivision');    // For updating a user
    Route::get('getDivisions',  'getDivisions');    
    Route::delete('delete-Division/{id}','deleteDivision');

});



/*
|--------------------LOGIN API-----------------------\
*/

Route::controller(AuthController::class)->group(function () {
    Route::post('login',  'login');
    Route::post('session',  'insertSession');
    });


    Route::middleware(['auth:sanctum', 'UserTypeAuth'])->group(function () {
        Route::middleware('auth:sanctum')->get('profile', [AuthController::class, 'viewProfile']);
        Route::middleware('auth:sanctum')->post('profile/edit', [AuthController::class, 'editProfile']);
        Route::middleware('auth:sanctum')->post('editpassword', [AuthController::class, 'changePassword']);
    
    
    
    //     Route::get('/admin/dashboard', [AuthController::class, 'admin']);
    //     Route::get('/supervisor/dashboard', [AuthController::class, 'supervisor']);
    //     Route::get('/teamleader/dashboard', [AuthController::class, 'teamleader']);
    //     Route::get('/controller/dashboard', [AuthController::class, 'controller']);
    //     Route::get('/dean/dashboard', [AuthController::class, 'dean'])
    });

/*
|--------------------LOGOUT API-----------------------\
*/

Route::middleware('auth:sanctum')->post('/logout/{id}', [AuthController::class, 'logout']);

Route::middleware(['auth:sanctum', 'session.expiry'])->group(function () {
    Route::get('/some-protected-route', [AuthController::class, 'someMethod']);

});


/*
|--------------------Request API-----------------------\
*/ 
    
Route::controller( RequestController::class)->group( function (){
    Route::post('createrequest', 'createRequest');               
    Route::post('updaterequest/{id}', 'updateRequest');    
    Route::get('getrequest',  'getRequests');    
    Route::get('getrequest/{id}',  'getRequestById');    
    Route::delete('delete-category/{id}', 'deleteCategory');
});


   
/*
|--------------------Category API-----------------------\
*/ 

    Route::controller( CategoryController::class)->group( function (){
        Route::post('createcategory', 'createCategory');        // For creating a user
        Route::post('updatecategory/{id}', 'updateCategory');    // For updating a user
        Route::get('getcategories',  'getCategories');    
        Route::delete('delete-category/{id}', 'deleteCategory');
    
    });


/*
|--------------------Location API-----------------------\
*/

    Route::controller( LocationController::class)->group( function (){
        Route::post('createlocation', 'createlocation');        // For creating a user
        Route::post('updatelocation/{id}', 'updateocation');    // For updating a user
        Route::get('getlocations',  'getlocations');    
        Route::delete('delete-location/{id}', 'deletelocation');
    
    });


 




/*
|--------------------ManPower API-----------------------\
*/

Route::controller( ManpowerController::class)->group( function (){
    Route::post('createmanpower', 'createmanpower');        
    Route::post('updmanpower/{id}', 'updatemanpower');    
    Route::get('listmanpower',  'getmanpowers');    
    Route::delete('del-manpower/{id}','deletemanpower');

});




/*
|--------------------Offices API-----------------------\
*/



Route::controller( OfficeController::class)->group( function (){
    Route::post('createoffice', 'createOffice');        
    Route::post('updoffice/{id}', 'updateOffice');    
    Route::get('listoffices',  'getOffices');    
    Route::delete('del-offices/{id}','deleteOffice');

});




/*
|--------------------USERTYPE API-----------------------\
*/

Route::controller( UserTypeController::class)->group( function (){
    Route::post('usertype', 'createUserType');        
    Route::post('usertypeup/{id}', 'updateUserType');    
    Route::get('usertypes',  'getUserTypes');    
    Route::delete('user-types/{id}','deleteUserType');

});

/*
|--------------------USERS API-----------------------\
*/

Route::controller( UserController::class)->group( function (){
Route::post('user', 'createUserAccount');        // For creating a user
Route::post('user/{id}', 'updateUserAccount');    // For updating a user
Route::get('users',  'getUserAccounts'); 
Route::post('users/{id}','deleteUserAccount');         // For fetching users
Route::post('session',  'insertSession');

});

/*
|--------------------TEST API-----------------------\
*/
Route::controller(BaseController::class)->group(function () {
Route::post('createCustomer', 'createCustomer');
Route::post('createCustomer', 'updateCustomer');
Route::get('getCustomers', 'getCustomers');
// Route::post('user', 'createUser');        // For creating a user
// Route::post('user/{id}', 'updateUser');    // For updating a user
// Route::get('users',  'getUsers');          // For fetching users
// //Route::post('session',  'insertSession');  // For inserting a session

});

//example - having a middleware
// Route::controller(BaseController::class)->middleware(['auth:sanctum'])->group(function () {
//     Route::get('get', 'getAll')->middleware('teacher');
// });