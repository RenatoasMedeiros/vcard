<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\VCard;
use App\Models\User;
use App\Models\Category;
use App\Http\Resources\AuthenticationResource;


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

            $userId = DB::table('view_auth_users')
                ->where('username', $username)
                ->value('id');

            $request['username']=$userId;
            request()->request->add($this->passportAuthenticationData($userId, $password));

            $request = Request::create(env('PASSPORT_SERVER_URL') . '/oauth/token', 'POST' );
            $response = Route::dispatch($request);
            $errorCode = $response->getStatusCode();
            $auth_server_response = json_decode((string) $response->content(), true);
            return response()->json($auth_server_response, $errorCode);
        } catch (\Exception $e) {
            return response()->json('Authentication has failed!', 401);
        }
    }

    public function register(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|string|unique:vcards',
            'password' => 'required|string',
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'category_name' => 'required|string',
            'category_type' => 'required|in:D,C',
        ]);
    
        // Create a new VCard
        $vCard = VCard::create([
            'phone_number' => $request->input('phone_number'),
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => bcrypt($request->input('password')),
            'confirmation_code' => bcrypt($request->input('password')),
            'updated_at' => now(),
            'max_debit' => '5000',
            'blocked' => 0,

        ]);
    
        // Create a new category
        $category = Category::create([
            'name' => $request->input('category_name'),
            'type' => $request->input('category_type'),
            'vcard' => $request->input('phone_number'),
        ]);
    
        // Associate the category with the VCard
        $vCard->categories()->save($category);
    
        return response()->json(['message' => 'VCard registered successfully']);
    }

    public function logout(Request $request)
    {
        $accessToken = $request->user()->token();
        $token = $request->user()->tokens->find($accessToken);
        $token->revoke();
        $token->delete();
        return response(['msg' => 'Token revoked'], 200);
    }

    
    public function show_me(Request $request)
    {
        \Log::info('\nRequest data:', $request->all());

        return new AuthenticationResource($request->user());
    }
}