<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\AuthController;
use App\Http\Controllers\api\TransactionController;
use App\Http\Controllers\api\UserController;
use App\Http\Controllers\api\VCardController;

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

Route::post('login', [AuthController::class, 'loginvCard']);
Route::post('loginAdmin', [AuthController::class, 'loginAdmin']);
Route::post('register', [AuthController::class, 'register']);
Route::post('registerAdmin', [AuthController::class, 'registerAdmin']);


Route::middleware('auth:api')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('vcard/me', [AuthController::class, 'show_me']);
    Route::post('/loginPin', [AuthController::class, 'loginPIN']);

    //transactions routes
    Route::prefix('transactions')->group(function () {
        Route::post('/', [TransactionController::class, 'store']);
        Route::get('/', [TransactionController::class, 'index']);
        Route::get('/{id}', [TransactionController::class, 'show']);
        Route::put('/{id}', [TransactionController::class, 'update']);
        Route::delete('/{id}', [TransactionController::class, 'destroy']);
        Route::get('/vcard/{vcardPhoneNumber}', [TransactionController::class, 'getTransactionsForVCard']);
        Route::get('/vcard/{vcardPhoneNumber}/phone-number-transactions', [TransactionController::class, 'getPhoneNumberTransactionsForVCard']);
        Route::get('/vcard/{vcardPhoneNumber}/recent-transactions', [TransactionController::class, 'getRecentTransactions']);
    });

    //PiggyBank routes
    Route::prefix('piggyBank')->group(function () {
        Route::post('deposit', [VCardController::class, 'deposit']);
        Route::post('withdraw', [VCardController::class, 'withdraw']);
    });
    
    //Admin routes
    Route::prefix('admin')->group(function () {
        Route::put('profile', [UserController::class, 'profile']);
        Route::post('cTransacion', [TransactionController::class, 'storeCreditTransaction']);
    });
});
/* 
Route::prefix('transactions')->group(function () {
    Route::post('/', [TransactionController::class, 'store']);
    Route::get('/', [TransactionController::class, 'index']);
    Route::get('/{id}', [TransactionController::class, 'show']);
    Route::put('/{id}', [TransactionController::class, 'update']);
    Route::delete('/{id}', [TransactionController::class, 'destroy']);
    Route::get('/vcard/{vcardPhoneNumber}', [TransactionController::class, 'getTransactionsForVCard']);
    Route::get('/vcard/{vcardPhoneNumber}/phone-number-transactions', [TransactionController::class, 'getPhoneNumberTransactionsForVCard']);
    Route::get('/vcard/{vcardPhoneNumber}/recent-transactions', [TransactionController::class, 'getRecentTransactions']);
}); 
*/

// CÓDIGO EXEMPLO
/*
Route::get('users/{user}', [UserController::class, 'show'])
->middleware('can:view,user');
Route::put('users/{user}', [UserController::class, 'update'])
->middleware('can:update,user');
Route::patch('users/{user}/password', [UserController::class, 'update_password'])
->middleware('can:updatePassword,user')
 */