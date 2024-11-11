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
    Route::post('deleteUser/{id}', 'deleteUserAccount');
    Route::get('getdropdownUsertype', 'getDropdownOptionsUsertype');
    Route::get('getdropdownUseroffice', 'getDropdownOptionsUseroffice');

});

/*
|--------------------Division API-----------------------\
*/

Route::controller(DivisionController::class)->group(function () {
    Route::post('createDivision', 'createDivision');
    Route::post('updateDivision/{id}', 'updateDivision');
    Route::get('divisionList', 'getDivisions');
    Route::post('deleteDivision/{id}', 'deleteDivision');
    Route::get('dropdownCategories', 'getdropdownCategories');
    Route::get('dropdownSupervisor', 'dropdownSupervisor');


});



/*
|--------------------Profile Api-----------------------\
*/

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('profile', [AuthController::class, 'viewProfile']);
    Route::post('profile/edit', [AuthController::class, 'editProfile']);
    Route::post('editpassword', [AuthController::class, 'changePassword']);
});

/*
|--------------------LOGOUT API-----------------------\
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
});
/*
|--------------------Request API-----------------------\
*/

Route::controller(RequestController::class)->group(function () {
    //Route::post('createRequest', 'createRequest');
    //Route::get('requestList', 'getRequests');

    //REQUEST DROPDOWN API
    Route::get('dropdownLocation', 'getDropdownOptionsRequestslocation');
    Route::get('dropdownStatus', 'getDropdownOptionsRequeststatus');
    Route::get('dropdownYear', 'getDropdownOptionsRequestyear');
    Route::get('dropdownDivision', 'getDropdownOptionsRequestdivision');
    Route::get('dropdownCategory', 'getDropdownOptionsRequestcategory');
    Route::get('dropdownOffice', 'getDropdownOptionscreateRequestsoffice');
});

Route::middleware('auth:sanctum')->get('requestList', [RequestController::class, 'getRequests']);
Route::middleware('auth:sanctum')->post('createRequest', [RequestController::class, 'createRequest']);
Route::middleware('auth:sanctum')->post('updateReturn/{id}', [RequestController::class, 'updateReturn']);
//Request
Route::get('requests/pending/{id}', [ReviewController::class, 'getReviews'])->name('requests.pending');
Route::get('requests/inspection/{id}', [InspectionController::class, 'getInspections'])->name('requests.inspection');
Route::get('requests/ongoing/{id}', [ActualWorkController::class, 'getWorkreports'])->name('requests.ongoing');
Route::get('/requests/completed/{id}', [AccomplishmentReportController::class, 'saveAccomplishmentReport'])->name('requests.completed');

/*
|--------------------\Accomplishment Report API-----------------------\
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::post('saveAccomplishment/{id?}', [AccomplishmentReportController::class, 'saveAccomplishmentReport']);

    //Feedback
    Route::post('saveFeedback/{id?}', [FeedbackController::class, 'saveFeedback']);
});



// Route::middleware(['auth:sanctum'])->group(function () {
//     // Admin can access all CRUD routes
//     Route::middleware('UserTypeAuth:Administrator')->group(function () {
//         Route::post('admin-createrequest', [RequestController::class, 'createRequest']);
//         Route::post('admin-updaterequest/{id}', [RequestController::class, 'updateRequest']);
//         Route::get('admin-getrequest', [RequestController::class, 'getRequests']);
//     });
//     Route::middleware('UserTypeAuth:Controller,Supervisor,TeamLeader')->group(function () {
//         Route::get('user-getrequest', [RequestController::class, 'getRequests']);

//     });
//     Route::middleware('UserTypeAuth:DeanHead')->group(function () {
//         Route::post('dean-createrequest', [RequestController::class, 'createRequest']);
//         Route::get('dean-getrequest', [RequestController::class, 'getRequests']);
//     });
// });

/*
|--------------------Category API-----------------------\
*/

Route::controller(CategoryController::class)->group(function () {
    Route::post('createCategory', 'createCategory');
    Route::post('updateCategory/{id}', 'updateCategory');
    Route::get('categoryList', 'getCategory');
    Route::post('deleteCategory/{id}', 'deleteCategory');
    Route::get('getdropdownCategories', 'getDropdownOptionsCategory');
    Route::get('dropdownTeamleader', 'getdropdownteamleader');

});


/*
|--------------------Location API-----------------------\
*/

Route::controller(LocationController::class)->group(function () {
    Route::post('createLocation', 'createlocation');
    Route::post('updateLocation/{id}', 'updatelocation');
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
    Route::post('toggleUser/{id}', 'toggleUsertype');
    Route::post('deleteUsertype/{id}', 'deleteUserType');

});

/*
|--------------------REVIEW API-----------------------\
*/

Route::controller(ReviewController::class)->group(function () {
    Route::post('updatereview/{id}', 'updateReview');
    Route::get('reviewList/{id}', 'getReviews');
    Route::post('returnReview/{id}', 'returnReview');

    Route::get('getdropdownReviewOffice', 'getDropdownOptionsReviewoffice');
    Route::get('getdropdownReviewLocation', 'getDropdownOptionsReviewlocation');

});

// /*
// |--------------------Actual Work API-----------------------\
// */

// Route::controller(ActualWorkController::class)->group(function () {
//     Route::post('createWorkreport/{id}', 'createWorkreport');
//     Route::post('updateWorkreport/{id}', 'updateWorkreport');
//     Route::get('workreportList/{id}', 'getWorkreports');

//     //MANPOWER DEPLOYMENT API

//     Route::post('addmanpowerdeploy', 'addManpowerDeploy');
//     Route::get('manpowerdeployList', 'getManpowerDeploy');
//     Route::post('deleteManpowerdeploy/{id}', 'deletemanpowerdeployment');
//     Route::get('getdropdownManpowerDeploy', 'getDropdownOptionsActualwork');
// });

// /*
// |--------------------Inspection API-----------------------\
// */

Route::middleware(['auth:sanctum'])->group(function () {

    //INSPECTION REPORT API
    Route::post('createInspection/{id}', [InspectionController::class,'createInspection']);
    Route::post('updateInspection/{id}', [InspectionController::class, 'updateInspection']);
    Route::post('deleteInspection/{id}', [InspectionController::class, 'deleteInspection']);
    Route::post('submitInspection/{id}', [InspectionController::class, 'submitInspection']);

    //ACTUAL WORK & MANPOWER DEPLOYMENT API
    Route::post('createWorkreport/{id}', [ActualWorkController::class, 'createWorkreport']);
    Route::post('updateWorkreport/{id}', [ActualWorkController::class, 'updateWorkreport']);
    Route::post('submitWorkreport/{id}', [ActualWorkController::class, 'submitWorkreport']);

    Route::post('addmanpowerdeploy', [ActualWorkController::class, 'addManpowerDeploy']);
    Route::post('deleteManpowerdeploy/{id}', [ActualWorkController::class, 'deletemanpowerdeployment']);

});

Route::get('workreportList/{id}', [ActualWorkController::class, 'getWorkreports']);
Route::get('inspectionList/{id}', [InspectionController::class, 'getInspections']);

Route::get('manpowerdeployList', [ActualWorkController::class, 'getManpowerDeploy']);
Route::get('getdropdownManpowerDeploy', [ActualWorkController::class, 'getDropdownOptionsActualwork']);

/*
|--------------------TEST API-----------------------\
*/
// Route::controller(BaseController::class)->group(function () {
//     Route::post('createCustomer', 'createCustomer');
//     Route::post('createCustomer', 'updateCustomer');
//     Route::get('getCustomers', 'getCustomers');
// });