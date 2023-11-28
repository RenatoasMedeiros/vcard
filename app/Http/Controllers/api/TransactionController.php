<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\Category;
use App\Models\VCard;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vcard' => 'required|string|exists:vcards,phone_number',
            'date' => 'required|date',
            'datetime' => 'required|date',
            'type' => 'required|in:C,D',
            'value' => [
                'required',
                'numeric',
                'min:0.01',
                function ($attribute, $value, $fail) use ($request) {
                    // Check if the value is greater than zero
                    if ($value <= 0) {
                        $fail("The $attribute must be greater than zero.");
                    }
            
                    // // Check if it's a debit transaction, is that needeed?
                    if ($request->input('type') === 'D') {
                        // Check if the value is less than or equal to the vCard balance
                        $vcard = VCard::where('phone_number', $request->input('vcard'))->first();
                        if ($value > $vcard->balance) {
                            $fail("The $attribute must be less than or equal to the vCard balance.");
                        }
            
                        // Check if the value is less than or equal to the maximum debit limit
                        if ($value > $vcard->max_debit) {
                            $fail("The $attribute must be less than or equal to the maximum debit limit.");
                        }
                    }
                },
            ],
            'old_balance' => 'required|numeric',
            'new_balance' => 'required|numeric',
            'payment_type' => 'required|in:VCARD,MBWAY,PAYPAL,IBAN,MB,VISA',
            'payment_reference' => 'required|string',
            'pair_transaction' => 'nullable|exists:transactions,id',
            'pair_vcard' => 'nullable|string|exists:vcards,phone_number',
            'category_id' => 'nullable|exists:categories,id', //rever esta parte
            'description' => 'nullable|string',
            'custom_options' => 'nullable|json',
            'custom_data' => 'nullable|json',
        ]);
    
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }
        
        $transaction = Transaction::create($request->all());

        if ($request->has('category_id')) {
            $category = Category::findOrFail($request->input('category_id'));
            $transaction->category()->associate($category);
            $transaction->save();
        }

        // Find the sender's VCard
        $senderVCard = VCard::findOrFail($request->input('vcard'));

        // Find the receiver's VCard (if applicable)
        $receiverVCard = $request->has('pair_card') ? VCard::findOrFail($request->input('pair_card')) : null;

        // Create the transaction
        $transaction = Transaction::create($request->all());

        // Update sender's VCard balance for debit
        if ($request->input('type') === 'D') {
            $senderVCard->update(['balance' => $senderVCard->balance - $request->input('value')]);
        }
        
        // Update receiver's VCard balance for credit
        if ($receiverVCard && $request->input('type') === 'C') {
            $receiverVCard->update(['balance' => $receiverVCard->balance + $request->input('value')]);
        }

        return response()->json(['message' => 'Transaction created successfully', 'data' => $transaction]);
    }

    public function index()
    {
        // Logic to retrieve all transactions
        $transactions = Transaction::all();

        return response()->json(['data' => $transactions]);
    }

    public function show($id)
    {
        // Logic to retrieve a specific transaction
        $transaction = Transaction::findOrFail($id);

        return response()->json(['data' => $transaction]);
    }

    public function update(Request $request, $id)
    {
        // Logic to update a transaction
        $transaction = Transaction::findOrFail($id);
        $transaction->update($request->all());

        return response()->json(['message' => 'Transaction updated successfully', 'data' => $transaction]);
    }

    public function destroy($id)
    {
        // Logic to delete a transaction
        $transaction = Transaction::findOrFail($id);
        $transaction->delete();

        return response()->json(['message' => 'Transaction deleted successfully']);
    }

    public function getTransactionsForVCard($vcardPhoneNumber)
    {
        // Find the vCard
        $vcard = VCard::where('phone_number', $vcardPhoneNumber)->first();

        if (!$vcard) {
            return response()->json(['error' => 'vCard not found'], 404);
        }

        // Retrieve paginated transactions associated with the vCard
        $transactions = Transaction::where('vcard', $vcardPhoneNumber)
            ->paginate(10);

        return response()->json(['data' => $transactions]);
    }


}