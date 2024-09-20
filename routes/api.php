<?php


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\UserTypeController; 
use App\Http\Controllers\CollegeOfficeController; 
use App\Http\Controllers\UserAccountController; 
use App\Http\Controllers\DivisionNameController;
use App\Http\Controllers\ListofCategoryController;
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


Route::controller( UserAccountController::class)->group( function (){
    Route::post('useraccount', 'createUserAccount');        // For creating a user
    Route::post('useraccount/{id}', 'updateUserAccount');    // For updating a user
    Route::get('useraccounts',  'getUserAccounts'); 
    Route::delete('user-account/{id}',  'deleteUserAccount');
});

Route::controller( CollegeOfficeController::class)->group( function (){
    Route::post('collegeoffice', 'createCollegeOffice');        // For creating a user
    Route::post('collegeoffice/{id}', 'updateCollegeOffice');    // For updating a user
    Route::get('collegeoffices',  'getCollegeOffices'); 
    Route::delete('college-offices/{id}',  'deleteCollegeOffice');
});

Route::controller( UserTypeController::class)->group( function (){
    Route::post('usertype', 'createUserType');        // For creating a user
    Route::post('usertype/{id}', 'updateUserType');    // For updating a user
    Route::get('usertypes',  'getUserTypes');    
    Route::delete('user-types/{id}','deleteUserType');

});

Route::controller(DivisionNameController::class)->group(function (){
    Route::post('divisionname', 'createDivision');        // For creating a user
    Route::post('divisionname/{id}', 'updateDivision');    // For updating a user
    Route::get('divisionname',  'getDivisions');    
    Route::delete('division-name/{id}','deleteDivision');
});

Route::controller(ListofCategoryController::class)->group(function (){
    Route::post('categoryname', 'createCategory');        // For creating a user
    Route::post('categoryname/{id}', 'updateCategory');    // For updating a user
    Route::get('categoryname',  'getCategories');    
    Route::delete('category-name/{id}','deleteCategory');
});

Route::controller(BaseController::class)->group(function () {
Route::post('createCustomer', 'createCustomer');
Route::post('createCustomer', 'updateCustomer');
Route::get('getCustomers', 'getCustomers');
Route::post('user', 'createUser');        // For creating a user
Route::post('user/{id}', 'updateUser');    // For updating a user
Route::get('users',  'getUsers');          // For fetching users
//Route::post('session',  'insertSession');  // For inserting a session

});

Route::controller(AuthController::class)->group(function () {
Route::post('login',  'login');
Route::post('session',  'insertSession');
});
Route::middleware(['auth:sanctum', 'UserTypeAuth'])->group(function () {
    Route::get('/admin/dashboard', [AuthController::class, 'admin']);
    Route::get('/supervisor/dashboard', [AuthController::class, 'supervisor']);
    // Add more protected routes here
});


//example - having a middleware
// Route::controller(BaseController::class)->middleware(['auth:sanctum'])->group(function () {
//     Route::get('get', 'getAll')->middleware('teacher');
// });