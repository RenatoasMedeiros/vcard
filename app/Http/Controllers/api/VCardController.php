<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\VCard; // Make sure to import your VCard model
use App\Models\Authentication; // Make sure to import your VCard model
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\VCardResource;

class VCardController extends Controller
{

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

    public function indexVCards()
    {
        try {
            // Get the authenticated user
            $admin = Auth::user();
    
            // Check if the authenticated user is an administrator
            if (!$admin || $admin->user_type !== 'A') {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
    
            // Fetch specific fields for all vCards
            $vcards = VCard::select('phone_number', 'name', 'email', 'photo_url', 'balance', 'max_debit', 'blocked')->get();
            
    
            return response()->json(['vcards' => $vcards], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch vCards', 'exception' => $e->getMessage()], 500);
        }
    }
    

    public function adminUpdateVCard(Request $request, $vcardId)
    {
        try {
            // Get the authenticated user
            $admin = Auth::user();

            // Check if the authenticated user is an administrator
            if (!$admin || $admin->user_type !== 'A') {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Find the vCard to update
            $vcardToUpdate = VCard::find($vcardId);

            // Check if the vCard exists
            if (!$vcardToUpdate) {
                return response()->json(['error' => 'VCard not found'], 404);
            }

            // Validate the request data
            $validator = Validator::make($request->all(), [
                'max_debit' => 'nullable|numeric|min:0',
                'blocked' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }

            // Update the vCard data
            if ($request->filled('max_debit')) {
                $vcardToUpdate->max_debit = $request->input('max_debit');
            }

            if ($request->filled('blocked')) {
                $vcardToUpdate->blocked = $request->input('blocked');
            }

            $vcardToUpdate->save();

            return response()->json(['message' => 'VCard updated successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update VCard', 'exception' => $e->getMessage()], 500);
        }
    }

    public function adminUpdateVCard(Request $request, $vcardId)
    {
        try {
            // Get the authenticated user
            $admin = Auth::user();

            // Check if the authenticated user is an administrator
            if (!$admin || $admin->user_type !== 'A') {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Find the vCard to update
            $vcardToUpdate = VCard::find($vcardId);

            // Check if the vCard exists
            if (!$vcardToUpdate) {
                return response()->json(['error' => 'VCard not found'], 404);
            }

            // Validate the request data
            $validator = Validator::make($request->all(), [
                'max_debit' => 'nullable|numeric|min:0',
                'blocked' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }

            // Update the vCard data
            if ($request->filled('max_debit')) {
                $vcardToUpdate->max_debit = $request->input('max_debit');
            }

            if ($request->filled('blocked')) {
                $vcardToUpdate->blocked = $request->input('blocked');
            }

            $vcardToUpdate->save();

            return response()->json(['message' => 'VCard updated successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update VCard', 'exception' => $e->getMessage()], 500);
        }
    }

    public function adminFindVcard(Request $request, $vcardId)
    {
        try {
            // Get the authenticated user
            $admin = Auth::user();

            // Check if the authenticated user is an administrator
            if (!$admin || $admin->user_type !== 'A') {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Find the vCard 
            $vcardToFind = VCard::find($vcardId);
            
            // Check if the vCard exists
            if (!$vcardToFind) {
                return response()->json(['error' => 'VCard not found'], 404);
            }
            \Log::info('Vcard found: ' . json_encode($vcardToFind));

            // Return the response using a resource
            return new VCardResource($vcardToFind);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update VCard', 'exception' => $e->getMessage()], 500);
        }
    }

    public function deleteVCard($vcardId)
    {
        try {
            // Get the authenticated user
            $admin = Auth::user();

            // Check if the authenticated user is an administrator
            if (!$admin || $admin->user_type !== 'A') {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Find the vCard to delete
            $vcardToDelete = VCard::find($vcardId);

            // Check if the vCard exists
            if (!$vcardToDelete) {
                return response()->json(['error' => 'VCard not found'], 404);
            }

            // Check if the vCard has a balance of zero
            if ($vcardToDelete->balance == 0) {
                // Check if the vCard has associated transactions
                $transactionsCount = Transaction::where('vcard', $vcardId)->count();
                
                if ($transactionsCount > 0) {
                    // Soft delete the vCard and its associated transactions
                    $vcardToDelete->delete();
                    Transaction::where('vcard', $vcardId)->delete();
                } else {
                    // Delete the vCard since it has no associated transactions
                    $vcardToDelete->forceDelete();
                }

                return response()->json(['message' => 'VCard deleted successfully'], 200);
            } else {
                return response()->json(['error' => 'Cannot delete VCard with a balance greater than zero'], 400);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete VCard', 'exception' => $e->getMessage()], 500);
        }
    }   

    public function updateProfile(Request $request)
    {
        try {
            // Get the authenticated user
            $authenticatedUser = Auth::user();

            // Find the vCard to update
            $vcardToUpdate = VCard::find($authenticatedUser->username);

            // Check if the vCard exists
            if (!$vcardToUpdate) {
                return response()->json(['error' => 'VCard not found'], 404);
            }

            // Validate the request data
            $validator = Validator::make($request->all(), [
                'name' => 'nullable|string',
                'email' => 'nullable|email|unique:vcards,email,' . $vcardToUpdate->phone_number . ',phone_number',
                'photo_url' => 'nullable|url',
                'confirmation_code' => 'nullable|string',
                'password' => 'nullable|string',
                'current_password' => 'required|string', // Add this field for password confirmation
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }

            // Verify the current password
            if (!password_verify($request->input('current_password'), $vcardToUpdate->password)) {
                return response()->json(['error' => 'Incorrect current password'], 400);
            }

            //HERE I NEED TO save the password and the confirmation code like bcrypt('password'), bcrypt('confirmation_code') 
            // Hash the password if provided
            if ($request->has('password')) {
                $vcardToUpdate->password = bcrypt($request->input('password'));
            }

            // Hash the confirmation code if provided
            if ($request->has('confirmation_code')) {
                $vcardToUpdate->confirmation_code = bcrypt($request->input('confirmation_code'));
            }

            // Update the vCard data
            $vcardToUpdate->fill($request->only(['name', 'email', 'photo_url', 'confirmation_code', 'password']));
            $vcardToUpdate->save();

            return response()->json(['message' => 'Profile updated successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update profile', 'exception' => $e->getMessage()], 500);
        }
    }

    
}