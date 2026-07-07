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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

        $shouldPersist = filter_var($request->generateTableData, FILTER_VALIDATE_BOOLEAN);

        if (! $request->customer_code || ! $shouldPersist) {
            return Inertia::render('CustomerLedger', [
                'customerledgers' => [
                    'data' => [],
                    'total' => 0,
                ],
                'paymentForwarded' => 0,
                'filters' => [
                    'customer_code' => $request->customer_code,
                    'date_start' => $request->date_start,
                    'date_end' => $request->date_end,
                ],
                'generateTableData' => $shouldPersist,
                'broadcastChannel' => 'customerledgers',
            ]);
        }

        $dateEnd = $request->date_end;
        $dateStart = $request->date_start;

        $adjustmentsPosNegQuery = Adjustment::where('customer_code', $request->customer_code)
            ->selectRaw("
                invoice_no,
                apply_to,
                SUM(CASE WHEN type='Positive' THEN amount ELSE 0 END) as pos_sum,
                SUM(CASE WHEN type='Negative' THEN amount ELSE 0 END) as neg_sum
            ")
            ->groupBy('invoice_no', 'apply_to');
        if ($dateEnd) {
            $adjustmentsPosNegQuery->whereDate('receipt_date', '<=', $dateEnd);
        }
        $adjustmentsPosNeg = $adjustmentsPosNegQuery->get()->groupBy(['invoice_no', 'apply_to']);

        $hasPaymentDetailsWhtColumns =
            \Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('payment_details', 'wht_amount') &&
            \Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('payment_details', 'wht_status');

        $paidAmountsSelect = $hasPaymentDetailsWhtColumns
            ? "document_no, type,
                SUM(
                    CASE
                        WHEN COALESCE(wht_amount, 0) > 0
                            AND wht_status IS NOT NULL
                            AND wht_status <> 'Cleared'
                        THEN GREATEST(COALESCE(amount_paid, 0) - COALESCE(wht_amount, 0), 0)
                        ELSE COALESCE(amount_paid, 0)
                    END
                ) as total_paid"
            : 'document_no, type, SUM(amount_paid) as total_paid';

        $paidAmountsQuery = PaymentDetails::where('customer_code', $request->customer_code)
            ->whereIn('status', ['Paid', 'Cleared'])
            ->selectRaw($paidAmountsSelect)
            ->groupBy('document_no', 'type');
        if ($dateEnd) {
            $paidAmountsQuery->whereDate('payment_receipt_date', '<=', $dateEnd);
        }
        $paidAmounts = $paidAmountsQuery->get()->groupBy(['document_no', 'type']);

        $floatingAmountsQuery = PaymentDetails::where('customer_code', $request->customer_code)
            ->where('status', 'Floating')
            ->selectRaw('document_no, type, SUM(amount_paid) as total_floating')
            ->groupBy('document_no', 'type');
        if ($dateEnd) {
            $floatingAmountsQuery->where(function ($q) use ($dateEnd) {
                $q->whereDate('payment_receipt_date', '<=', $dateEnd)
                    ->orWhereDate('document_date', '<=', $dateEnd);
            });
        }
        $floatingAmounts = $floatingAmountsQuery->get()->groupBy(['document_no', 'type']);

        $val = function ($v) {
            return (float) str_replace(',', '', (string) ($v ?? 0));
        };

        $applyToFor = function ($recordType) {
            if ($recordType === 'BG') {
                return 'Beginning Balance';
            }
            if ($recordType === 'Charge Invoice') {
                return 'Other Income';
            }
            if ($recordType === 'Merchandise Transfer Out') {
                return 'Merchandise Transfer Out';
            }
            if ($recordType === 'Merchandise Charge Invoice') {
                return 'Merchandise Charge Invoice';
            }
            if ($recordType === 'Sales Charge Invoice') {
                return 'Sales Charge Invoice';
            }

            return $recordType;
        };

        $computeNetDebit = function ($record) use ($val) {
            if ($record->type === 'Payment') {
                return $val($record->amount);
            }

            $amount = $val($record->amount);
            $shrinkage = $val($record->shrinkage);
            $overage = $val($record->overage);
            $return = $val($record->return);

            return $amount - $shrinkage + $overage - $return;
        };

        $computeCredit = function ($record, $netDebit, $paidAmounts, $floatingAmounts) use ($val) {
            if ($record->type === 'Payment') {
                return 0.0;
            }

            $paidRow = $paidAmounts
                ->get($record->invoice_number, collect())
                ->get($record->type, collect())
                ->first();
            $creditPaid = $paidRow?->total_paid ?? 0;

            $floatingRow = $floatingAmounts
                ->get($record->invoice_number, collect())
                ->get($record->type, collect())
                ->first();
            $creditFloating = $floatingRow?->total_floating ?? 0;

            return (float) $creditPaid + (float) $creditFloating;
        };

        $prevQuery = CustomerLedger::where('customer_code', 'like', '%' . $request->customer_code . '%');
        if ($dateStart) {
            $prevQuery->whereDate('date', '<', $dateStart);
        }
        $prevRecords = $prevQuery
            ->orderBy('customer_code')
            ->orderBy('date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        $paymentForwarded = 0;
        foreach ($prevRecords as $prev) {
            $netDebit = $computeNetDebit($prev);
            $applyTo = $applyToFor($prev->type);
            $posNeg = $adjustmentsPosNeg
                ->get($prev->invoice_number, collect())
                ->get($applyTo, collect())
                ->first();
            $posSum = $posNeg->pos_sum ?? 0;
            $negSum = $posNeg->neg_sum ?? 0;
            $netDebit += ($posSum - $negSum);

            $credit = $computeCredit($prev, $netDebit, $paidAmounts, $floatingAmounts);
            $paymentForwarded += ($netDebit - $credit);
        }

        $records = $query->clone()
            ->orderBy('customer_code')
            ->orderBy('date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        $runningBalance = $paymentForwarded;
        $processedRecords = $records->map(function ($record) use (
            &$runningBalance,
            $val,
            $applyToFor,
            $computeNetDebit,
            $computeCredit,
            $adjustmentsPosNeg,
            $paidAmounts,
            $floatingAmounts
        ) {
            $applyTo = $applyToFor($record->type);

            $netDebit = $computeNetDebit($record);
            $posNeg = $adjustmentsPosNeg
                ->get($record->invoice_number, collect())
                ->get($applyTo, collect())
                ->first();
            $posSum = $posNeg->pos_sum ?? 0;
            $negSum = $posNeg->neg_sum ?? 0;
            $netDebit += ($posSum - $negSum);

            $credit = $computeCredit($record, $netDebit, $paidAmounts, $floatingAmounts);

            $floatingRow = $floatingAmounts
                ->get($record->invoice_number, collect())
                ->get($record->type, collect())
                ->first();
            $floatingAmount = $floatingRow?->total_floating ?? 0;

            $record->debit_amount = $netDebit;
            $record->positive_adjustment_amount = $posSum;
            $record->negative_adjustment_amount = $negSum;
            $record->amount_paid = $credit;
            $record->floating_amount = $floatingAmount;
            $record->has_floating_deduction = (float) $floatingAmount > 0;
            $record->document_balance = max(0, $netDebit - $credit);

            $runningBalance += ($netDebit - $credit);
            $record->running_balance = $runningBalance;

            return $record;
        });

        return Inertia::render('CustomerLedger', [
            'customerledgers' => [
                'data' => $processedRecords->values(),
                'total' => $processedRecords->count(),
            ],
            // 'searchTerm' => $request->search,
            'paymentForwarded' => $paymentForwarded,
            'filters' => [
                'customer_code' => $request->customer_code,
                'date_start' => $request->date_start,
                'date_end' => $request->date_end,
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
                    'credit_floating' => 0,
                    'wht_floating' => 0,
                    'floating' => 0,
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
                    'credit_floating' => 0,
                    'wht_floating' => 0,
                    'floating' => 0,
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
            ->orderBy('receipt_date', 'asc')
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
                'date' => $adj->receipt_date,
                'transaction_no' => $adj->adjustment_no,
                'description' => $description,
                'type' => 'Adjustment',
                'debit' => $debit,
                'credit' => $credit,
                'credit_floating' => 0,
                'wht_floating' => 0,
                'floating' => 0,
                'balance' => $runningBalance,
            ];
        }

        // 3. Get Payments
        $paymentsQuery = PaymentDetails::where('document_no', $invoiceNo)
            ->where('status', '!=', 'Cancelled')
            ->orderBy('payment_receipt_date', 'asc');
        if (\Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('payment_details', 'wht_status')) {
            $paymentsQuery->where(function ($q) {
                $q->whereNull('wht_status')->orWhere('wht_status', '!=', 'Cancelled');
            });
        }

        // Enforce strict type matching while allowing common synonyms for Beginning Balance
        if (in_array($type, ['BG', 'Beginning Balance'], true)) {
            $paymentsQuery->whereIn('type', ['BG', 'Beginning Balance']);
        } else {
            $paymentsQuery->where('type', $type);
        }

        $payments = $paymentsQuery->get();

        foreach ($payments as $payment) {
            $amountPaid = (float) ($payment->amount_paid ?? 0);
            $wht = \Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('payment_details', 'wht_amount')
                ? (float) ($payment->wht_amount ?? 0)
                : 0.0;
            $cash = max(0.0, $amountPaid - $wht);

            $cashIsFloating = ($payment->status ?? null) === 'Floating';
            $whtIsFloating = false;
            if (\Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('payment_details', 'wht_status')) {
                $whtIsFloating = ($payment->wht_status ?? null) === 'Floating';
            }

            $creditCash = $cashIsFloating ? 0.0 : $cash;
            $floatingCash = $cashIsFloating ? $cash : 0.0;
            $creditWht = ($wht > 0 && !$whtIsFloating) ? $wht : 0.0;
            $floatingWht = ($wht > 0 && $whtIsFloating) ? $wht : 0.0;

            $creditApplied = $creditCash + $creditWht;
            $floatingApplied = $floatingCash + $floatingWht;

            $runningBalance = max(0, $runningBalance - $creditApplied);
            $paymentsSum += $creditApplied;

            $transactions[] = [
                'date' => $payment->payment_date,
                'transaction_no' => $payment->payment_no,
                'description' => 'Payment (' . $payment->payment_type . ') - ' . ($payment->status ?? 'N/A'),
                'type' => 'Payment',
                'debit' => 0,
                'credit' => $creditApplied,
                'credit_floating' => $floatingCash,
                'wht_floating' => $floatingWht,
                'floating' => $floatingApplied,
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
        $whtAmt = \Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('customer_ledger', 'wht_amount')
            ? ($ledgerRow?->wht_amount ?? 0.00)
            : 0.00;
        
        // Sort transactions by date if needed, but they are usually added in chronological order of processing logic
        // If strict date sorting is needed, we can collect all and sort.

        $hasLedgerOverpaymentAmount = Schema::connection('tenant')->hasColumn('customer_ledger', 'overpayment_amount');

        return response()->json([
            'data' => $transactions,
            'ledger' => [
                'amount' => $ledgerRow?->amount ?? 0.00,
                'adjusted_amount' => $ledgerRow?->adjusted_amount ?? 0.00,
                'positive_adjustment_amount' => $ledgerRow?->positive_adjustment_amount ?? 0.00,
                'negative_adjustment_amount' => $ledgerRow?->negative_adjustment_amount ?? 0.00,
                'amount_paid' => $ledgerRow?->amount_paid ?? 0.00,
                'running_balance' => $ledgerRow?->running_balance ?? 0.00,
                'overpayment_amount' => $hasLedgerOverpaymentAmount ? ($ledgerRow?->overpayment_amount ?? 0.00) : 0.00,
                'overage' => $ledgerRow?->overage ?? 0.00,
                'shrinkage' => $ledgerRow?->shrinkage ?? 0.00,
                'return' => $ledgerRow?->return ?? 0.00,
                'wht_amount' => $whtAmt,
            ],
            'summary' => [
                'pos_adjustment' => $ledgerRow?->positive_adjustment_amount ?? 0.00,
                'neg_adjustment' => $ledgerRow?->negative_adjustment_amount ?? 0.00,
                'payments_total' => $ledgerRow?->amount_paid ?? 0.00,
                'beginning_amount' => $ledgerRow?->amount ?? 0.00,
                'adjusted_amount' => $ledgerRow?->adjusted_amount ?? 0.00,
                'running_balance' => $ledgerRow?->running_balance ?? 0.00,
                'overpayment_amount' => $hasLedgerOverpaymentAmount ? ($ledgerRow?->overpayment_amount ?? 0.00) : 0.00,
                'overage' => $ledgerRow?->overage ?? 0.00,
                'shrinkage' => $ledgerRow?->shrinkage ?? 0.00,
                'return_amount' => $ledgerRow?->return ?? 0.00,
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
        $selectColumns = [
            'payment_no',
            'document_no',
            'document_date',
            'payment_date',
            'payment_type',
            'type',
            'check_type',
            'amount_paid',
            'wht_amount',
            'status',
        ];

        $hasPaymentDetailOverpayment = Schema::connection('tenant')->hasColumn('payment_details', 'overpayment_amount');
        if ($hasPaymentDetailOverpayment) {
            $selectColumns[] = 'overpayment_amount';
        }

        $payments = PaymentDetails::where('customer_code', $customerCode)
            ->select($selectColumns)
            ->get();

        // Format the results
        $result = $payments->map(function ($payment) use ($hasPaymentDetailOverpayment) {
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
                'overpayment_amount' => $hasPaymentDetailOverpayment ? ($payment->overpayment_amount ?? 0) : 0,
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
        $hasWhtAmount = Schema::connection('tenant')->hasColumn('payment_details', 'wht_amount');
        $hasTotalAmountLessWht = Schema::connection('tenant')->hasColumn('payment_details', 'total_amount_less_wht');

        $selectColumns = [
            'payment_no',
            'check_no',
            'document_no',
            'type',
            'due_date',
            'amount_paid',
            'status',
            'remarks',
        ];

        if ($hasWhtAmount) {
            $selectColumns[] = 'wht_amount';
        } else {
            $selectColumns[] = DB::raw('0 as wht_amount');
        }

        if ($hasTotalAmountLessWht) {
            $selectColumns[] = 'total_amount_less_wht';
        } else {
            $selectColumns[] = DB::raw('NULL as total_amount_less_wht');
        }

        $payments = DB::table('payment_details')
            ->where('customer_code', $customerCode)
            ->where('check_type', $checktype) // Only get check payments
            ->where('due_date', '<=', $clearingDate) // Only get check payments
            ->where(function ($query) {
                $query->where('status', 'Floating')
                    ->orWhereNull('status');
            })
            ->select($selectColumns)
            ->orderBy('due_date', 'asc') // Add ordering
            ->get();

        return response()->json($payments);
    }

    public function getFloatingWht(Request $request)
    {

        $validated = $request->validate([
            'customer_code' => 'required',
            'clearingdate' => 'required',
        ]);

        $customerCode = $validated['customer_code'];
        $clearingDate = $validated['clearingdate'];

        $payments = DB::table('payment_details')
            ->where('customer_code', $customerCode)
            ->where('payment_receipt_date', '<=', $clearingDate) // Only get wht payments
            ->where(function ($query) {
                $query->where('wht_status', 'Floating')
                    ->orWhereNull('wht_status');
            })
            ->where(function ($query) {
                $query->whereNotNull('wht_amount')
                    ->where('wht_amount', '>', 0);
            })
            ->select([
                'payment_no',
                'check_no',
                'document_no',
                'type',
                'payment_receipt_date',
                'wht_amount',
                'wht_status',
                DB::raw("COALESCE(wht_status, 'Floating') as status"),
                'remarks',
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
