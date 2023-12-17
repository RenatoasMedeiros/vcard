<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User; // Add the User model

class UserController extends Controller
{
    
    public function profile(Request $request)
{
    try {
        $admin = Auth::user();
        \Log::info('$admin: ' . json_encode($admin));

        if (!$admin || $admin->user_type !== 'A') {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Find the user in the 'users' table by email
        $user = User::where('email', $admin->email)->first();

        // Validate the request data
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'password' => 'nullable|string|min:3',
            'current_password' => 'nullable|required_with:password|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Update the 'users' table
        $user->name = $request->input('name');
        $user->email = $request->input('email');
        $user->save(); // Save changes to the 'users' table

        // Check and update the password if provided
        if ($request->filled('password')) {
            // Check the current password
            if (!Hash::check($request->input('current_password'), $user->password)) {
                return response()->json(['error' => 'Current password is incorrect'], 400);
            }
            // Update the password
            $user->password = bcrypt($request->input('password'));
            $user->save(); // Save changes to the 'users' table
        }

        return response()->json(['message' => 'Profile updated successfully'], 200);

    } catch (\Exception $e) {
        return response()->json(['error' => 'Profile update failed. Please try again.', 'exception' => $e->getMessage()], 500);
    }
}


}