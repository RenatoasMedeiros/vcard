<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    private function passportAuthenticationData($username, $password)
    {
        return [
            'grant_type' => 'password',
            'client_id' => env('PASSPORT_CLIENT_ID'),
            'client_secret' => env('PASSPORT_CLIENT_SECRET'),
            'username' => $username,
            'password' => $password,
            'scope' => '',
        ];
    }
 
    public function login(Request $request)
    {
        try {
            $username = $request->input('username');
            $password = $request->input('password');
            
            \Log::info('Provided username: ' . $username);
            \Log::info('Provided password: ' . $password);

            $userId = DB::table('view_auth_users')
                ->where('username', $username)
                ->tap(function ($query) {
                    \Log::info('SQL Query: ' . $query->toSql());
                })
                ->value('id');
            \Log::info('User ID: ' . $userId);

                $request['username']=$userId;
                request()->request->add($this->passportAuthenticationData($userId, $password));
                // return $request;
            // return $request;
            $request = Request::create(env('PASSPORT_SERVER_URL') . '/oauth/token', 'POST' );
            $response = Route::dispatch($request);
            // return $response;
            $errorCode = $response->getStatusCode();
            $auth_server_response = json_decode((string) $response->content(), true);
            return response()->json($auth_server_response, $errorCode);
        } catch (\Exception $e) {
            return response()->json('Authentication has failed!', 401);
        }
    }

    public function logout(Request $request)
    {
        $accessToken = $request->user()->token();
        $token = $request->user()->tokens->find($accessToken);
        $token->revoke();
        $token->delete();
        return response(['msg' => 'Token revoked'], 200);
    }
}