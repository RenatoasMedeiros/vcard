<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\VCard; // Make sure to import your VCard model
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VCardController extends Controller
{
    // ...

    public function deposit(Request $request)
    {
        $amount = $request->input('amount');

        // Validate the request data
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $phone_number = $request->input('phone_number');
        
        // Get the authenticated user
        $authenticatedUser = Auth::user();

        // Check if the authenticated user matches the specified phone_number
        if ($authenticatedUser->username !== $phone_number) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Find the user by phone_number
        $user = VCard::find($phone_number);

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Check if the user has sufficient balance to deposit
        if ($user->balance < $amount) {
            return response()->json(['error' => 'Insufficient balance to deposit'], 400);
        }

        // Update the piggy_bank balance
        $user->piggy_bank += $amount;
        $user->balance -= $amount; // Deduct the deposited amount from the user's balance
        $user->save();

        return response()->json(['message' => 'Deposit to piggy_bank successful']);
    }


    public function withdraw(Request $request)
    {
        $amount = $request->input('amount');

        // Validate the request data
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $phone_number = $request->input('phone_number');

        // Get the authenticated user
        $authenticatedUser = Auth::user();
        \Log::info('$authenticatedUser: ' . json_encode($authenticatedUser));

        // Check if the authenticated user matches the specified phone_number
        if ($authenticatedUser->username !== $phone_number) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Find the user by phone_number
        $user = VCard::find($phone_number);

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Check if the piggy_bank has sufficient balance for withdrawal
        if ($user->piggy_bank < $amount) {
            return response()->json(['error' => 'Insufficient piggy_bank balance'], 400);
        }

        // Update the piggy_bank and balance
        $user->piggy_bank -= $amount;
        $user->balance += $amount;
        $user->save();

        return response()->json(['message' => 'Withdrawal from piggy_bank successful']);
    }

    // ...
}