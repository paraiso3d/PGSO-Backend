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
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\InspectionController;
use App\Http\Controllers\ActualWorkController;






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



/*
|--------------------LOGIN API-----------------------\
*/

Route::controller(AuthController::class)->group(function () {
    Route::post('login', 'login');
    Route::post('session', 'insertSession');
});


/*
|--------------------USERS API-----------------------\
*/

Route::controller(UserController::class)->group(function () {
    Route::post('createUser', 'createUserAccount');        
    Route::post('updateUser/{id}', 'updateUserAccount');    
    Route::get('userList', 'getUserAccounts');
    Route::get('userDropdown', 'getDropdownOptions');
    Route::post('deleteUser/{id}', 'deleteUserAccount');        

});

/*
|--------------------Division API-----------------------\
*/

Route::controller(DivisionController::class)->group(function () {
    Route::post('createDivision', 'createDivision');
    Route::post('updateDivision/{id}', 'updateDivision');
    Route::get('divisionList', 'getDivisions');
    Route::post('deleteDivision/{id}', 'deleteDivision');

});



/*
|--------------------Profile Api-----------------------\
*/

Route::middleware(['auth:sanctum', 'UserTypeAuth'])->group(function () {
    Route::middleware('auth:sanctum')->get('profile', [AuthController::class, 'viewProfile']);
    Route::middleware('auth:sanctum')->post('profile/edit', [AuthController::class, 'editProfile']);
    Route::middleware('auth:sanctum')->post('editpassword', [AuthController::class, 'changePassword']);


});

/*
|--------------------LOGOUT API-----------------------\
*/

Route::middleware('auth:sanctum')->post('logout', [AuthController::class, 'logout']);



/*
|--------------------Request API-----------------------\
*/

Route::controller(RequestController::class)->group(function () {
    Route::post('createRequest', 'createRequest');
    Route::post('updateRequest/{id}', 'updateRequest');
    Route::get('requestList', 'getRequests');
    Route::post('deleteCategory/{id}', 'deleteCategory');
});

Route::middleware(['auth:sanctum'])->group(function () {
    // Admin can access all CRUD routes
    Route::middleware('UserTypeAuth:Administrator')->group(function () {
        Route::post('admin-createrequest', [RequestController::class, 'createRequest']);
        Route::post('admin-updaterequest/{id}', [RequestController::class, 'updateRequest']);
        Route::get('admin-getrequest', [RequestController::class, 'getRequests']);
    });
    Route::middleware('UserTypeAuth:Controller,Supervisor,TeamLeader')->group(function () {
        Route::get('user-getrequest', [RequestController::class, 'getRequests']);

    });
    Route::middleware('UserTypeAuth:DeanHead')->group(function () {
        Route::post('dean-createrequest', [RequestController::class, 'createRequest']);
        Route::get('dean-getrequest', [RequestController::class, 'getRequests']);
    });
});

/*
|--------------------Category API-----------------------\
*/

Route::controller(CategoryController::class)->group(function () {
    Route::post('createCategory', 'createCategory');        
    Route::post('updateCategory/{id}', 'updateCategory');    
    Route::get('categoryList', 'getCategory');
    Route::post('deleteCategory/{id}', 'deleteCategory');

});


/*
|--------------------Location API-----------------------\
*/

Route::controller(LocationController::class)->group(function () {
    Route::post('createLocation', 'createlocation');
    Route::post('updateLocation/{id}', 'updateocation');
    Route::get('locationList', 'getlocations');
    Route::post('deleteLocation/{id}', 'deletelocation');

});



/*
|--------------------ManPower API-----------------------\
*/

Route::controller(ManpowerController::class)->group(function () {
    Route::post('createManpower', 'createmanpower');
    Route::post('updateManpower/{id}', 'updatemanpower');
    Route::get('manpowerList', 'getmanpowers');
    Route::post('deleteManpower/{id}', 'deletemanpower');
    
});




/*
|--------------------Offices API-----------------------\
*/

Route::controller(OfficeController::class)->group(function () {
    Route::post('createOffice', 'createOffice');
    Route::post('updateOffice/{id}', 'updateOffice');
    Route::get('officeList', 'getOffices');
    Route::post('deleteOffice/{id}', 'deleteOffice');

});




/*
|--------------------USERTYPE API-----------------------\
*/

Route::controller(UserTypeController::class)->group(function () {
    Route::post('createUsertype', 'createUserType');
    Route::post('updateUsertype/{id}', 'updateUserType');
    Route::get('usertypeList', 'getUserTypes');
    Route::post('deleteUsertype/{id}', 'deleteUserType');

});

/*
|--------------------REVIEW API-----------------------\
*/

Route::middleware(['auth:sanctum'])->group(function () {
    // Admin can access all CRUD routes
    Route::middleware('UserTypeAuth:Administrator')->group(function () {
        Route::post('admin-updatereview/{id}', [ReviewController::class, 'updateReview']);
        Route::get('admin-getreviews', [ReviewController::class, 'getReviews']);
    });
    Route::middleware('UserTypeAuth:Controller,Supervisor,TeamLeader')->group(function () {
        Route::post('user-updatereview/{id}', [ReviewController::class, 'getReviews']);
        Route::get('user-getreviews', [ReviewController::class, 'getReviews']);
    });
    Route::middleware('UserTypeAuth:DeanHead')->group(function () {
        Route::get('dean-getreviews', [ReviewController::class, 'getReviews']);
    });
});

/*
|--------------------Actual Work API-----------------------\
*/

Route::controller(ActualWorkController::class)->group(function () {
    Route::post('createworkreport', 'createWorkreport');
    Route::post('updateworkreport/{id}', 'updateWorkreport');
    Route::get('getworkreport', 'getWorkreport');
    Route::post('addmanpowerdeploy','addManpowerDeploy');
});

/*
|--------------------Inspection API-----------------------\
*/

Route::controller(InspectionController::class)->group(function () {
    Route::get('getinspection','getInspections');
    Route::post('createinspection', 'createInspection');
    Route::post('updateinspection/{id}', 'updateInspection');
});


/*
|--------------------TEST API-----------------------\
*/
// Route::controller(BaseController::class)->group(function () {
//     Route::post('createCustomer', 'createCustomer');
//     Route::post('createCustomer', 'updateCustomer');
//     Route::get('getCustomers', 'getCustomers');
// });