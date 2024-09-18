<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\UserTypeController; 
use App\Http\Controllers\CollegeOfficeController; 
use App\Http\Controllers\UserAccountController; 

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


Route::controller(BaseController::class)->group(function () {
Route::post('createCustomer', 'createCustomer');
Route::post('createCustomer', 'updateCustomer');
Route::get('getCustomers', 'getCustomers');
Route::post('user', 'createUser');        // For creating a user
Route::post('user/{id}', 'updateUser');    // For updating a user
Route::get('users',  'getUsers');          // For fetching users
Route::post('session',  'insertSession');  // For inserting a session

});


//example - having a middleware
// Route::controller(BaseController::class)->middleware(['auth:sanctum'])->group(function () {
//     Route::get('get', 'getAll')->middleware('teacher');
// });