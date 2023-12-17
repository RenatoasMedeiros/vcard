<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
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
 
    public function loginvCard(Request $request)
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

    public function loginAdmin(Request $request)
    {
        try {
            $username = $request->input('username');
            $password = $request->input('password');

            $userEmail = DB::table('view_auth_users')->where('email', $username)->value('email');
            \Log::info('$userEmail: ' . json_encode($userEmail));
            $request['username']=$userEmail;
            
            request()->request->add($this->passportAuthenticationData($userEmail, $password));
            
            $request = Request::create(env('PASSPORT_SERVER_URL') . '/oauth/token', 'POST' );
            \Log::info('$request: ' . json_encode($request));
            $response = Route::dispatch($request);
            \Log::info('$response: ' . json_encode($response));
            $errorCode = $response->getStatusCode();
            $auth_server_response = json_decode((string) $response->content(), true);
            return response()->json($auth_server_response, $errorCode);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Authentication with Admin account has failed!', 'exception' => $e->getMessage()], 401);
        }
    }

    public function loginPIN(Request $request) {
        $authenticatedUser = Auth::user();
            if($authenticatedUser == $request->user()){
            $phone_number = $request->input('username');
            $confirmation_code = $request->input('confirmation_code');

            \Log::debug('\n\n' . $request);
        
            $user = DB::table('view_auth_users')->where('username', $phone_number)->where('confirmation_code', $confirmation_code)->first();
            
            \Log::debug("User found: " . json_encode($user));

            if ($user) {
                return response()->json('Login com codigo de confirmação realizado com sucesso!', 200);
            }
        }
        return response()->json('Authentication with confirmation code has failed!', 401);
    }
    

    public function register(Request $request)
    {
        try{
            $request->validate([
                'phone_number' => 'required|string|regex:/^9[0-9]{8}$/|unique:vcards',
                'password' => 'required|string',
                'name' => 'required|string',
                'email' => 'required|email|unique:vcards',
                'category_name' => 'required|string',
                'category_type' => 'required|in:D,C',
                'confirmation_code' => 'required|string'
            ]);

            // Create a new VCard
            $vCard = VCard::create([
                'phone_number' => $request->input('phone_number'),
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'password' => bcrypt($request->input('password')),
                'confirmation_code' => bcrypt($request->input('confirmation_code')),
                'updated_at' => now(),
                'max_debit' => '5000',
                'blocked' => 0,
                'piggy_bank' => '0'
            ]);
        
            // Create a new category
            $category = Category::create([
                'name' => $request->input('category_name'),
                'type' => $request->input('category_type'),
                'vcard' => $request->input('phone_number'),
            ]);
        
            // Associate the category with the VCard
            $vCard->categories()->save($category);
        
            return response()->json(['message' => 'VCard registered successfully'], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Registration failed. Please try again.'], 500);
        }
    }

    public function registerAdmin(Request $request)
    {
        try {
            // Log the request headers for debugging
            \Log::info('Request Headers: ' . json_encode($request->headers->all()));

            // Get the authenticated user
            $authenticatedUser = Auth::user();
            \Log::info('$authenticatedUser: ' . json_encode($authenticatedUser));
            
            if ($authenticatedUser && $authenticatedUser->user_type === 'A') {
                $request->validate([
                    'email' => 'required|email|unique:users',
                    'name' => 'required|string',
                    'password' => 'required|string',
                ]);
            
                // Create a new VCard
                $user = User::create([
                    'name' => $request->input('name'),
                    'email' => $request->input('email'),
                    'password' => bcrypt($request->input('password')),
                    'remember_token' => Str::random(10),
                    'email_verified_at' => now(), //alterar depois!
                    'updated_at' => now(),
                    'created_at' => now(),

                ]);
                return response()->json(['message' => 'Admin registered successfully'], 201);
            }
        } catch (\Exception $e) {
           // Log the exception for debugging
            \Log::error('Exception: ' . $e->getMessage());

            // Return the exception message in the response for debugging
            return response()->json(['error' => 'Admin Registration failed. Please try again.', 'exception' => $e->getMessage()], 500);
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

    
    public function show_me(Request $request)
    {
        try {
            // Get the authenticated user
            $authenticatedUser = Auth::user();
            if($authenticatedUser == $request->user()){
                // Fetch user data from the Authentication model
                $user = $request->user();
                \Log::info('\User data: ' . json_encode($user));
    
                // Fetch additional data for the user from the VCard model
                $vcardData = VCard::find($user->username);
                \Log::info('\vcardData data: ' . json_encode($vcardData));
    
                // Fetch user_type from the related view_auth_users table
                $userType = DB::table('view_auth_users')->where('username', $user->username)->value('user_type');

                // Merge the VCard data and user_type into the Authentication model
                $user->vcard = $vcardData;
                $user->user_type = $userType;
    
                // Return the response using a resource
                return new AuthenticationResource($user);
            }
        } catch (\Exception $e) {
            return response()->json('Error fetching user data', 500);
        }
    }

    

}