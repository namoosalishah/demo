<?php

use App\Http\Controllers\AuthenticationController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;

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

Route::post('/login', [AuthenticationController::class, 'login']);
Route::get('/verify', [UsersController::class, 'verify'])->name('verify');
Route::post('/register', [UsersController::class, 'register'])->name('register');
Route::post('/confirm/pin', [UsersController::class, 'confirmPin']);
Route::group(['middleware', 'auth:sanctum'], function () {
    Route::get('/profile', [UsersController::class, 'profile']);
    Route::post('/invite', [UsersController::class, 'invite']);
    Route::post('/profile', [UsersController::class, 'updateProfile']);
});
