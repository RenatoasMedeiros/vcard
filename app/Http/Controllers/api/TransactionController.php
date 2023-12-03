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
            'value' => [
                'required',
                'numeric',
                'min:0.01',
                function ($attribute, $value, $fail) use ($request) {
                    // Check if the value is greater than zero
                    if ($value <= 0) {
                        $fail("The $attribute must be greater than zero.");
                    }

                    // Check if it's a debit transaction, is that needed?
                    if ($request->input('type') === 'D') {
                        // Check if the value is less than or equal to the vCard balance
                        $vcard = VCard::where('phone_number', $request->input('vcard'))->first();
                        \Log::info('\n vcard data: ' . json_encode($vcard));
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
            'payment_type' => 'required|in:VCARD,MBWAY,PAYPAL,IBAN,MB,VISA',
            'payment_reference' => 'required|string',
            'pair_vcard' => 'nullable|string|exists:vcards,phone_number',
            'category_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'custom_options' => 'nullable|json',
            'custom_data' => 'nullable|json',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $transactionData = [
            'vcard' => $request->input('vcard'),
            'date' => $request->input('date'),
            'datetime' => $request->input('datetime'),
            'type' => 'D',
            'value' => $request->input('value'),
            'old_balance' => '0', // temporary
            'new_balance' => '0',
            'payment_type' => $request->input('payment_type'),
            'payment_reference' => $request->input('payment_reference'),
            'pair_transaction' => '1', //temporary
            'pair_vcard' => $request->input('pair_vcard'),
            'category_id' => $request->input('category_id'),
            'description' => $request->input('description'),
            'custom_options' => $request->input('custom_options'),
            'custom_data' => $request->input('custom_data'),
        ];

        // Create the debit transaction (source VCard)
        $debitTransaction = Transaction::create($transactionData);
        $debitTransaction->update([
            'pair_transaction' => $debitTransaction->id
        ]);

        // Find the sender's VCard
        $senderVCard = VCard::findOrFail($request->input('vcard'));

        // Calculate the old balance of the sender's VCard
        $oldBalance = $senderVCard->balance;

        // Update sender's VCard balance for debit
        \Log::info('\n sender balance before update: ' . json_encode($senderVCard));
        $senderVCard->update(['balance' => $senderVCard->balance - $request->input('value')]);
        \Log::info('\n sender balance after update: ' . json_encode($senderVCard));

        // Update the debit transaction with the old and new balance of the sender
        $debitTransaction->update([
            'old_balance' => $oldBalance,
            'new_balance' => $senderVCard->balance,
        ]);

        $hasPair_vcard = $request->has('pair_vcard');

        if ($hasPair_vcard == "null") $hasPair_vcard = 0;

        // If there is a paired vCard, create the credit transaction (destination VCard)
        if ($hasPair_vcard) {
            \Log::info('\n Has a pair_card: ' . json_encode($hasPair_vcard));
            \Log::info('\n Has a pair_card: ' . $hasPair_vcard);
            $creditTransactionData = $transactionData;
            $creditTransactionData['vcard'] = $request->input('pair_vcard');
            $creditTransactionData['type'] = 'C';

            $creditTransaction = Transaction::create($creditTransactionData);

            $creditTransaction->update([
                'pair_transaction' => $debitTransaction->id
            ]);
            // Find the receiver's VCard
            $receiverVCard = VCard::findOrFail($request->input('pair_vcard'));

            // Update receiver's VCard balance for credit
            \Log::info('\n receiverVCard balance before update: ' . json_encode($receiverVCard));
            $receiverVCard->update(['balance' => $receiverVCard->balance + $request->input('value')]);
            \Log::info('\n receiverVCard balance after update: ' . json_encode($receiverVCard));

            // Update the credit transaction with the new balance of the receiver
            $creditTransaction->update(['new_balance' => $receiverVCard->balance]);
        }

        return response()->json(['message' => 'Transactions created successfully', 'debitTransaction' => $debitTransaction]);
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

    public function getPhoneNumberTransactionsForVCard($vcardPhoneNumber)
    {
        // Find the vCard
        $vcard = VCard::where('phone_number', $vcardPhoneNumber)->first();

        if (!$vcard) {
            return response()->json(['error' => 'vCard not found'], 404);
        }

        // Retrieve paginated transactions associated with the vCard
        $phoneNumberTransactions = Transaction::where('vcard', $vcardPhoneNumber)
            ->whereRaw("LENGTH(payment_reference) = 9 AND payment_reference REGEXP '^[0-9]+$'")
            ->paginate(10);

        // Get the associated vCard name for each transaction
        $phoneNumberTransactions = $phoneNumberTransactions->map(function ($transaction) {
            // Find the vCard with the payment_reference
            $associatedVCard = VCard::where('phone_number', $transaction->payment_reference)->first();

            // If found, add the 'associatedVCard' field to the transaction
            if ($associatedVCard) {
                $transaction->associatedVCard = $associatedVCard->name;
            } else {
                $transaction->associatedVCard = null;
            }

            return $transaction;
        });

        return response()->json(['data' => $phoneNumberTransactions]);
    }

    public function getRecentTransactions($vcardPhoneNumber)
    {
        // Find the vCard
        $vcard = VCard::where('phone_number', $vcardPhoneNumber)->first();

        if (!$vcard) {
            return response()->json(['error' => 'vCard not found'], 404);
        }

        $sevenDaysAgo = now()->subDays(7);

        // Retrieve paginated transactions associated with the vCard
        $phoneNumberTransactions = Transaction::where('vcard', $vcardPhoneNumber)
            ->where('date', '>=', $sevenDaysAgo)
            ->whereRaw("LENGTH(payment_reference) = 9 AND payment_reference REGEXP '^[0-9]+$'")
            ->paginate(10);

        // Get the associated vCard name for each transaction
        $phoneNumberTransactions = $phoneNumberTransactions->map(function ($transaction) {
            // Find the vCard with the payment_reference
            $associatedVCard = VCard::where('phone_number', $transaction->payment_reference)->first();

            // If found, add the 'associatedVCard' field to the transaction
            if ($associatedVCard) {
                $transaction->associatedVCard = $associatedVCard->name;
            } else {
                $transaction->associatedVCard = null;
            }

            return $transaction;
        });

        \Log::info('\n receiverVCard balance after update: ' . json_encode($phoneNumberTransactions));

        return response()->json(['data' => $phoneNumberTransactions]);
    }
}
