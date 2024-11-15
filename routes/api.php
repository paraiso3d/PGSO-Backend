<?php

use App\Http\Controllers\AccomplishmentReportController;
use App\Http\Controllers\CategoryController;
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
    Route::post('session', 'insertSession');
    Route::post('logout', 'logout')->middleware('auth:sanctum');
});


/*
|--------------------Profile API-----------------------
*/

Route::prefix('users')->middleware(['auth:sanctum'])->group(function () {
    Route::get('profile/get', [AuthController::class, 'viewProfile']);
    Route::post('profile/edit', [AuthController::class, 'editProfile']);
    Route::post('password/change', [AuthController::class, 'changePassword']);
});

/*
|--------------------USERS API-----------------------
*/

Route::prefix('admin')->controller(UserController::class)->group(function () {
    Route::post('user/create', 'createUserAccount');
    Route::post('user/update/{id}', 'updateUserAccount');
    Route::get('users/get', 'getUserAccounts');
    Route::post('user/delete/{id}', 'deleteUserAccount');
});

/*
|--------------------Division API-----------------------
*/

Route::controller(DivisionController::class)->group(function () {
    Route::post('division/create', 'createDivision');
    Route::post('division/update/{id}', 'updateDivision');
    Route::get('divisions/get', 'getDivisions');
    Route::post('division/delete/{id}', 'deleteDivision');
});

/*
|--------------------Category API-----------------------
*/

Route::controller(CategoryController::class)->group(function () {
    Route::post('category/create', 'createCategory');
    Route::post('category/update/{id}', 'updateCategory');
    Route::get('categories/get', 'getCategory');
    Route::post('delete/category/{id}', 'deleteCategory');
});

/*
|--------------------Location API-----------------------
*/

Route::prefix('admin')->controller(LocationController::class)->group(function () {
    Route::post('location/create', 'createlocation');
    Route::post('location/update/{id}', 'updatelocation');
    Route::get('locations/get', 'getlocations');
    Route::post('location/delete/{id}', 'deletelocation');
});

/*
|--------------------ManPower API-----------------------
*/

Route::prefix('admin')->controller(ManpowerController::class)->group(function () {
    Route::post('manpower/create', 'createmanpower');
    Route::post('manpower/update/{id}', 'updatemanpower');
    Route::get('manpowers/get', 'getmanpowers');
    Route::post('manpower/delete/{id}', 'deletemanpower');
});

/*
|--------------------Offices API-----------------------
*/

Route::prefix('admin')->controller(OfficeController::class)->group(function () {
    Route::post('office/create', 'createOffice');
    Route::post('office/update/{id}', 'updateOffice');
    Route::get('offices/get', 'getOffices');
    Route::post('office/delete/{id}', 'deleteOffice');
});

/*
|--------------------USERTYPE API-----------------------
*/

Route::prefix('admin')->controller(UserTypeController::class)->group(function () {
    Route::post('user-type/create', 'createUserType');
    Route::post('user-type/update/{id}', 'updateUserType');
    Route::get('user-types/get', 'getUserTypes');
    Route::post('user-type/toggle/{id}', 'toggleUsertype');
    Route::post('user-type/delete/{id}', 'deleteUserType');
});

/*
|--------------------Request API-----------------------
*/

Route::controller(RequestController::class)->group(function () {
    Route::get('requests/get', 'getRequests')->middleware('auth:sanctum');
    Route::post('request/create', 'createRequest')->middleware('auth:sanctum');
    Route::post('request/return/{id}', 'updateReturn')->middleware('auth:sanctum');
    Route::post('request/assess/{id}', 'assessRequest')->middleware('auth:sanctum');
});

/*
|--------------------ACCOMPLISHMENT API-----------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::post('accomplishment/save/{id}', [AccomplishmentReportController::class, 'saveAccomplishmentReport']);
    Route::post('feedback/save/{id}', [FeedbackController::class, 'saveFeedback']);
    Route::post('feedback/submit/{id}', [FeedbackController::class, 'submitFeedback']);
});

/*
|--------------------REVIEW API-----------------------
*/

Route::controller(ReviewController::class)->group(function () {
    Route::post('review/update/{id}', 'updateReview');
    Route::post('review/edit/{id}', 'editReview');
    Route::get('review/get/{id}', 'getReviews');
    Route::post('review/return/{id}', 'returnReview');
});

/*
|--------------------INSPECTION & WORK REPORT API-----------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('inspection/create/{id}', [InspectionController::class, 'createInspection']);
    Route::post('inspection/update/{id}', [InspectionController::class, 'updateInspection']);
    Route::post('inspection/delete/{id}', [InspectionController::class, 'deleteInspection']);
    Route::post('inspection/submit/{id}', [InspectionController::class, 'submitInspection']);
    Route::post('work-report/create/{id}', [ActualWorkController::class, 'createWorkreport']);
    Route::post('work-report/update/{id}', [ActualWorkController::class, 'updateWorkreport']);
    Route::post('work-report/submit/{id}', [ActualWorkController::class, 'submitWorkreport']);
    Route::post('manpower/deploy', [ActualWorkController::class, 'addManpowerDeploy']);
    Route::post('manpower/deploy/delete/{id}', [ActualWorkController::class, 'deletemanpowerdeployment']);
});

Route::get('work-reports/get/{id}', [ActualWorkController::class, 'getWorkreports']);
Route::get('inspections/get/{id}', [InspectionController::class, 'getInspections']);
Route::get('manpower/deploy/get', [ActualWorkController::class, 'getManpowerDeploy']);

/*
|--------------------Dropdown API-----------------------
*/

Route::prefix('dropdown')->group(function () {
    Route::get('user/get', [UserController::class, 'getDropdownOptionsUsertype']);
    Route::get('office/get', [UserController::class, 'getDropdownOptionsUseroffice']);
    Route::get('category/get', [DivisionController::class, 'getdropdownCategories']);
    Route::get('supervisor/get', [DivisionController::class, 'dropdownSupervisor']);
    Route::get('categories/get', [CategoryController::class, 'getDropdownOptionsCategory']);
    Route::get('teamleaders/get', [CategoryController::class, 'getdropdownteamleader']);
    Route::get('location/get', [RequestController::class, 'getDropdownOptionsRequestslocation']);
    Route::get('status/get', [RequestController::class, 'getDropdownOptionsRequeststatus']);
    Route::get('year/get', [RequestController::class, 'getDropdownOptionsRequestyear']);
    Route::get('division/get', [RequestController::class, 'getDropdownOptionsRequestdivision']);
    Route::get('request-category/get', [RequestController::class, 'getDropdownOptionsRequestcategory']);
    Route::get('request-office/get', [RequestController::class, 'getDropdownOptionscreateRequestsoffice']);
    Route::get('review-office/get', [ReviewController::class, 'getDropdownOptionsReviewoffice']);
    Route::get('review-location/get', [ReviewController::class, 'getDropdownOptionsReviewlocation']);
    Route::get('manpower/get', [ActualWorkController::class, 'getDropdownOptionsActualwork']);
});
