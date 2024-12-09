<?php

use App\Http\Controllers\AccomplishmentReportController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DivisionController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\UserController;
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
|--------------------LOGIN/LOGOUT API-----------------------
*/

Route::controller(AuthController::class)->group(function () {
    Route::post('login', 'login');
    Route::post('test', 'test');
    Route::post('session', 'insertSession');
    Route::post('logout', 'logout')->middleware('auth:sanctum');
});


/*
|--------------------Profile API-----------------------
*/

Route::prefix('users')->middleware(['auth:sanctum'])->group(function () {
    Route::get('profile', [AuthController::class, 'viewProfile']);
    Route::post('profile/edit', [AuthController::class, 'editProfile']);
    Route::post('password/change', [AuthController::class, 'changePassword']);
});

/*
|--------------------USERS API-----------------------
*/

Route::prefix('admin')->controller(UserController::class)->group(function () {
    Route::post('user/create', 'createUserAccount');
    Route::post('user/update/{id}', 'updateUserAccount');
    Route::post('users', 'getUserAccounts');
    Route::post('user/delete/{id}', 'deleteUserAccount');
});

/*
|--------------------Division API-----------------------
*/

Route::controller(DivisionController::class)->group(function () {
    Route::post('division/create', 'createDivision');
    Route::post('division/update/{id}', 'updateDivision');
    Route::post('divisions', 'getDivisions');
    Route::post('division/delete/{id}', 'deleteDivision');
});

/*
|--------------------Category API-----------------------
*/

Route::controller(CategoryController::class)->group(function () {
    Route::post('category/create', 'createCategory');
    Route::post('category/update/{id}', 'updateCategory');
    Route::post('categories', 'getCategory');
    Route::post('delete/category/{id}', 'deleteCategory');
});

/*
|--------------------Location API-----------------------
*/

Route::prefix('admin')->controller(LocationController::class)->group(function () {
    Route::post('location/create', 'createlocation');
    Route::post('location/update/{id}', 'updatelocation');
    Route::post('locations', 'getlocations');
    Route::post('location/delete/{id}', 'deletelocation');
});

/*
|--------------------ManPower API-----------------------
*/

Route::prefix('admin')->controller(ManpowerController::class)->group(function () {
    Route::post('manpower/create', 'createmanpower');
    Route::post('manpower/update/{id}', 'updatemanpower');
    Route::post('manpowers', 'getmanpowers');
    Route::post('manpower/delete/{id}', 'deletemanpower');
});

/*
|--------------------Offices API-----------------------
*/

Route::prefix('admin')->controller(DepartmentController::class)->group(function () {
    Route::post('department/create', 'createOffice');
    Route::post('department/update/{id}', 'updateOffice');
    Route::post('department', 'getOffices');
    Route::post('department/delete/{id}', 'deleteOffice');
});

/*
|--------------------USERTYPE API-----------------------
*/

Route::prefix('admin')->controller(UserTypeController::class)->group(function () {
    Route::post('user-type/create', 'createUserType');
    Route::post('user-type/update/{id}', 'updateUserType');
    Route::post('user-types', 'getUserTypes');
    Route::post('user-type/toggle/{id}', 'toggleUsertype');
    Route::post('user-type/delete/{id}', 'deleteUserType');
});


Route::middleware('auth:sanctum')->group(function () {
    Route::post('accomplishment/save/{id}', [AccomplishmentReportController::class, 'saveAccomplishmentReport']);
    Route::post('feedback/save/{id}', [FeedbackController::class, 'saveFeedback']);
    Route::post('feedback/submit/{id}', [FeedbackController::class, 'submitFeedback']);
});

/*
|--------------------REQUESTS API-----------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // Review Routes
    Route::prefix('request')->group(function () {

        // Request Routes
        Route::post('create', [RequestController::class, 'createRequest']);
        Route::post('accept/{id}', [RequestController::class, 'acceptRequest']);
        Route::post('reject/{id}', [RequestController::class, 'rejectRequest']);
        Route::post('list', [RequestController::class, 'getRequests']);
        Route::post('list/{id}', [RequestController::class, 'getRequestById']);

        // Review Routes
        Route::post('review/update/{id}', [ReviewController::class, 'updateReview']);
        Route::post('review/edit/{id}', [ReviewController::class, 'editReview']);
        Route::post('review/return/{id}', [ReviewController::class, 'returnReview']);

        // Inspection Report Routes
        Route::post('inspection/create/{id}', [InspectionController::class, 'createInspection']);
        Route::post('inspection/update/{id}', [InspectionController::class, 'updateInspection']);
        Route::post('inspection/delete/{id}', [InspectionController::class, 'deleteInspection']);
        Route::post('inspection/submit/{id}', [InspectionController::class, 'submitInspection']);

        // Work Report Routes
        Route::post('work-report/create/{id}', [ActualWorkController::class, 'createWorkreport']);
        Route::post('work-report/update/{id}', [ActualWorkController::class, 'updateWorkreport']);
        Route::post('work-report/submit/{id}', [ActualWorkController::class, 'submitWorkreport']);
        Route::post('manpower/deploy', [ActualWorkController::class, 'addManpowerDeploy']);
        Route::post('manpower/deploy/delete/{id}', [ActualWorkController::class, 'deletemanpowerdeployment']);

        // Accomplishment Report Routes
        Route::post('accomplishment/save/{id}', [AccomplishmentReportController::class, 'saveAccomplishmentReport']);

        // Feedback Report Routes
        Route::post('feedback/save/{id}', [FeedbackController::class, 'saveFeedback']);
        Route::post('feedback/submit/{id}', [FeedbackController::class, 'submitFeedback']);
    });

});

Route::get('review/{id}', [ReviewController::class, 'getReviews']);
Route::get('work-reports/{id}', [ActualWorkController::class, 'getWorkreports']);
Route::get('inspections/{id}', [InspectionController::class, 'getInspections']);
Route::get('manpower/deploy', [ActualWorkController::class, 'getManpowerDeploy']);

/*
|--------------------Dropdown API-----------------------
*/

Route::prefix('dropdown')->group(function () {
    Route::get('users', [UserController::class, 'getDropdownOptionsUsertype']);
    Route::get('offices', [UserController::class, 'getDropdownOptionsUseroffice']);
    Route::get('category', [DivisionController::class, 'getdropdownCategories']);
    Route::get('supervisor', [DivisionController::class, 'dropdownSupervisor']);
    Route::get('categories', [CategoryController::class, 'getDropdownOptionsCategory']);
    Route::get('teamleaders', [CategoryController::class, 'getdropdownteamleader']);
    Route::get('locations', [RequestController::class, 'getDropdownOptionsRequestslocation']);
    Route::get('status', [RequestController::class, 'getDropdownOptionsRequeststatus']);
    Route::get('years', [RequestController::class, 'getDropdownOptionsRequestyear']);
    Route::get('divisions', [RequestController::class, 'getDropdownOptionsRequestdivision']);
    Route::get('request-category', [RequestController::class, 'getDropdownOptionsRequestcategory']);
    Route::get('request-office', [RequestController::class, 'getDropdownOptionscreateRequestsoffice']);
    Route::get('review-office', [ReviewController::class, 'getDropdownOptionsReviewoffice']);
    Route::get('review-location', [ReviewController::class, 'getDropdownOptionsReviewlocation']);
    Route::get('manpower', [ActualWorkController::class, 'getDropdownOptionsActualwork']);
});

