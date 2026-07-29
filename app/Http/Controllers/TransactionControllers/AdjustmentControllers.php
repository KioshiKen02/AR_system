<?php

namespace App\Http\Controllers\TransactionControllers;

use App\Events\NewCreated;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\MasterfileModels\AdjustmentReasonSetup;
use App\Models\ReportModels\CustomerLedger;
use App\Models\TransactionModels\Adjustment;
use App\Models\TransactionModels\BeginningBalance;
use App\Models\TransactionModels\Invoice;
use App\Models\TransactionModels\PaymentDetails;
use App\Services\AdjustmentNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AdjustmentControllers extends Controller
{
    private function getEffectivePaidAmount(PaymentDetails $detail, bool $hasPaymentDetailsWhtColumns): float
    {
        if (!in_array($detail->status, ['Paid', 'Cleared'], true)) {
            return 0.0;
        }

        $effectivePaid = (float) ($detail->amount_paid ?? 0);

        if ((float) ($detail->wht_amount ?? 0) > 0) {
            $effectivePaid = max(0, $effectivePaid - (float) ($detail->wht_amount ?? 0));
        }

        return $effectivePaid;
    }

    private function getEffectiveClearedWhtAmount(PaymentDetails $detail, bool $hasPaymentDetailsWhtColumns): float
    {
        $whtAmount = (float) ($detail->wht_amount ?? 0);
        if ($whtAmount <= 0 || $detail->status === 'Cancelled') {
            return 0.0;
        }

        if (!$hasPaymentDetailsWhtColumns) {
            return $whtAmount;
        }

        return in_array($detail->wht_status, [null, 'Cleared'], true) ? $whtAmount : 0.0;
    }

    private function syncPaymentDetailOverpayment(string $documentNo, string $type, float $debit): void
    {
        if (!Schema::connection('tenant')->hasColumn('payment_details', 'overpayment_amount')) {
            return;
        }

        $hasPaymentDetailsWhtColumns = Schema::connection('tenant')->hasColumn('payment_details', 'wht_amount')
            && Schema::connection('tenant')->hasColumn('payment_details', 'wht_status');

        $rows = PaymentDetails::on('tenant')->where('document_no', $documentNo)
            ->where('type', $type)
            ->orderBy('payment_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $runningCredited = 0.0;

        foreach ($rows as $row) {
            if ($row->status === 'Cancelled') {
                $row->update(['overpayment_amount' => 0]);
                continue;
            }

            $previousOverflow = max(0, $runningCredited - $debit);
            $runningCredited += in_array($row->status, ['Floating', 'Paid', 'Cleared'], true)
                ? (float) ($row->amount_paid ?? 0)
                : 0.0;
            $currentOverflow = max(0, $runningCredited - $debit);

            $row->update([
                'overpayment_amount' => max(0, $currentOverflow - $previousOverflow),
            ]);
        }
    }

    private function syncLedgerOverpayment(CustomerLedger $ledger): void
    {
        if (!Schema::connection('tenant')->hasColumn('customer_ledger', 'overpayment_amount')) {
            return;
        }

        $baseAmount = (float) ($ledger->adjusted_amount ?? $ledger->amount ?? 0);
        $overage = (float) ($ledger->overage ?? 0);
        $shrinkage = (float) ($ledger->shrinkage ?? 0);
        $returnAmount = (float) ($ledger->return ?? 0);
        $debit = $baseAmount + $overage - $shrinkage - $returnAmount;
        $totalCollectiblePaid = (float) PaymentDetails::on('tenant')->where('document_no', $ledger->invoice_number)
            ->where('type', $ledger->type)
            ->whereIn('status', ['Floating', 'Paid', 'Cleared'])
            ->sum('amount_paid');

        $ledger->update([
            'overpayment_amount' => max(0, $totalCollectiblePaid - $debit),
        ]);
        $this->syncPaymentDetailOverpayment($ledger->invoice_number, $ledger->type, $debit);
    }

    private function getActiveWhtTotal(string $documentNo, string $type): float
    {
        if (!Schema::hasColumn('payment_details', 'wht_amount')) {
            return 0.0;
        }

        $query = PaymentDetails::on('tenant')->where('document_no', $documentNo)
            ->where('type', $type)
            ->where('status', '!=', 'Cancelled')
            ->where('wht_amount', '>', 0);

        if (Schema::hasColumn('payment_details', 'wht_status')) {
            $query->where(function ($q) {
                $q->whereNull('wht_status')
                    ->orWhere('wht_status', 'Floating')
                    ->orWhere('wht_status', 'Cleared');
            });
        }

        return (float) $query->sum('wht_amount');
    }

    private function getFloatingWhtTotal(string $documentNo, string $type): float
    {
        if (!Schema::hasColumn('payment_details', 'wht_amount')) {
            return 0.0;
        }

        $query = PaymentDetails::on('tenant')->where('document_no', $documentNo)
            ->where('type', $type)
            ->where('status', '!=', 'Cancelled')
            ->where('wht_amount', '>', 0);

        if (Schema::hasColumn('payment_details', 'wht_status')) {
            $query->where(function ($q) {
                $q->where('wht_status', 'Floating')
                    ->orWhereNull('wht_status');
            });
        }

        return (float) $query->sum('wht_amount');
    }

    private function paymentDetailsEffectivePaidSelect(bool $hasPaymentDetailsWhtColumns): string
    {
        if (!$hasPaymentDetailsWhtColumns) {
            return 'document_no, type, SUM(amount_paid) as total_paid';
        }

        return "document_no, type,
            SUM(
                CASE
                    WHEN COALESCE(wht_amount, 0) > 0
                    THEN GREATEST(COALESCE(amount_paid, 0) - COALESCE(wht_amount, 0), 0)
                    ELSE COALESCE(amount_paid, 0)
                END
            ) as total_paid";
    }

    private function recomputeLedgerFromCurrentState(CustomerLedger $ledger): void
    {
        $hasLedgerWhtAmount = Schema::connection('tenant')->hasColumn('customer_ledger', 'wht_amount');
        $hasLedgerOverpaymentAmount = Schema::connection('tenant')->hasColumn('customer_ledger', 'overpayment_amount');
        $hasPaymentDetailsWhtColumns = Schema::connection('tenant')->hasColumn('payment_details', 'wht_amount')
            && Schema::connection('tenant')->hasColumn('payment_details', 'wht_status');

        $ledger->refresh();

        $applyTo = $ledger->type;
        if ($ledger->type === 'Charge Invoice') {
            $applyTo = 'Other Income';
        } elseif ($ledger->type === 'Merchandise Charge Invoice') {
            $applyTo = 'Merchandise Charge Invoice';
        } elseif ($ledger->type === 'Merchandise Transfer Out') {
            $applyTo = 'Merchandise Transfer Out';
        } elseif ($ledger->type === 'Sales Charge Invoice') {
            $applyTo = 'Sales Charge Invoice';
        } elseif ($ledger->type === 'BG' || $ledger->type === 'Beginning Balance') {
            $applyTo = 'Beginning Balance';
        }

        $amount = (float) ($ledger->amount ?? 0);
        if ($ledger->type === 'BG' || $ledger->type === 'Beginning Balance') {
            $beginRow = BeginningBalance::on('tenant')->where('beginningbalance_no', $ledger->invoice_number)->first();
            if ($beginRow) {
                $amount = (float) $beginRow->balance_amount;
            }
        }

        $posNeg = Adjustment::on('tenant')->where('invoice_no', $ledger->invoice_number)
            ->where('apply_to', $applyTo)
            ->selectRaw("
                SUM(CASE WHEN type='Positive' THEN amount ELSE 0 END) as pos_sum,
                SUM(CASE WHEN type='Negative' THEN amount ELSE 0 END) as neg_sum
            ")
            ->first();

        $posSum = (float) ($posNeg->pos_sum ?? 0);
        $negSum = (float) ($posNeg->neg_sum ?? 0);
        $netAdjustment = $posSum - $negSum;

        $shrinkage = (float) ($ledger->shrinkage ?? 0);
        $overage = (float) ($ledger->overage ?? 0);
        $returnAmount = (float) ($ledger->return ?? 0);

        $debit = $amount + $netAdjustment - $shrinkage + $overage - $returnAmount;
        $adjustedAmount = $amount + $netAdjustment;

        $paidRow = PaymentDetails::on('tenant')->where('document_no', $ledger->invoice_number)
            ->where('type', $ledger->type)
            ->whereIn('status', ['Paid', 'Cleared'])
            ->selectRaw($this->paymentDetailsEffectivePaidSelect($hasPaymentDetailsWhtColumns))
            ->groupBy('document_no', 'type')
            ->first();
        $paid = (float) ($paidRow->total_paid ?? 0);

        $grossRow = PaymentDetails::on('tenant')->where('document_no', $ledger->invoice_number)
            ->where('type', $ledger->type)
            ->whereIn('status', ['Floating', 'Paid', 'Cleared'])
            ->selectRaw('document_no, type, SUM(COALESCE(amount_paid, 0)) as total_gross_paid')
            ->groupBy('document_no', 'type')
            ->first();
        $grossPaid = (float) ($grossRow->total_gross_paid ?? 0);

        $wht = 0.0;
        if ($hasLedgerWhtAmount && Schema::connection('tenant')->hasColumn('payment_details', 'wht_amount')) {
            $whtQuery = PaymentDetails::on('tenant')->where('document_no', $ledger->invoice_number)
                ->where('type', $ledger->type)
                ->whereIn('status', ['Paid', 'Cleared'])
                ->where('wht_amount', '>', 0);

            if ($hasPaymentDetailsWhtColumns) {
                $whtQuery->where(function ($q) {
                    $q->whereNull('wht_status')
                        ->orWhere('wht_status', 'Cleared');
                });
            }

            $whtRow = $whtQuery
                ->selectRaw('document_no, type, SUM(wht_amount) as total_wht')
                ->groupBy('document_no', 'type')
                ->first();

            $wht = (float) ($whtRow->total_wht ?? 0);
        }

        $totalCredited = $paid + $wht;
        $runningBalance = max(0, $debit - $totalCredited);
        $overpaymentAmount = max(0, $grossPaid - $debit);

        $ledgerUpdate = [
            'amount' => $amount,
            'amount_paid' => $paid,
            'adjusted_amount' => $adjustedAmount,
            'positive_adjustment_amount' => $posSum,
            'negative_adjustment_amount' => $negSum,
            'running_balance' => $runningBalance,
        ];

        if ($hasLedgerWhtAmount) {
            $ledgerUpdate['wht_amount'] = $wht;
        }
        if ($hasLedgerOverpaymentAmount) {
            $ledgerUpdate['overpayment_amount'] = $overpaymentAmount;
        }

        $ledger->update($ledgerUpdate);
        $ledger->refresh();
        $this->syncPaymentDetailOverpayment($ledger->invoice_number, $ledger->type, $debit);
    }

    public function index(Request $request)
    {
        $query = Adjustment::query();

        // Search functionality
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('adjustment_no', 'like', '%' . $request->search . '%')
                    ->orWhere('invoice_no', 'like', '%' . $request->search . '%');
            });
        }

        // Date sorting
        if ($request->date_start) {
            $query->whereDate('receipt_date', '>=', $request->date_start);
        }

        if ($request->date_end) {
            $query->whereDate('receipt_date', '<=', $request->date_end);
        }


        // Type filters
        if ($request->type_filters) {
            $types = is_array($request->type_filters)
                ? $request->type_filters
                : explode(',', $request->type_filters);
            $query->whereIn('type', $types);
        }

        // Apply To filters
        if ($request->type_filtersApplyTo) {
            $types = is_array($request->type_filtersApplyTo)
                ? $request->type_filtersApplyTo
                : explode(',', $request->type_filtersApplyTo);
            $query->whereIn('apply_to', $types);
        }



        // Code sorting
        if ($request->code_sort) {
            $query->orderBy('adjustment_no', $request->code_sort === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('adjustment_no', 'desc');
        }

        return Inertia::render('Adjustment', [
            'adjustments' => $query->paginate(10)->withQueryString(),
            'searchTerm' => $request->search,
            'filters' => [
                'code_sort' => $request->code_sort,
                'type_filters' => $request->type_filters ?
                    (is_array($request->type_filters) ?
                        $request->type_filters :
                        explode(',', $request->type_filters)) :
                    [],
                'type_filtersApplyTo' => $request->type_filtersApplyTo ?
                    (is_array($request->type_filtersApplyTo) ?
                        $request->type_filtersApplyTo :
                        explode(',', $request->type_filtersApplyTo)) :
                    [],
                'date_start' => $request->date_start,
                'date_end' => $request->date_end,
            ],
            'broadcastChannel' => 'adjustments',
        ]);
    }

    public function addAdjustment(Request $request, AdjustmentNumberService $adjustmentNumberService)
    {

        $cl_type = $request->input('_cl_type', '');

        $validated = $request->validate(
            [
                'adjustment_no' => ['required', 'string'],
                'receipt_date' => ['required', 'date', 'before_or_equal:today'],
                'transaction_date' => ['required', 'date', 'before_or_equal:today'],
                'customer_code' => ['required', 'string'],
                'name' => ['required', 'string'],
                'type' => ['required', 'string'],
                'apply_to' => ['required', 'in:Sales Invoice,Other Income,Merchandise Charge Invoice,Merchandise Transfer Out,Sales Charge Invoice,Beginning Balance'],
                'invoice_no' => ['required', 'string'],
                'balance' => ['required', 'numeric'],
                'adjustment_reason' => ['required', 'string'],
                'adjustment_code' => ['nullable', 'string'],
                'particulars' => ['required', 'string'],
                'amount' => [
                    'required',
                    'numeric',
                    function ($attribute, $value, $fail) use ($request) {
                        $amount = round((float) $value, 2);
                        $balance = round((float) $request->balance, 2);
                        if ($request->type === 'Negative' && $amount > $balance) {
                            $fail('Amount Should Not Be Greater Than Available Balance');
                        }
                    },
                ],
            ],
            [
                'adjustment_no.required' => 'Adjustment Number Required',
                'receipt_date.required' => 'Date Required',
                'receipt_date.date' => 'Date Must Be Valid Date',
                'receipt_date.before_or_equal' => 'Date Cannot Be Advance',
                'transaction_date.required' => 'Date Required',
                'transaction_date.date' => 'Date Must Be Valid Date',
                'transaction_date.before_or_equal' => 'Date Cannot Be Advance',
                'customer_code.required' => 'Customer Code Required',
                'name.required' => 'Customer Name Required',
                'type.required' => 'Type Required',
                'apply_to.required' => 'Apply To Required',
                'apply_to.in' => 'Apply To Required',
                'invoice_no.required' => 'Document Number Required',
                'adjustment_reason.required' => 'Adjustment Reason Required',
                'particulars.required' => 'Particular Required',
                'amount.required' => 'Amount Required',
                'amount.numeric' => 'Amount Must Be Valid number',
            ]
        );

        $adjNo = DB::transaction(function () use ($validated, $request, $cl_type, $adjustmentNumberService) {
            $adjustmentNumber = $adjustmentNumberService->generate();

            if (Adjustment::where('adjustment_no', $adjustmentNumber)->exists()) {
                throw ValidationException::withMessages([
                    'general' => 'Error Please Try Again',
                ]);
            }
            if ($cl_type === 'Beginning Balance') {
                $formattedType = 'BG';

                $ledger = CustomerLedger::where('invoice_number', $validated['invoice_no']) 
                    ->where('type', $formattedType) 
                    ->firstOrFail();

                $beginningBalance = BeginningBalance::where('beginningbalance_no', $validated['invoice_no'])->first();

                if ($beginningBalance && $ledger->amount != $beginningBalance->balance_amount) {
                    $ledger->update(['amount' => $beginningBalance->balance_amount]);
                    $ledger->refresh();
                }

                $existingPositive = Adjustment::where('invoice_no', $validated['invoice_no'])
                    ->where('apply_to', 'Beginning Balance')
                    ->where('type', 'Positive')
                    ->sum('amount');

                $existingNegative = Adjustment::where('invoice_no', $validated['invoice_no'])
                    ->where('apply_to', 'Beginning Balance')
                    ->where('type', 'Negative')
                    ->sum('amount');

                $amount = $ledger->amount;
                $currentAdjusted = $amount + $existingPositive - $existingNegative;
                $syncedRunningBalance = $currentAdjusted - $ledger->amount_paid;

                $floatingPaid = PaymentDetails::where('document_no', $ledger->invoice_number)
                    ->where('type', $ledger->type)
                    ->whereIn('status', ['Floating', 'Paid', 'Cleared'])
                    ->sum('amount_paid');
                $activeWhtApplied = $this->getActiveWhtTotal($ledger->invoice_number, $ledger->type);

                $newPositive = $existingPositive;
                $newNegative = $existingNegative;

                if (strtolower($validated['type']) === 'positive') {
                    $newPositive += $validated['amount'];
                } elseif (strtolower($validated['type']) === 'negative') {
                    $prospectiveNegative = $existingNegative + $validated['amount'];
                    $prospectiveAdjusted = $amount + $newPositive - $prospectiveNegative;

                    if (round((float) $prospectiveAdjusted - (float) ($floatingPaid + $activeWhtApplied), 2) < 0) {
                        throw ValidationException::withMessages([
                            'amount' => 'Amount Exceeds Available Balance. Selected Document Has A Total Payment/WHT Applied Or Floating of ' . number_format($floatingPaid + $activeWhtApplied, 2),
                        ]);
                    }

                    $newNegative = $prospectiveNegative;
                }

                $newAdjustedAmount = $amount + $newPositive - $newNegative;
                $newRunningBalance = $newAdjustedAmount - $ledger->amount_paid;

                $ledger->update([
                    'adjusted_amount' => $newAdjustedAmount,
                    'positive_adjustment_amount' => $newPositive,
                    'negative_adjustment_amount' => $newNegative,
                    'running_balance' => $newRunningBalance,
                ]);
                $ledger->refresh();
                $this->syncLedgerOverpayment($ledger);

                $dbData = collect($validated)
                    ->except(['_cl_type'])
                    ->all();
                $dbData['adjustment_no'] = $adjustmentNumber;
                $dbData['created_by'] = $request->user()->name;
                Adjustment::create($dbData);

                return $adjustmentNumber;
            }

            $ledger = CustomerLedger::where('invoice_number', $validated['invoice_no'])->where('type', $cl_type)->firstOrFail();

            $floatingPaid = PaymentDetails::where('document_no', $ledger->invoice_number)
                ->where('type', $ledger->type)
                ->where('status', 'Floating')
                ->sum('amount_paid');
            $floatingWht = $this->getFloatingWhtTotal($ledger->invoice_number, $ledger->type);

            $availableBalance = round((float) $ledger->running_balance - (float) $floatingPaid - (float) $floatingWht, 2);
            $adjustmentAmount = round((float) $validated['amount'], 2);
            if (strtolower($validated['type']) === 'negative' && round($availableBalance - $adjustmentAmount, 2) < 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Amount Exceeds Available Balance. Selected Document Has A Total Floating Payment/WHT of ' . number_format($floatingPaid + $floatingWht, 2),
                ]);
            }

            $existingPositive = (float) Adjustment::where('invoice_no', $validated['invoice_no'])
                ->where('apply_to', $validated['apply_to'])
                ->where('type', 'Positive')
                ->sum('amount');

            $existingNegative = (float) Adjustment::where('invoice_no', $validated['invoice_no'])
                ->where('apply_to', $validated['apply_to'])
                ->where('type', 'Negative')
                ->sum('amount');

            $currentRunningBalance = $ledger->running_balance;
            $currentAmount = $ledger->adjusted_amount;
            $newPositive = $existingPositive;
            $newNegative = $existingNegative;

            if (strtolower($validated['type']) === 'positive') {
                $newAmount = $currentRunningBalance + $validated['amount'];
                $newAdjustmentAmount = $validated['amount'] + $currentAmount;
                $newPositive += $validated['amount'];
            } elseif (strtolower($validated['type']) === 'negative') {
                $newAmount = $currentRunningBalance - $validated['amount'];
                $newAmount = max($newAmount, 0);
                $newAdjustmentAmount = $currentAmount - $validated['amount'];
                $newNegative += $validated['amount'];
            }

            $ledger->update([
                'running_balance' => $newAmount,
                'adjusted_amount' => $newAdjustmentAmount,
                'positive_adjustment_amount' => $newPositive,
                'negative_adjustment_amount' => $newNegative,
            ]);
            $ledger->refresh();
            $this->syncLedgerOverpayment($ledger);

            $dbData = collect($validated)
                ->except(['_cl_type'])
                ->all();
            $dbData['adjustment_no'] = $adjustmentNumber;
            $dbData['created_by'] = $request->user()->name;
            Adjustment::create($dbData);

            //DYNAMIC API LINK - Send API request for all adjustment types
            $userAppSetting = $request->user()->appSetting;
            
            // We need a mapping between App Settings (DB config) and the Centralized Invoicing API URLs.
            // The switch case below hardcodes URLs based on app name. 
            // We should ideally store these URLs in the AppSetting model or derive them.
            // For now, we will try to use the user's app setting NAME to switch, 
            // effectively replacing config('app.name') with $userAppSetting->app_name.
            
            $appName = $userAppSetting ? $userAppSetting->app_name : config('app.name');
            
            $baseUrl = $this->adjustmentSalesBaseUrlForApp($appName);
            if ($baseUrl === null && $appName !== 'Ar System') {
                throw new \Exception("Unknown app name: {$appName}");
            }
            
            // Calculate the total of all adjustments for this invoice to send to the API
            $existingPositive = (float) Adjustment::where('invoice_no', $validated['invoice_no'])
                ->where('apply_to', $validated['apply_to'])
                ->where('type', 'Positive')
                ->sum('amount');
            $existingNegative = (float) Adjustment::where('invoice_no', $validated['invoice_no'])
                ->where('apply_to', $validated['apply_to'])
                ->where('type', 'Negative')
                ->sum('amount');
            
            $newPositive = $existingPositive;
            $newNegative = $existingNegative;
            if (strtolower($validated['type']) === 'positive') {
                $newPositive += $validated['amount'];
            } else {
                $newNegative += $validated['amount'];
            }
            
            $apiAdjustmentValue = $newPositive - $newNegative;
            
            if ($baseUrl) {
                $url = preg_replace('/^(https?:\/\/)\s+/', '$1', trim($baseUrl));
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    Log::error('Adjustment Sales API Failed', [
                        'app_name' => $appName,
                        'url' => $url,
                        'status' => null,
                        'response_body' => null,
                        'response_json' => null,
                        'payload' => [
                            'adj_sales' => (string) $apiAdjustmentValue,
                            'tds_no'    => $validated['invoice_no'],
                        ],
                        'exception' => 'Invalid URL',
                    ]);
                    return $adjustmentNumber;
                }
                try {
                    $response = Http::timeout(3)
                        ->retry(2, 200)
                        ->withHeaders([
                            'Accept' => 'application/json',
                        ])->post($url, [
                            'adj_sales' => (string) $apiAdjustmentValue,
                            'tds_no' => $validated['invoice_no'],
                        ]);
                    if (!$response->successful()) {
                        Log::error('Adjustment Sales API Failed', [
                            'app_name' => $appName,
                            'url' => $url,
                            'status' => $response->status(),
                            'response_body' => $response->body(),
                            'response_json' => $response->json(),
                            'payload' => [
                                'adj_sales' => (string) $apiAdjustmentValue,
                                'tds_no'    => $validated['invoice_no'],
                            ],
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::error('Adjustment Sales API Failed', [
                        'app_name' => $appName,
                        'url' => $url,
                        'status' => null,
                        'response_body' => null,
                        'response_json' => null,
                        'payload' => [
                            'adj_sales' => (string) $apiAdjustmentValue,
                            'tds_no'    => $validated['invoice_no'],
                        ],
                        'exception' => $e->getMessage(),
                    ]);
                }
            }
            return $adjustmentNumber;
        });

        event(new NewCreated('adjustment'));
        event(new NewCreated('customerledger'));

        session()->put('adjustment_number', $adjNo);
        return redirect()->back();
    }

    public function syncAdjustmentSales(Request $request, $adjustment)
    {
        $invoiceNo = $request->input('invoice_no');
        $applyTo = $request->input('apply_to');

        $adjustmentRecord = Adjustment::on('tenant')->withTrashed()->find($adjustment);
        if ($adjustmentRecord) {
            $invoiceNo = $adjustmentRecord->invoice_no ?: $invoiceNo;
            $applyTo = $adjustmentRecord->apply_to ?: $applyTo;
        }

        if (!$invoiceNo) {
            return response()->json([
                'success' => false,
                'message' => 'invoice_no is required.',
            ], 422);
        }
        if (!$applyTo) {
            return response()->json([
                'success' => false,
                'message' => 'apply_to is required.',
            ], 422);
        }

        $ledgerTypes = match ($applyTo) {
            'Beginning Balance' => ['BG', 'Beginning Balance'],
            default => [$applyTo],
        };

        $ledger = CustomerLedger::on('tenant')->where('invoice_number', $invoiceNo)
            ->whereIn('type', $ledgerTypes)
            ->first();

        if (!$ledger) {
            return response()->json([
                'success' => false,
                'message' => 'Customer ledger not found for this invoice.',
            ], 404);
        }

        $userAppSetting = $request->user()->appSetting;
        $appName = $userAppSetting ? $userAppSetting->app_name : config('app.name');

        $currentSettingId = config('tenant.current_app_setting_id');
        if ($currentSettingId) {
            $currentSetting = AppSetting::on('mysql')->find($currentSettingId);
            if ($currentSetting?->app_name) {
                $appName = $currentSetting->app_name;
            }
        }
        if (is_string($appName) && strcasecmp($appName, 'ar system') === 0) {
            $appName = 'Ar System';
        }

        $tenant = (string) $request->route('tenant');
        if ((!$userAppSetting || $appName === 'Ar System') && $tenant !== '') {
            $appNameFromTenant = $this->appNameFromTenant($tenant);
            if ($appNameFromTenant) {
                $appName = $appNameFromTenant;
            }
        }

        $baseUrl = $this->adjustmentSalesBaseUrlForApp($appName);
        if ($baseUrl === null && $appName !== 'Ar System') {
            return response()->json([
                'success' => false,
                'message' => "Unknown app name: {$appName}",
            ], 422);
        }

        if (!$baseUrl) {
            return response()->json([
                'success' => false,
                'message' => 'No sync URL configured for this app.',
            ], 422);
        }

        // Calculate the total of all adjustments for this invoice to send to the API
        $existingPositive = (float) Adjustment::on('tenant')->where('invoice_no', $invoiceNo)
            ->where('apply_to', $applyTo)
            ->where('type', 'Positive')
            ->sum('amount');
        $existingNegative = (float) Adjustment::on('tenant')->where('invoice_no', $invoiceNo)
            ->where('apply_to', $applyTo)
            ->where('type', 'Negative')
            ->sum('amount');
        
        $apiAdjustmentValue = $existingPositive - $existingNegative;

        $url = preg_replace('/^(https?:\/\/)\s+/', '$1', trim($baseUrl));
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            Log::error('Adjustment Sales API Failed', [
                'app_name' => $appName,
                'url' => $url,
                'status' => null,
                'response_body' => null,
                'response_json' => null,
                'payload' => [
                    'adj_sales' => (string) $apiAdjustmentValue,
                    'tds_no'    => $invoiceNo,
                ],
                'exception' => 'Invalid URL',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid sync URL.',
            ], 422);
        }

        try {
            $response = Http::timeout(5)
                ->retry(2, 200)
                ->withHeaders([
                    'Accept' => 'application/json',
                ])->post($url, [
                    'adj_sales' => (string) $apiAdjustmentValue,
                    'tds_no' => $invoiceNo,
                ]);

            if (!$response->successful()) {
                Log::error('Adjustment Sales API Failed', [
                    'app_name' => $appName,
                    'url' => $url,
                    'status' => $response->status(),
                    'response_body' => $response->body(),
                    'response_json' => $response->json(),
                    'payload' => [
                        'adj_sales' => (string) $apiAdjustmentValue,
                        'tds_no'    => $invoiceNo,
                    ],
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Sync failed. Please check logs for details.',
                ], 502);
            }

            return response()->json([
                'success' => true,
                'message' => 'Sync successful.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Adjustment Sales API Failed', [
                'app_name' => $appName,
                'url' => $url,
                'status' => null,
                'response_body' => null,
                'response_json' => null,
                'payload' => [
                    'adj_sales' => (string) $apiAdjustmentValue,
                    'tds_no'    => $invoiceNo,
                ],
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Sync failed due to a network/connection error.',
            ], 502);
        }
    }

    private function adjustmentSalesBaseUrlForApp(string $appName): ?string
    {
        $map = [
            'Bilar Breeder Local' => 'http://172.16.43.148/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=13',
            'Bilar Breeder' => 'http://172.16.220.1:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=13',
            'Gp Jagna' => 'http://172.16.112.51:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=50',
            'Ice Plant' => 'http://172.16.184.49:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=25',
            'Peanut Kisses' => 'http://172.16.184.49:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=26',
            'Cortes Poultry' => 'http://172.16.192.68:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=12',
            'Cortes Piggery' => 'http://172.16.192.68:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=11',
            'Canhayupon Breeder' => 'http://172.16.220.223:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=15',
            'Bilar Hatchery' => 'http://172.16.219.200:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=14',
            'Lapsaon Breeder' => 'http://172.16.220.222:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=16',
            'Rizal Breeder' => 'http://172.16.217.11:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=43',
            'Feedmill' => 'http://172.16.105.2:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=19',
            'Growout' => 'http://172.16.105.2:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=20',
            'Cortes Fertilizer' => 'http://172.16.105.2:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=42',
            'Ubay Fertilizer' => 'http://172.16.105.2:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=22',
            'Piggery Untaga' => 'http://172.16.105.2:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=23',
            'Demo Farm' => 'http://172.16.105.2:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=21',
            'Dressing Plant' => 'http://172.16.105.2:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=17',
            'Farmers Market' => 'http://172.16.105.2:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=41',
            'Meat Processing' => 'http://172.16.105.2:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=46',
            'Rendering' => 'http://172.16.105.2:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=18',
            'Ar System' => null,
        ];

        return $map[$appName] ?? null;
    }

    private function appNameFromTenant(string $tenant): ?string
    {
        $key = strtolower(trim($tenant));

        return match ($key) {
            'bilarbreeder' => 'Bilar Breeder',
            'bilarbreederlocal' => 'Bilar Breeder Local',
            'gpjagna' => 'Gp Jagna',
            'iceplant' => 'Ice Plant',
            'peanutkisses' => 'Peanut Kisses',
            'cortespoultry' => 'Cortes Poultry',
            'cortespiggery' => 'Cortes Piggery',
            'canhayuponbreeder' => 'Canhayupon Breeder',
            'bilarhatchery' => 'Bilar Hatchery',
            'lapsaonbreeder' => 'Lapsaon Breeder',
            'rizalbreeder' => 'Rizal Breeder',
            'feedmill' => 'Feedmill',
            'growout' => 'Growout',
            'mficortesfertilizer' => 'Cortes Fertilizer',
            'mfiubayfertilizer' => 'Ubay Fertilizer',
            'piggeryuntaga' => 'Piggery Untaga',
            'demofarm' => 'Demo Farm',
            'dressingplant' => 'Dressing Plant',
            'farmersmarket' => 'Farmers Market',
            'meatprocessing' => 'Meat Processing',
            'rendering' => 'Rendering',
            default => null,
        };
    }

    public function destroy(Request $request, $tenant, $id)
    {
        $routeId = $request->route('id');
        $targetId = is_numeric($routeId) ? (int) $routeId : $id;

        DB::connection('tenant')->transaction(function () use ($request, $targetId, $id, $routeId) {
            $adj = Adjustment::on('tenant')->withTrashed()->lockForUpdate()->find($targetId);
            if (!$adj) {
                $tenantSlug = (string) ($request->route('tenant') ?? '');
                $dbName = (string) (DB::connection('tenant')->getDatabaseName() ?? '');
                throw ValidationException::withMessages([
                    'general' => trim("Adjustment not found. Tenant: {$tenantSlug} DB: {$dbName} ID: {$targetId} (route id: {$routeId}, arg id: {$id})"),
                ]);
            }
            if ($adj->trashed()) {
                throw ValidationException::withMessages([
                    'general' => 'Adjustment is already cancelled.',
                ]);
            }

            $ledgerTypes = match ($adj->apply_to) {
                'Sales Invoice' => ['Sales Invoice'],
                'Other Income' => ['Charge Invoice'],
                'Merchandise Charge Invoice' => ['Merchandise Charge Invoice'],
                'Merchandise Transfer Out' => ['Merchandise Transfer Out'],
                'Sales Charge Invoice' => ['Sales Charge Invoice'],
                'Beginning Balance' => ['BG', 'Beginning Balance'],
                default => [],
            };

            if (empty($ledgerTypes)) {
                throw ValidationException::withMessages([
                    'general' => 'Unsupported adjustment type.',
                ]);
            }

            $ledger = CustomerLedger::on('tenant')->where('invoice_number', $adj->invoice_no)
                ->whereIn('type', $ledgerTypes)
                ->lockForUpdate()
                ->first();

            if (!$ledger) {
                throw ValidationException::withMessages([
                    'general' => 'No matching customer ledger record was found for this adjustment.',
                ]);
            }

            $adj->delete();

            $this->recomputeLedgerFromCurrentState($ledger);
            $this->syncLedgerOverpayment($ledger);

            if ($adj->apply_to === 'Sales Invoice') {
                $userAppSetting = $request->user()?->appSetting;
                $appName = $userAppSetting ? $userAppSetting->app_name : config('app.name');

                $baseUrl = $this->adjustmentSalesBaseUrlForApp($appName);
                if ($baseUrl) {
                    $url = preg_replace('/^(https?:\/\/)\s+/', '$1', trim($baseUrl));
                    if (filter_var($url, FILTER_VALIDATE_URL)) {
                        try {
                            Http::timeout(3)
                                ->retry(2, 200)
                                ->withHeaders([
                                    'Accept' => 'application/json',
                                ])->post($url, [
                                    'adj_sales' => (string) ($ledger->adjusted_amount ?? 0),
                                    'tds_no' => $adj->invoice_no,
                                ]);
                        } catch (\Throwable $e) {
                            Log::error('Adjustment cancel sales sync failed', [
                                'adjustment_id' => $adj->id,
                                'invoice_no' => $adj->invoice_no,
                                'url' => $url,
                                'exception' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }
        });

        event(new NewCreated('adjustment'));
        event(new NewCreated('customerledger'));

        return redirect()->back();
    }

    public function latest()
    {
        //return Adjustment::orderByDesc('id')->value('adjustment_no'); // returns "26000001" or null
        return DB::transaction(function () {
            $latestAdjustment = Adjustment::withTrashed()
                ->lockForUpdate() // Prevent concurrent access
                ->orderByDesc('adjustment_no')
                ->first();

            $nextNumber = $latestAdjustment ? $latestAdjustment->adjustment_no + 1 : 26000001;

            return response()->json([
                'next_adjustment_no' => $nextNumber,
                'is_new_sequence' => !$latestAdjustment
            ]);
        });
    }

    public function getAdjustmentReasonSetup(Request $request)
    {
        $apply_to = $request->input('apply_to');

        $reasons = AdjustmentReasonSetup::where('type', $apply_to)
            ->where('status', 'Active')
            ->get(['reason_name', 'acc_code']);

        return response()->json($reasons);
    }
}
