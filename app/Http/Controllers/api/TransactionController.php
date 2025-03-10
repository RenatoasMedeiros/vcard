<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\VCardController;
use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\Category;
use App\Models\VCard;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
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
                'max:100000', // Maximum value constraint
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

        //  // Additional validation for type, reference, and value
        // if ($validator->passes()) {
        //     switch ($request->input('payment_type')) {
        //         case 'MBWAY':
        //             $validator->sometimes('payment_reference', 'regex:/^9\d{8}$/', function ($input) {
        //                 return $input->payment_type === 'MBWAY';
        //             });
        //             break;
        //         case 'PAYPAL':
        //             $validator->sometimes('payment_reference', 'email', function ($input) {
        //                 return $input->payment_type === 'PAYPAL';
        //             });
        //             break;
        //         case 'IBAN':
        //             $validator->sometimes('payment_reference', 'regex:/^[A-Z]{2}\d{23}$/', function ($input) {
        //                 return $input->payment_type === 'IBAN';
        //             });
        //             break;
        //         case 'MB':
        //             $validator->sometimes('payment_reference', 'regex:/^\d{5}-\d{9}$/', function ($input) {
        //                 return $input->payment_type === 'MB';
        //             });
        //             break;
        //         case 'VISA':
        //             $validator->sometimes('payment_reference', 'regex:/^4\d{15}$/', function ($input) {
        //                 return $input->payment_type === 'VISA';
        //             });
        //             break;
        //     }
        // }

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        \Log::info('Request Payload: ' . json_encode($request->all()));

        // Additional simulation rules
        $reference = $request->input('payment_reference');
        $value = round($request->input('value'), 2);

        switch ($request->input('payment_type')) {
            case 'MBWAY':
                if (strpos($reference, '9') !== 0) {
                    \Log::info('Invalid MBWAY Reference Detected');
                    \Log::info('Reference: ' . $reference);
                    return response()->json(['error' => 'Invalid MBWAY reference'], 400);
                }
                
                // Check for maximum value
                if ($value > 50) {
                    return response()->json(['error' => 'MBWAY transaction amount exceeds the limit'], 400);
                }
                break;

            case 'PAYPAL':
                if (strpos($reference, 'xx') === 0) {
                    return response()->json(['error' => 'Invalid PAYPAL reference'], 400);
                }
                // Check for maximum value
                if ($value > 100) {
                    return response()->json(['error' => 'PAYPAL transaction amount exceeds the limit'], 400);
                }
                break;

            case 'IBAN':
                if (strpos($reference, 'XX') === 0) {
                    return response()->json(['error' => 'Invalid IBAN reference'], 400);
                }
                // Check for maximum value
                if ($value > 1000) {
                    return response()->json(['error' => 'IBAN transaction amount exceeds the limit'], 400);
                }
                break;

            case 'MB':
                if (strpos($reference, '9') === 0) {
                    return response()->json(['error' => 'Invalid MB reference'], 400);
                }
                // Check for maximum value
                if ($value > 500) {
                    return response()->json(['error' => 'MB transaction amount exceeds the limit'], 400);
                }
                break;

            case 'VISA':
                if (strpos($reference, '40') === 0) {
                    return response()->json(['error' => 'Invalid VISA reference'], 400);
                }
                // Check for maximum value
                if ($value > 200) {
                    return response()->json(['error' => 'VISA transaction amount exceeds the limit'], 400);
                }
                break;
        }

        // Balance verification
        $vcard = VCard::where('phone_number', $request->input('vcard'))->first();
        $value = $request->input('value');
        if ($request->input('type') === 'D' && $value > $vcard->balance) {
            return response()->json(['error' => 'Insufficient balance.'], 400);
        }

        // Verifica se o valor é maior que o max_debit da VCard
        if ($value > $vcard->max_debit) {
            return response()->json(['error' => 'Value cannot exceed the maximum debit limit for this VCard.'], 400);
        }

        // Check if payment_type is not VCARD
        if ($request->input('payment_type') !== 'VCARD') {
            \Log::info('Entrou aqui porque é diferente de VCARD');
            // Construct the request payload for external service
            $externalServicePayload = [
                'type' => $request->input('payment_type'),
                'reference' => $request->input('payment_reference'),
                'value' => $request->input('value'),
            ];
            \Log::info('$externalServicePayload ' . json_encode($externalServicePayload));

            // Make the HTTP request to the external service
            $response = Http::post('https://dad-202324-payments-api.vercel.app/api/debit', $externalServicePayload);
        
            \Log::info('$response->successful() ' . json_encode($response->successful()));
            // Check if the HTTP request was successful
            if (!$response->successful()) {
                // Handle the case where the external service rejected the transaction
                return response()->json(['error' => $response->json()], $response->status());
            }
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

            \Log::info('\n Has a pair_card? -> ' . json_encode($hasPair_vcard));

            // If there is a paired vCard, create the credit transaction (destination VCard)
            if ($hasPair_vcard && !empty($request->input('pair_vcard'))) {
                \Log::info('\n Has a pair_card: ' . json_encode($hasPair_vcard));
                $creditTransactionData = $transactionData;
                //$creditTransactionData['vcard'] = $request->input('pair_vcard');
                //$creditTransactionData['payment_reference'] = $request->input('vcard');
                $creditTransactionData['vcard'] = $request->input('pair_vcard');
                $creditTransactionData['payment_reference'] = $request->input('vcard');
                $creditTransactionData['pair_vcard'] = $request->input('vcard');
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
            }
            return response()->json(['message' => 'Transactions created successfully (to a VCARD user)', 'debitTransaction' => $debitTransaction], 201);
        }
        // Code for non VCARD users
        $transaction = Transaction::create($transactionData);

        //return response()->json(['message' => 'Transactions created successfully', 'debitTransaction' => $debitTransaction, 'creditTransaction' => $creditTransaction], 201);
        return response()->json(['message' => 'Transactions created successfully', 'transaction' => $transaction], 201);
    }

    // admin/cTransaction
    public function storeCreditTransaction(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'vcard' => 'required|string|exists:vcards,phone_number',
                //'vcard' => 'required|string',
                'date' => 'required|date',
                'datetime' => 'required|date',
                'value' => [
                    'required',
                    'numeric',
                    'min:0.01',
                ],
                'payment_type' => 'required|in:MBWAY,PAYPAL,IBAN,MB,VISA', // Exclude 'VCARD' from admin credit transactions
                'payment_reference' => 'required|string',
                'category_id' => 'nullable|exists:categories,id',
                'description' => 'nullable|string',
                'custom_options' => 'nullable|json',
                'custom_data' => 'nullable|json',
                'type' => 'required|in:C', // Credit transaction
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }

            // Ensure that the payment_type is not 'VCARD' for admin credit transactions
            if ($request->input('payment_type') === 'VCARD') {
                return response()->json(['error' => 'Invalid payment type for admin credit transactions'], 400);
            }
            
            // Initialize vCard and oldBalance
            $vcard = null;
            $oldBalance = 0;


            $vcard = VCard::where('phone_number', $request->input('vcard'))->first();
            \Log::info('$vcard: ' . json_encode($vcard));
            \Log::info('$request->input(vcard): ' . json_encode($request->input('vcard')));
            \Log::info('!$vcard ' . json_encode(!$vcard));

            // // If payment_type is MBWAY, find vCard based on phone number
            // if ($request->input('payment_type') === 'MBWAY') {
            // }

            // // Handle other payment types here (IBAN, PAYPAL, MB, VISA)
            // // If payment type is not MBWAY, use phone_number_receiver to find the vCard
            // if (in_array($request->input('payment_type'), ['IBAN', 'PAYPAL', 'MB', 'VISA'])) {
            //     $vcard = VCard::where('phone_number', $request->input('vcard'))->first();
            // }

            // Check if vCard was found
            if (!$vcard) {
                return response()->json(['error' => 'VCard not found for the provided payment reference or phone number'], 404);
            }

            // Construct the request payload for external service
            $externalServicePayload = [
                'type' => $request->input('payment_type'),
                'reference' => $request->input('payment_reference'),
                'value' => $request->input('value'),
            ];
            
            \Log::info('$externalServicePayload: ' . json_encode($externalServicePayload));

            // Make the HTTP request to the external service
            $response = Http::post('https://dad-202324-payments-api.vercel.app/api/debit', $externalServicePayload);

            // Check if the HTTP request was successful
            if (!$response->successful()) {
                // Handle the case where the external service rejected the transaction
                return response()->json(['error' => $response->json()], $response->status());
            }

            // Update the vCard's balance for credit
            $oldBalance = $vcard->balance;
            $vcard->update(['balance' => $vcard->balance + $request->input('value')]);

            // Create the credit transaction
            $creditTransactionData = [
                'vcard' => $request->input('vcard'),
                'date' => $request->input('date'),
                'datetime' => $request->input('datetime'),
                'type' => $request->input('type'),
                'value' => $request->input('value'),
                'old_balance' => $oldBalance,
                'new_balance' => $vcard->balance,
                'payment_type' => $request->input('payment_type'),
                'payment_reference' => $request->input('payment_reference'),
                'pair_transaction' => null,
                'pair_vcard' => null,
                'category_id' => $request->input('category_id'),
                'description' => $request->input('description'),
                'custom_options' => $request->input('custom_options'),
                'custom_data' => $request->input('custom_data'),
            ];

            $creditTransaction = Transaction::create($creditTransactionData);

            return response()->json(['message' => 'Credit transaction created successfully', 'creditTransaction' => $creditTransaction], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Credit transaction creation failed. Please try again.', 'exception' => $e->getMessage()], 500);
        }
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
            ->paginate(99);

        // Get the associated vCard name for each transaction
        $transactions = $transactions->map(function ($transaction) {
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
