<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\VCardController;
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
            ],
            'payment_type' => 'required|in:VCARD,MBWAY,PAYPAL,IBAN,MB,VISA',
            'payment_reference' => 'required|string',
            'pair_vcard' => 'nullable|string|exists:vcards,phone_number',
            'category_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'custom_options' => 'nullable|json',
            'custom_data' => 'nullable|json',
            'type' => 'required|in:D,C',

        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Balance verification
        $vcard = VCard::where('phone_number', $request->input('vcard'))->first();
        $value = $request->input('value');
        if ($request->input('type') === 'D' && $value > $vcard->balance) {
            return response()->json(['error' => 'Insufficient balance.'], 400);
        }

        $transactionData = [
            'vcard' => $request->input('vcard'),
            'date' => $request->input('date'),
            'datetime' => $request->input('datetime'),
            'type' => $request->input('type'), // Updated to handle non-debit transactions
            'value' => $request->input('value'),
            'old_balance' => '0', // temporary
            'new_balance' => '0',
            'payment_type' => $request->input('payment_type'),
            'payment_reference' => $request->input('payment_reference'),
            'pair_transaction' => '1', //temporary
            'pair_vcard' => $request->input('pair_vcard'),
            'category_id' => $request->input('category_id'),
            'description' => $request->input('description'),
            'custom_options' => $request->input('custom_options'), //?? [], // Directly provide the array value,
            'custom_data' => $request->input('custom_data'),
        ];
        //\Log::info('\n sender balance before update: ' . json_encode($senderVCard));

        // Perform the balance deduction if it's a debit transaction
        \Log::info('\n Is a D trasaction!: ' . json_encode($request->input('type')));
        if ($request->input('type') === 'D') {
            // Update sender's VCard balance for debit
            $senderVCard = VCard::findOrFail($request->input('vcard'));
            $oldBalance = $senderVCard->balance;

            // Deduct the value from the sender's balance
            $senderVCard->update(['balance' => $senderVCard->balance - $value]);

            // Update the debit transaction with the old and new balance of the sender
            $debitTransaction = Transaction::create($transactionData);
            $debitTransaction->update([
                'pair_transaction' => $debitTransaction->id,
                'old_balance' => $oldBalance,
                'new_balance' => $senderVCard->balance,
            ]);

            // If there is a paired vCard, create the credit transaction (destination VCard)
            $hasPair_vcard = $request->has('pair_vcard');
            //Here
            //if ($hasPair_vcard == "null") $hasPair_vcard = 0;

            \Log::info('\n Has a pair_card: ' . json_encode($hasPair_vcard));

            // If there is a paired vCard, create the credit transaction (destination VCard)
            if ($hasPair_vcard) {
                \Log::info('\n Has a pair_card: ' . json_encode($hasPair_vcard));
                $creditTransactionData = $transactionData;
                //$creditTransactionData['vcard'] = $request->input('pair_vcard');
                //$creditTransactionData['payment_reference'] = $request->input('vcard');
                $creditTransactionData['vcard'] = $request->input('pair_vcard');
                $creditTransactionData['type'] = 'C';
                $receiverVCard = VCard::findOrFail($request->input('pair_vcard'));
                \Log::info('\n\n\n $receiverVCard: ' . json_encode($receiverVCard));
                $oldBalance = $receiverVCard->balance;
                \Log::info('\n Credit OLD BALANCE DATA: ' . json_encode($oldBalance));
                
                $creditTransaction = Transaction::create($creditTransactionData);
                // Deduct the value from the sender's balance
                $receiverVCard->update(['balance' => $receiverVCard->balance + $value]);

                $creditTransaction->update([
                    'pair_transaction' => $debitTransaction->id,
                    'old_balance' => $oldBalance,
                    'new_balance' => $receiverVCard->balance,
                ]);
                \Log::info('\n Credit NEW BALANCE DATA: ' . json_encode($receiverVCard->balance));
                \Log::info('\n Credit Transaciton (AFTER UPDATE): ' . json_encode($creditTransaction));
                
                // // Check if piggy_setting is "piggysaves: 1" and the value is not an integer
                // if ($request->input('custom_options') === 'piggysaves: 1' && (double)$request->input('value') != (int)$request->input('value')) {
                //     $valueToAddOnPiggyBank = (double)$request->input('value') - (int)$request->input('value');
                //     \Log::info('\n $valueToAddOnPiggyBank : ' . json_encode($valueToAddOnPiggyBank));
                //     \Log::info('\n $valueToAddOnPiggyBank : ' . $valueToAddOnPiggyBank);

                //     $vCardController = app(VCardController::class);

                //     $result = $vCardController->deposit($request->merge([
                //         'amount' => $valueToAddOnPiggyBank,
                //         'phone_number' => $request->input('pair_vcard'),
                //     ]));
                //     \Log::info('\n Result of the piggy bank transaction : ' . json_encode($result));
                //     \Log::info('\n Result of the piggy bank transaction : ' . $result);
                // }

            }
            return response()->json(['message' => 'Transactions created successfully (to a VCARD user)', 'debitTransaction' => $debitTransaction], 201);
        }
        // Code for non VCARD users
        $transaction = Transaction::create($transactionData);


        //return response()->json(['message' => 'Transactions created successfully', 'debitTransaction' => $debitTransaction, 'creditTransaction' => $creditTransaction], 201);
        return response()->json(['message' => 'Transactions created successfully', 'transaction' => $transaction], 201);
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