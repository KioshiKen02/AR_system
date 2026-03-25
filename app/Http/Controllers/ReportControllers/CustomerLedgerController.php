<?php

namespace App\Http\Controllers\ReportControllers;

use App\Events\NewCreated;
use App\Http\Controllers\Controller;
use App\Models\ReportModels\CustomerLedger;
use App\Models\TransactionModels\Adjustment;
use App\Models\TransactionModels\BeginningBalance;
use App\Models\TransactionModels\Payment;
use App\Models\TransactionModels\PaymentDetails;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CustomerLedgerController extends Controller
{
    public function index(Request $request)
    {
        $query = CustomerLedger::query();

        // Customer code filter
        if ($request->customer_code) {
            $query->where('customer_code', 'like', '%' . $request->customer_code . '%');
        }

        // Date sorting
        if ($request->date_start) {
            $query->whereDate('date', '>=', $request->date_start);
        }

        if ($request->date_end) {
            $query->whereDate('date', '<=', $request->date_end);
        }

        if ($request->customer_code) {
            // Fetch all adjustments for this customer to ensure we have the latest adjusted amounts
            // Grouped by invoice_no and apply_to
            $adjustmentsGrouped = Adjustment::where('customer_code', $request->customer_code)
                ->selectRaw("invoice_no, apply_to, SUM(CASE WHEN type='Positive' THEN amount WHEN type='Negative' THEN -amount ELSE 0 END) as total_adjustment")
                ->groupBy('invoice_no', 'apply_to')
                ->get()
                ->groupBy(['invoice_no', 'apply_to']);
            $adjustmentsPosNeg = Adjustment::where('customer_code', $request->customer_code)
                ->selectRaw("
                    invoice_no,
                    apply_to,
                    SUM(CASE WHEN type='Positive' THEN amount ELSE 0 END) as pos_sum,
                    SUM(CASE WHEN type='Negative' THEN amount ELSE 0 END) as neg_sum
                ")
                ->groupBy('invoice_no', 'apply_to')
                ->get()
                ->groupBy(['invoice_no', 'apply_to']);
            $paidAmounts = PaymentDetails::where('customer_code', $request->customer_code)
                ->where('status', 'Paid')
                ->selectRaw('document_no, type, SUM(amount_paid) as total_paid')
                ->groupBy('document_no', 'type')
                ->get()
                ->groupBy(['document_no', 'type']);

            // $partialPaymentForwarded = (float) CustomerLedger::where('customer_code', $request->customer_code)
            //     ->whereDate('date', '<', $request->date_start)
            //     ->sum('running_balance');

            // Recalculate true forwarded balance from scratch
            $prevRecords = CustomerLedger::where('customer_code', $request->customer_code)
                ->whereDate('date', '<', $request->date_start)
                ->get();
            
            $partialPaymentForwarded = $prevRecords->sum(function ($record) use ($adjustmentsPosNeg) {
                 $applyTo = $record->type;
                 if ($record->type === 'BG') {
                     $applyTo = 'Beginning Balance';
                 } elseif ($record->type === 'Charge Invoice') {
                     $applyTo = 'Other Income';
                 }

                 $pos = $adjustmentsPosNeg
                        ->get($record->invoice_number, collect())
                        ->get($applyTo, collect())
                        ->first();
                 $posSum = $pos->pos_sum ?? 0;
                 $negSum = $pos->neg_sum ?? 0;

                 if ($record->type === 'BG' || $record->type === 'Beginning Balance') {
                     $beginRow = BeginningBalance::where('beginningbalance_no', $record->invoice_number)->first();
                     $baseAmount = $beginRow?->balance_amount ?? 0;
                 } else {
                     $baseAmount = ($record->amount ?? 0);
                 }

                 return (($baseAmount ?? 0) + $posSum - $negSum)
                    - ($record->shrinkage ?? 0)
                    + ($record->overage ?? 0)
                    - ($record->return ?? 0)
                    - ($record->amount_paid ?? 0);
            });

            $totalFloatingAmount = (float) PaymentDetails::where('customer_code', $request->customer_code)
                ->whereDate('document_date', '<', $request->date_start)
                ->where('status', 'Floating')
                ->sum('amount_paid');
        } else {
             $adjustmentsGrouped = collect();
             $adjustmentsPosNeg = collect();
             $paidAmounts = collect();
        }
        $paymentForwarded = 0;
        $shouldPersist = filter_var($request->generateTableData, FILTER_VALIDATE_BOOLEAN);

        if ($request->customer_code && $shouldPersist) {
            $bgLedgers = CustomerLedger::where('customer_code', $request->customer_code)
                ->whereIn('type', ['BG', 'Beginning Balance'])
                ->get(['id', 'invoice_number', 'amount']);

            if ($bgLedgers->isNotEmpty()) {
                $beginningBalances = BeginningBalance::whereIn('beginningbalance_no', $bgLedgers->pluck('invoice_number'))
                    ->get(['beginningbalance_no', 'balance_amount'])
                    ->keyBy('beginningbalance_no');

                foreach ($bgLedgers as $bgLedger) {
                    $beginRow = $beginningBalances->get($bgLedger->invoice_number);
                    if (! $beginRow) {
                        continue;
                    }
                    if ($bgLedger->amount != $beginRow->balance_amount) {
                        CustomerLedger::where('id', $bgLedger->id)->update([
                            'amount' => $beginRow->balance_amount,
                        ]);
                    }
                }
            }
        }

        if ($request->type_filters && $request->type_filters === 'Without Floating Deducted') {

            $paymentForwarded = $partialPaymentForwarded;
            // Get all records (not paginated) to calculate running balance
            $allRecords = $query->clone()
                ->orderBy('customer_code')
                // ->orderBy('type')
                ->orderBy('date', 'asc')
                ->orderBy('created_at', 'asc')
                ->get();

            // Calculate running balance per customer and type
            $runningBalances = [];
            $processedRecords = $allRecords->map(function ($record) use (&$runningBalances, $paymentForwarded, $adjustmentsPosNeg, $paidAmounts, $shouldPersist) {
                $key = $record->customer_code;

                // Initialize running balance for this customer+type if not exists
                if (! isset($runningBalances[$key])) {
                    $runningBalances[$key] = $paymentForwarded;
                }

                // Helper to safely parse float from string with commas
                $val = function($v) {
                    return (float) str_replace(',', '', (string)($v ?? 0));
                };

                // Calculate debit and credit based on transaction type
                $amount = $val($record->amount);
                $shrinkage = $val($record->shrinkage);
                $overage = $val($record->overage);
                $return = $val($record->return);
                
                $applyTo = $record->type;
                if ($record->type === 'BG') {
                    $applyTo = 'Beginning Balance';
                } elseif ($record->type === 'Charge Invoice') {
                    $applyTo = 'Other Income';
                }

                if ($record->type === 'BG' || $record->type === 'Beginning Balance') {
                    $beginRow = BeginningBalance::where('beginningbalance_no', $record->invoice_number)->first();
                    $amount = $val($beginRow?->balance_amount);
                    if ($shouldPersist && $beginRow && $record->amount != $beginRow->balance_amount) {
                        CustomerLedger::where('id', $record->id)->update(['amount' => $beginRow->balance_amount]);
                        $record->amount = $beginRow->balance_amount;
                    }
                }

                $posNeg = $adjustmentsPosNeg
                        ->get($record->invoice_number, collect())
                        ->get($applyTo, collect())
                        ->first()
                        ?? null;
                $posSum = $posNeg->pos_sum ?? 0;
                $negSum = $posNeg->neg_sum ?? 0;
                $adjustedAmount = $posSum - $negSum;

                $grossDebit = $amount - $shrinkage + $overage - $return;

                $netDebit = $grossDebit + $adjustedAmount;

                $paidRow = $paidAmounts
                    ->get($record->invoice_number, collect())
                    ->get($record->type, collect())
                    ->first();
                $creditBase = $paidRow?->total_paid ?? $val($record->amount_paid);
                $credit = $creditBase;
                $record->amount_paid = $creditBase;

                $record->document_balance = $record->running_balance; // for display document real balance
                // Update running balance
                $runningBalances[$key] += $netDebit - $credit;
                $record->running_balance = $runningBalances[$key];
                $record->persist_running_balance = $netDebit - $creditBase;
                $record->persist_amount_paid = $creditBase;
                
                $record->debit_amount = $netDebit;  
                $record->credit_amount = $credit; // Optional: store for display
                $record->adjusted_amount = $netDebit;
                $record->positive_adjustment_amount = $posSum;
                $record->negative_adjustment_amount = $negSum;

                return $record;
            });
        } elseif ($request->type_filters && $request->type_filters === 'With Floating Deducted') {
            $paymentForwarded = $partialPaymentForwarded - $totalFloatingAmount;

            // Get all records (not paginated) to calculate running balance
            $allRecords = $query->clone()
                ->orderBy('customer_code')
                // ->orderBy('type')
                ->orderBy('date', 'asc')
                ->orderBy('created_at', 'asc')
                ->get();

            $floatingAmounts = PaymentDetails::where('customer_code', $request->customer_code)
                ->where('status', 'Floating')
                ->selectRaw('document_no, type, SUM(amount_paid) as total_floating')
                ->groupBy('document_no', 'type')
                ->get()
                ->groupBy(['document_no', 'type']);

            // Calculate running balance per customer and type
            $runningBalances = [];
            $processedRecords = $allRecords->map(function ($record) use (&$runningBalances, $floatingAmounts, $paymentForwarded, $adjustmentsPosNeg, $paidAmounts, $shouldPersist) {
                $key = $record->customer_code;

                // Initialize running balance for this customer+type if not exists
                if (! isset($runningBalances[$key])) {
                    $runningBalances[$key] = $paymentForwarded;
                }

                $floatingAmount = $floatingAmounts
                    ->get($record->invoice_number, collect())
                    ->get($record->type, collect())
                    ->first()
                    ->total_floating ?? 0;

                // Helper to safely parse float from string with commas
                $val = function($v) {
                    return (float) str_replace(',', '', (string)($v ?? 0));
                };

                // Calculate debit and credit based on transaction type
                $amount = $val($record->amount);
                $shrinkage = $val($record->shrinkage);
                $overage = $val($record->overage);
                $return = $val($record->return);
                
                $applyTo = $record->type;
                if ($record->type === 'BG') {
                    $applyTo = 'Beginning Balance';
                } elseif ($record->type === 'Charge Invoice') {
                    $applyTo = 'Other Income';
                }
                
                if ($record->type === 'BG' || $record->type === 'Beginning Balance') {
                    $beginRow = BeginningBalance::where('beginningbalance_no', $record->invoice_number)->first();
                    $amount = $val($beginRow?->balance_amount);
                    if ($shouldPersist && $beginRow && $record->amount != $beginRow->balance_amount) {
                        CustomerLedger::where('id', $record->id)->update(['amount' => $beginRow->balance_amount]);
                        $record->amount = $beginRow->balance_amount;
                    }
                }

                $posNeg = $adjustmentsPosNeg
                        ->get($record->invoice_number, collect())
                        ->get($applyTo, collect())
                        ->first()
                        ?? null;
                $posSum = $posNeg->pos_sum ?? 0;
                $negSum = $posNeg->neg_sum ?? 0;
                $adjustedAmount = $posSum - $negSum;

                $grossDebit = $amount - $shrinkage + $overage - $return;

                $netDebit = $grossDebit + $adjustedAmount;

                $paidRow = $paidAmounts
                    ->get($record->invoice_number, collect())
                    ->get($record->type, collect())
                    ->first();
                $creditBase = $paidRow?->total_paid ?? $val($record->amount_paid);
                $credit = $creditBase + $floatingAmount;
                $record->amount_paid = $credit;

                // for display document real balance
                $record->document_balance = $record->running_balance - $floatingAmount;
                
                // Update running balance
                $runningBalances[$key] += $netDebit - $credit;
                $record->running_balance = $runningBalances[$key];
                $record->persist_running_balance = $netDebit - $creditBase;
                $record->persist_amount_paid = $creditBase;
                
                $record->debit_amount = $netDebit;
                $record->amount_paid = $credit;
                $record->adjusted_amount = $netDebit;
                $record->positive_adjustment_amount = $posSum;
                $record->negative_adjustment_amount = $negSum;

                return $record;
            });
        } else {
            // Get all records (not paginated) to calculate running balance
            $allRecords = $query->clone()
                ->orderBy('customer_code')
                // ->orderBy('type')
                ->orderBy('date', 'asc')
                ->orderBy('created_at', 'asc')
                ->get();

            // Calculate running balance per customer and type
            $runningBalances = [];
            $processedRecords = $allRecords->map(function ($record) use (&$runningBalances, $adjustmentsPosNeg, $paidAmounts, $shouldPersist) {
                $key = $record->customer_code;

                // Initialize running balance for this customer+type if not exists
                if (! isset($runningBalances[$key])) {
                    $runningBalances[$key] = 0;
                }

                // Helper to safely parse float from string with commas
                $val = function($v) {
                    return (float) str_replace(',', '', (string)($v ?? 0));
                };

                // Calculate debit and credit based on transaction type
                $amount = $val($record->amount);
                $shrinkage = $val($record->shrinkage);
                $overage = $val($record->overage);
                $return = $val($record->return);
                
                $applyTo = $record->type;
                if ($record->type === 'BG') {
                    $applyTo = 'Beginning Balance';
                } elseif ($record->type === 'Charge Invoice') {
                    $applyTo = 'Other Income';
                }

                if ($record->type === 'BG' || $record->type === 'Beginning Balance') {
                    $beginRow = BeginningBalance::where('beginningbalance_no', $record->invoice_number)->first();
                    $amount = $val($beginRow?->balance_amount);
                    if ($shouldPersist && $beginRow && $record->amount != $beginRow->balance_amount) {
                        CustomerLedger::where('id', $record->id)->update(['amount' => $beginRow->balance_amount]);
                        $record->amount = $beginRow->balance_amount;
                    }
                }

                $posNeg = $adjustmentsPosNeg
                        ->get($record->invoice_number, collect())
                        ->get($applyTo, collect())
                        ->first()
                        ?? null;
                $posSum = $posNeg->pos_sum ?? 0;
                $negSum = $posNeg->neg_sum ?? 0;
                $adjustedAmount = $posSum - $negSum;

                $grossDebit = $amount - $shrinkage + $overage - $return;

                $netDebit = $grossDebit + $adjustedAmount;

                $paidRow = $paidAmounts
                    ->get($record->invoice_number, collect())
                    ->get($record->type, collect())
                    ->first();
                $creditBase = $paidRow?->total_paid ?? $val($record->amount_paid);
                $credit = $creditBase;
                $record->amount_paid = $creditBase;

                $record->document_balance = $record->running_balance; // for display document real balance
                // Update running balance
                $runningBalances[$key] += $netDebit - $credit;
                $record->running_balance = $runningBalances[$key];
                $record->persist_running_balance = $netDebit - $creditBase;
                $record->persist_amount_paid = $creditBase;
                $record->debit_amount = $netDebit;
                $record->credit_amount = $credit;
                $record->adjusted_amount = $netDebit;
                $record->positive_adjustment_amount = $posSum;
                $record->negative_adjustment_amount = $negSum;

                return $record;
            });
        }

        if (isset($processedRecords) && $shouldPersist) {
            foreach ($processedRecords as $r) {
                $adj = $r->adjusted_amount ?? $r->debit_amount ?? null;
                $rb = $r->persist_running_balance ?? $r->running_balance;
                $pos = $r->positive_adjustment_amount ?? null;
                $neg = $r->negative_adjustment_amount ?? null;
                $amt = $r->amount ?? null;
                $paid = $r->persist_amount_paid ?? null;
                CustomerLedger::where('id', $r->id)->update([
                    'amount' => $amt,
                    'adjusted_amount' => $adj,
                    'positive_adjustment_amount' => $pos,
                    'negative_adjustment_amount' => $neg,
                    'amount_paid' => $paid,
                    'running_balance' => $rb,
                ]);
            }
        }

        // Now apply pagination to the processed records
        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 10;
        $paginatedItems = $processedRecords->slice(($page - 1) * $perPage, $perPage)->values();
        $paginatedRecords = new LengthAwarePaginator(
            $paginatedItems,
            $processedRecords->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );

        return Inertia::render('CustomerLedger', [
            'customerledgers' => $paginatedRecords,
            // 'searchTerm' => $request->search,
            'paymentForwarded' => $paymentForwarded,
            'filters' => [
                'customer_code' => $request->customer_code,
                'date_start' => $request->date_start,
                'date_end' => $request->date_end,
                'type_filters' => $request->type_filters,
            ],
            'generateTableData' => filter_var($request->generateTableData, FILTER_VALIDATE_BOOLEAN),
            'broadcastChannel' => 'customerledgers',
        ]);
    }

    public function getDetailedLedger(Request $request)
    {
        $validated = $request->validate([
            'invoice_no' => 'required|string',
            'type' => 'required|string',
        ]);

        $invoiceNo = $validated['invoice_no'];
        $type = $validated['type'];
        
        $transactions = [];
        $runningBalance = 0;
        $posAdjSum = 0;
        $negAdjSum = 0;
        $paymentsSum = 0;
        $beginningAmountDisplay = 0;
        $adjustedAmountVal = 0;
        $finalRunningBalance = 0;

        // 1. Get Beginning Balance / Initial Transaction
        if ($type === 'Beginning Balance' || $type === 'BG') {
            $beginningBalance = BeginningBalance::where('beginningbalance_no', $invoiceNo)->first();
            if ($beginningBalance) {
                $amount = $beginningBalance->balance_amount;
                $runningBalance = $amount;
                $transactions[] = [
                    'date' => $beginningBalance->receipt_date,
                    'transaction_no' => $beginningBalance->beginningbalance_no,
                    'description' => 'Beginning Balance',
                    'type' => 'Initial',
                    'debit' => $amount ,
                    'credit' => 0,
                    'balance' => $runningBalance,
                ];
            }
        } else {
             // Logic for Sales Invoice or other types can be added here if needed in future
             // For now focusing on Beginning Balance as requested
             $ledger = CustomerLedger::where('invoice_number', $invoiceNo)->where('type', $type)->first();
             if($ledger) {
                 // Calculate initial debit amount similar to index method
                 $amount = ($ledger->amount ?? 0) 
                         - ($ledger->shrinkage ?? 0) 
                         + ($ledger->overage ?? 0) 
                         - ($ledger->return ?? 0);
                 
                 $runningBalance = $amount;
                 $transactions[] = [
                    'date' => $ledger->date,
                    'transaction_no' => $ledger->invoice_number,
                    'description' => $ledger->type,
                    'type' => 'Initial',
                    'debit' => $amount,
                    'credit' => 0,
                    'balance' => $runningBalance,
                 ];
             }
        }

        // 2. Get Adjustments
        $applyTo = $type;
        if ($type === 'BG') {
            $applyTo = 'Beginning Balance';
        } elseif ($type === 'Charge Invoice') {
            $applyTo = 'Other Income';
        }

            $adjustments = Adjustment::where('invoice_no', $invoiceNo)
            ->where('apply_to', $applyTo)
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($adjustments as $adj) {
            $adjAmount = $adj->amount;
            $debit = 0;
            $credit = 0;
            $description = $adj->type . ' Adjustment';

            if ($adj->type === 'Positive') {
                $debit = $adjAmount;
                $runningBalance += $adjAmount;
                $posAdjSum += $adjAmount;
            } elseif ($adj->type === 'Negative') {
                $credit = $adjAmount; // Negative adjustment reduces the balance, effectively like a credit
                $runningBalance -= $adjAmount;
                $negAdjSum += $adjAmount;
            }

            $transactions[] = [
                'date' => $adj->created_at->format('Y-m-d'), // Or receipt_date if preferred
                'transaction_no' => $adj->adjustment_no,
                'description' => $description,
                'type' => 'Adjustment',
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $runningBalance,
            ];
        }

        // 3. Get Payments
        $paymentsQuery = PaymentDetails::where('document_no', $invoiceNo)
            ->orderBy('payment_date', 'asc');

        // Enforce strict type matching while allowing common synonyms for Beginning Balance
        if (in_array($type, ['BG', 'Beginning Balance'], true)) {
            $paymentsQuery->whereIn('type', ['BG', 'Beginning Balance']);
        } else {
            $paymentsQuery->where('type', $type);
        }

        $payments = $paymentsQuery->get();

        foreach ($payments as $payment) {
            $amountPaid = $payment->amount_paid;
            $runningBalance -= $amountPaid;
            $paymentsSum += $amountPaid;

            $transactions[] = [
                'date' => $payment->payment_date,
                'transaction_no' => $payment->payment_no,
                'description' => 'Payment (' . $payment->payment_type . ')',
                'type' => 'Payment',
                'debit' => 0,
                'credit' => $amountPaid,
                'balance' => $runningBalance,
            ];
        }
        
        // Fetch the matching ledger row for contextual fields (overage/shrinkage/return/wht)
        $ledgerRowQuery = CustomerLedger::where('invoice_number', $invoiceNo);
        if (in_array($type, ['BG', 'Beginning Balance'], true)) {
            $ledgerRowQuery->whereIn('type', ['BG', 'Beginning Balance']);
        } else {
            $ledgerRowQuery->where('type', $type);
        }
        $ledgerRow = $ledgerRowQuery->orderByDesc('id')->first();
        $overage = $ledgerRow->overage ?? 0.00;
        $shrinkage = $ledgerRow->shrinkage ?? 0.00;
        $returnAmt = $ledgerRow->return ?? 0.00;
        $whtAmt = \Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('customer_ledger', 'wht_amount')
            ? ($ledgerRow->wht_amount ?? 0.00)
            : 0.00;
        if ($type === 'Beginning Balance' || $type === 'BG') {
            $beginRow = \App\Models\TransactionModels\BeginningBalance::where('beginningbalance_no', $invoiceNo)->first();
            $baseAmount = $beginRow?->balance_amount ?? 0.00;
            $beginningAmountDisplay = $baseAmount; // match transaction history initial debit
        } else {
            // match initial debit computed for non-BG entries
            $baseAmount = (($ledgerRow->amount ?? 0.00)
                - ($ledgerRow->shrinkage ?? 0.00)
                + ($ledgerRow->overage ?? 0.00)
                - ($ledgerRow->return ?? 0.00));
            $beginningAmountDisplay = $baseAmount;
        }
        $adjustedAmountVal = $baseAmount - $overage - $shrinkage - $returnAmt - $whtAmt + $posAdjSum - $negAdjSum;
        // Use the payments we computed above for the selected type to keep consistent with transaction history
        $finalRunningBalance = $adjustedAmountVal - $paymentsSum;
        
        // Sort transactions by date if needed, but they are usually added in chronological order of processing logic
        // If strict date sorting is needed, we can collect all and sort.

        return response()->json([
            'data' => $transactions,
            'summary' => [
                'pos_adjustment' => $posAdjSum,
                'neg_adjustment' => $negAdjSum,
                'payments_total' => $paymentsSum,
                'beginning_amount' => $beginningAmountDisplay,
                'adjusted_amount' => $adjustedAmountVal,
                'running_balance' => $finalRunningBalance,
                'overage' => $overage,
                'shrinkage' => $shrinkage,
                'return_amount' => $returnAmt,
                'wht_amount' => $whtAmt,
            ],
        ]);
    }

    //API
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'invoice_number' => [
                    'required',
                    Rule::unique('customer_ledger')->where(function ($query) use ($request) {
                        return $query->where('type', $request->type);
                    }),
                ],
                'date' => 'required|date',
                'type' => 'required',
                'customer_code' => 'required',
                'customer_name' => 'required',
                'currency' => 'required',
                'amount' => 'required|numeric',
                'adjusted_amount' => 'required|numeric',
                'amount_paid' => 'required|numeric',
                'running_balance' => 'required|numeric',
                'trade_type' => 'required',
                'shrinkage' => 'required|numeric',
                'overage' => 'required|numeric',
                'return' => 'required|numeric',
                'si_payment_type' => 'required',
            ]);

            // Laravel will automatically add timestamps
            // Force the connection to be 'tenant' if bu_id was provided (handled by middleware)
            $ledger = new CustomerLedger();
            $ledger->setConnection('tenant'); // Ensure it uses the tenant connection
            $ledger->fill($validated);
            $ledger->save();

            event(new NewCreated('customerledger'));

            return response()->json([
                'success' => true,
                'id' => $ledger->id,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage(), // Optional: for debugging only
            ], 500);
        }
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'invoice_number' => 'required|string',
            'type' => 'required|string',
            'shrinkage' => 'required|numeric',
            'overage' => 'required|numeric',
            'return' => 'required|numeric',
        ]);

        try {
            // $ledger = CustomerLedger::where('invoice_number', $validated['invoice_number'])
            //     ->where('type', $validated['type'])
            //     ->firstOrFail();

            // $ledger->update([
            //     'shrinkage' => $validated['shrinkage'],
            //     'overage' => $validated['overage'],
            //     'return' => $validated['return'],
            // ]);

            $ledger = CustomerLedger::where('invoice_number', $validated['invoice_number'])
                ->where('type', $validated['type'])
                ->firstOrFail();

            // Initialize variables with default values
            $shrinkage = $validated['shrinkage'] ?? 0;
            $overage = $validated['overage'] ?? 0;
            $return = $validated['return'] ?? 0;

            // Calculate new running balance
            $newRunningBalance = $ledger->running_balance
                - $shrinkage
                + $overage
                - $return;

            $uShrinkage = ($ledger->shrinkage ?: 0.00) + $shrinkage;
            $uOverage = ($ledger->overage ?: 0.00) + $overage;
            $uReturn = ($ledger->return ?: 0.00) + $return;

            // Update the ledger
            $ledger->update([
                'shrinkage' => $uShrinkage,
                'overage' => $uOverage,
                'return' => $uReturn,
                'running_balance' => $newRunningBalance,
            ]);

            event(new NewCreated('customerledger'));

            return response()->json([
                'success' => true,
                'message' => 'Ledger adjustments updated successfully',
                'data' => $ledger,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'No ledger entry found with the specified invoice number and type',
            ], 404);
        }
    }

    public function getCustomerLedgerList(Request $request)
    {
        $documentNo = $request->query('document_no');

        if (! $documentNo) {
            return response()->json(['data' => 'NOT FOUND']);
        }

        $ledger = CustomerLedger::where('type', 'Sales Invoice')
            ->where('invoice_number', $documentNo)
            ->first();

        if (! $ledger) {
            return response()->json(['data' => 'NOT FOUND']);
        }

        $hasFloatingPayment = PaymentDetails::where('document_no', $documentNo)
            ->where('type', 'Sales Invoice')
            ->where('status', 'Floating')
            ->exists();

        if ($ledger->amount_paid !== 0 && $hasFloatingPayment) {
            return response()->json(['data' => 'Paid']);
        }

        return response()->json(['data' => 'Not Paid']);
    }

    // /NON API/ ///////////////////////////////////////////////////////////////////

    public function getPaymentDetails(Request $request)
    {
        $customerCode = $request->input('customer_code');

        // Now get all ledger entries for these invoice numbers
        $payments = PaymentDetails::where('customer_code', $customerCode)
            ->select('payment_no', 'document_no', 'document_date', 'payment_date', 'payment_type', 'type', 'check_type', 'amount_paid', 'wht_amount', 'status')
            ->get();

        // Format the results
        $result = $payments->map(function ($payment) {
            return [
                'payment_no' => $payment->payment_no,
                'document_no' => $payment->document_no,
                'document_date' => $payment->document_date,
                'payment_date' => $payment->payment_date,
                'payment_type' => $payment->payment_type,
                'type' => $payment->type,
                'check_type' => $payment->check_type,
                'amount_paid' => $payment->amount_paid,
                'wht_amount' => $payment->wht_amount,
                'status' => $payment->status,
            ];
        });

        return response()->json($result);
    }

    public function getFloatingChecks(Request $request)
    {

        $validated = $request->validate([
            'customer_code' => 'required',
            'checktype' => 'required',
            'clearingdate' => 'required',
        ]);

        $customerCode = $validated['customer_code'];
        $checktype = $validated['checktype'];
        $clearingDate = $validated['clearingdate'];

        $payments = DB::table('payment_details')
            ->where('customer_code', $customerCode)
            ->where('check_type', $checktype) // Only get check payments
            ->where('due_date', '<=', $clearingDate) // Only get check payments
            ->where(function ($query) {
                $query->where('status', 'Floating')
                    ->orWhereNull('status');
            })
            ->select([
                'payment_no',
                'check_no',
                'document_no',
                'type',
                'due_date',
                'amount_paid',
                'status',
            ])
            ->orderBy('due_date', 'asc') // Add ordering
            ->get();

        return response()->json($payments);
    }

    public function getFloatingWht(Request $request)
    {

        $validated = $request->validate([
            'customer_code' => 'required',
            'payment_type' => 'required',
            'clearingdate' => 'required',
        ]);

        $customerCode = $validated['customer_code'];
        $paymentType = $validated['payment_type'];
        $clearingDate = $validated['clearingdate'];

        $payments = DB::table('payment_details')
            ->where('customer_code', $customerCode)
            ->where('payment_type', $paymentType) // Only get wht payments
            ->where('payment_receipt_date', '<=', $clearingDate) // Only get wht payments
            ->where(function ($query) {
                $query->where('status', 'Floating')
                    ->orWhereNull('status');
            })
            ->select([
                'payment_no',
                'check_no',
                'document_no',
                'type',
                'payment_receipt_date',
                'amount_paid',
                'status',
            ])
            ->orderBy('payment_receipt_date', 'asc') // Add ordering
            ->get();

        return response()->json($payments);
    }

    public function getPaymentList(Request $request)
    {
        $customerCode = $request->input('customer_code');
        $document_no = $request->input('document_no');
        $type = $request->input('type');

        $validatedDocumentNo = CustomerLedger::where('invoice_number', $document_no)
            ->where('type', 'Sales Invoice')
            ->where('si_payment_type', 'Cash')
            ->first();

        if ($validatedDocumentNo) {
            return response()->json([]);
        }

        // Now get all ledger entries for these invoice numbers
        $payments = PaymentDetails::where('customer_code', $customerCode)
            ->where('document_no', $document_no)
            ->where('type', $type)
            ->where('status', '!=', 'Cancelled')
            ->select('id', 'payment_no', 'document_no', 'payment_receipt_date', 'document_date', 'payment_date', 'payment_type', 'type', 'advpy_amount_paid', 'amount_paid', 'status', 'remarks')
            ->get();

        // Format the results
        $result = $payments->map(function ($payment) {
            return [
                'id' => $payment->id,
                'payment_no' => $payment->payment_no,
                'document_no' => $payment->document_no,
                'payment_receipt_date' => $payment->payment_receipt_date,
                'document_date' => $payment->document_date,
                'payment_date' => $payment->payment_date,
                'payment_type' => $payment->payment_type,
                'type' => $payment->type,
                'advpy_amount_paid' => $payment->advpy_amount_paid,
                'amount_paid' => $payment->amount_paid,
                'status' => $payment->status,
                'remarks' => $payment->remarks,
            ];
        });

        return response()->json($result);
    }

    public function getDocumentsPaidList(Request $request)
    {
        $payment_no = $request->input('payment_no');

        $paymentInfo = Payment::where('payment_no', $payment_no)
            ->select('document_no', 'customer_code', 'name', 'type')
            ->first();

        if ($paymentInfo) {
            $validatedDocumentNo = CustomerLedger::where('invoice_number', $paymentInfo->document_no)
                ->where('type', 'Sales Invoice')
                ->where('si_payment_type', 'Cash')
                ->first();

            if ($validatedDocumentNo) {
                return response()->json([]);
            }
        }

        // Now get all ledger entries for these invoice numbers
        $payments = PaymentDetails::where('payment_no', $payment_no)
            ->where('status', '!=', 'Cancelled')
            ->select('id', 'payment_no', 'document_no', 'payment_receipt_date', 'document_date', 'payment_date', 'payment_type', 'type', 'advpy_amount_paid', 'amount_paid', 'status', 'remarks')
            ->get();

        // Format the results
        $result = $payments->map(function ($payment) {
            return [
                'id' => $payment->id,
                'payment_no' => $payment->payment_no,
                'document_no' => $payment->document_no,
                'payment_receipt_date' => $payment->payment_receipt_date,
                'document_date' => $payment->document_date,
                'payment_date' => $payment->payment_date,
                'payment_type' => $payment->payment_type,
                'type' => $payment->type,
                'advpy_amount_paid' => $payment->advpy_amount_paid,
                'amount_paid' => $payment->amount_paid,
                'status' => $payment->status,
                'remarks' => $payment->remarks,
            ];
        });

        return response()->json([
            'payment_info' => $paymentInfo,
            'payment_details' => $result,
        ]);
    }

    public function getDocumentListToCancel()
    {
        $customers = CustomerLedger::select(['invoice_number', 'type', 'customer_code', 'customer_name'])
            ->get()
            ->toArray();

        return response()->json($customers);
    }
}
