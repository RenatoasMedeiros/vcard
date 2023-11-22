<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('login', [AuthController::class, 'login']);
Route::middleware('auth:api')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
});


// CÓDIGO EXEMPLO
/*
Route::get('users/{user}', [UserController::class, 'show'])
->middleware('can:view,user');
Route::put('users/{user}', [UserController::class, 'update'])
->middleware('can:update,user');
Route::patch('users/{user}/password', [UserController::class, 'update_password'])
->middleware('can:updatePassword,user')
 */