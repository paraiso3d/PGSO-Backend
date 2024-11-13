<?php

use App\Http\Controllers\AccomplishmentReportController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DivisionController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
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
|--------------------LOGIN/LOGOUT API-----------------------\
*/

Route::controller(AuthController::class)->group(function () {
    Route::post('login', 'login');
    Route::post('session', 'insertSession');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
});


/*
|--------------------Profile Api-----------------------\
*/

Route::prefix('users')->middleware(['auth:sanctum'])->group(function () {
    Route::get('profile/get', [AuthController::class, 'viewProfile']);
    Route::post('profile/edit', [AuthController::class, 'editProfile']);
    Route::post('password/change', [AuthController::class, 'changePassword']);
    Route::post('logout', [AuthController::class, 'logout']);
});

/*
|--------------------USERS API-----------------------\
*/

Route::prefix('admin')->controller(UserController::class)->group(function () {
    Route::post('user/create', 'createUserAccount');
    Route::post('user/update/{id}', 'updateUserAccount');
    Route::get('users/get', 'getUserAccounts');
    Route::post('user/delete/{id}', 'deleteUserAccount');
    Route::get('dropdown/user/get', 'getDropdownOptionsUsertype');
    Route::get('dropdown/office/get', 'getDropdownOptionsUseroffice');
});


/*
|--------------------Division API-----------------------\
*/

Route::controller(DivisionController::class)->group(function () {
    Route::post('division/create', 'createDivision');
    Route::post('division/update/{id}', 'updateDivision');
    Route::get('divisions/get', 'getDivisions');
    Route::post('division/delete/{id}', 'deleteDivision');
    Route::get('dropdown/category/get', 'getdropdownCategories');
    Route::get('dropdown/supervisor/get', 'dropdownSupervisor');


});


/*
|--------------------Category API-----------------------\
*/

Route::controller(CategoryController::class)->group(function () {
    Route::post('category/create', 'createCategory');
    Route::post('category/update/{id}', 'updateCategory');
    Route::get('categories/get', 'getCategory');
    Route::post('delete/category/{id}', 'deleteCategory');
    Route::get('dropdown/categories/get', 'getDropdownOptionsCategory');
    Route::get('dropdown/teamleaders/get', 'getdropdownteamleader');

});

/*
|--------------------Location API-----------------------\
*/

Route::prefix('admin')->controller(LocationController::class)->group(function () {
    Route::post('location/create', 'createlocation');
    Route::post('location/update/{id}', 'updatelocation');
    Route::get('locations/get', 'getlocations');
    Route::post('location/delete/{id}', 'deletelocation');

});

/*
|--------------------ManPower API-----------------------\
*/

Route::prefix('admin')->controller(ManpowerController::class)->group(function () {
    Route::post('manpower/create', 'createmanpower');
    Route::post('manpower/update/{id}', 'updatemanpower');
    Route::get('manpowers/get', 'getmanpowers');
    Route::post('manpower/delete/{id}', 'deletemanpower');

});

/*
|--------------------Offices API-----------------------\
*/

Route::prefix('admin')->controller(OfficeController::class)->group(function () {
    Route::post('office/create', 'createOffice');
    Route::post('office/update/{id}', 'updateOffice');
    Route::get('offices/get', 'getOffices');
    Route::post('office/delete/{id}', 'deleteOffice');
});

/*
|--------------------USERTYPE API-----------------------\
*/

Route::prefix('admin')->controller(UserTypeController::class)->group(function () {
    Route::post('user-type/create', 'createUserType');
    Route::post('user-type/update/{id}', 'updateUserType');
    Route::get('user-types/get', 'getUserTypes');
    Route::post('user-type/toggle/{id}', 'toggleUsertype');
    Route::post('user-type/delete/{id}', 'deleteUserType');

});


/*
|--------------------Request API-----------------------\
*/

Route::controller(RequestController::class)->group(function () {
    //REQUEST DROPDOWN API
    Route::get('dropdown/location/get', 'getDropdownOptionsRequestslocation');
    Route::get('dropdown/status/get', 'getDropdownOptionsRequeststatus');
    Route::get('dropdown/year/get', 'getDropdownOptionsRequestyear');
    Route::get('dropdown/division/get', 'getDropdownOptionsRequestdivision');
    Route::get('dropdown/category/get', 'getDropdownOptionsRequestcategory');
    Route::get('dropdown/office/get', 'getDropdownOptionscreateRequestsoffice');
});

Route::middleware('auth:sanctum')->get('requests/get', [RequestController::class, 'getRequests']);
Route::middleware('auth:sanctum')->post('request/create', [RequestController::class, 'createRequest']);
Route::middleware('auth:sanctum')->post('request/return/{id}', [RequestController::class, 'updateReturn']);
Route::middleware('auth:sanctum')->post('request/assess/{id}', [RequestController::class, 'assessRequest']);

//ACCOMPLISHMENT
Route::middleware('auth:sanctum')->group(function () {
    Route::post('accomplishment/save/{id?}', [AccomplishmentReportController::class, 'saveAccomplishmentReport']);

    //Feedback
    Route::post('feedback/save/{id?}', [FeedbackController::class, 'saveFeedback']);
});

//REVIEW
Route::controller(ReviewController::class)->group(function () {
    Route::post('review/update/{id}', 'updateReview');
    Route::get('review/get/{id}', 'getReviews');
    Route::post('returnReview/{id}', 'returnReview');
    Route::get('dropdown/office/get', 'getDropdownOptionsReviewoffice');
    Route::get('dropdown/location/get', 'getDropdownOptionsReviewlocation');

});


//INSPECTION
Route::middleware(['auth:sanctum'])->group(function () {

    //INSPECTION REPORT API
    Route::post('inspection/create/{id}', [InspectionController::class, 'createInspection']);
    Route::post('inspection/update/{id}', [InspectionController::class, 'updateInspection']);
    Route::post('inspection/delete/{id}', [InspectionController::class, 'deleteInspection']);
    Route::post('inspection/submit/{id}', [InspectionController::class, 'submitInspection']);

    //ACTUAL WORK & MANPOWER DEPLOYMENT API
    Route::post('work-report/create/{id}', [ActualWorkController::class, 'createWorkreport']);
    Route::post('work-report/update/{id}', [ActualWorkController::class, 'updateWorkreport']);
    Route::post('work-report/submit/{id}', [ActualWorkController::class, 'submitWorkreport']);

    Route::post('manpower/deploy', [ActualWorkController::class, 'addManpowerDeploy']);
    Route::post('manpower/deploy/delete/{id}', [ActualWorkController::class, 'deletemanpowerdeployment']);

});

Route::get('work-reports/get/{id}', [ActualWorkController::class, 'getWorkreports']);
Route::get('inspections/get/{id}', [InspectionController::class, 'getInspections']);

Route::get('manpower/deploy/get', [ActualWorkController::class, 'getManpowerDeploy']);
Route::get('dropdown/manpower/deploy/get', [ActualWorkController::class, 'getDropdownOptionsActualwork']);

/*
|--------------------TEST API-----------------------\
*/
// Route::controller(BaseController::class)->group(function () {
//     Route::post('createCustomer', 'createCustomer');
//     Route::post('createCustomer', 'updateCustomer');
//     Route::get('getCustomers', 'getCustomers');
// });