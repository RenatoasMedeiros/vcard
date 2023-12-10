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

    public function loginPIN(Request $request) {
        $phone_number = $request->input('username');
        $pin = $request->input('pin');

        \Log::debug('\n\n' . $request);
    
        $user = DB::table('view_auth_users')->where('username', $phone_number)->where('pin', $pin)->first();
        
        \Log::debug("User found: " . json_encode($user));

        if ($user) {
            return response()->json('Login com PIN realizado com sucesso!', 200);
        }
    
        return response()->json('Authentication with PIN has failed! \n User: ', 401);
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
            'pin' => 'required'
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
            'pin' => $request->input('pin'),

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
        try {
            // Fetch user data from the Authentication model
            $user = $request->user();
            \Log::info('\User data: ' . json_encode($user));

            // Fetch additional data for the user from the VCard model
            $vcardData = VCard::find($user->username);
            \Log::info('\vcardData data: ' . json_encode($vcardData));

            // Merge the VCard data into the Authentication model
            $user->vcard = $vcardData;

            // Return the response using a resource
            return new AuthenticationResource($user);
        } catch (\Exception $e) {
            return response()->json('Error fetching user data', 500);
        }
    }

    

}