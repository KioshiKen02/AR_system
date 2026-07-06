<?php

namespace App\Http\Controllers\TransactionControllers;

use App\Events\NewCreated;
use App\Http\Controllers\Controller;
use App\Models\MasterfileModels\Permission;
use App\Models\MasterfileModels\User;
use App\Models\ReportModels\CustomerLedger;
use App\Models\TransactionModels\Adjustment;
use App\Models\TransactionModels\BeginningBalance;
use App\Models\TransactionModels\Invoice;
use App\Models\TransactionModels\InvoiceItem;
use App\Models\TransactionModels\ManagerKeyEntries;
use App\Models\TransactionModels\Payment;
use App\Models\TransactionModels\PaymentDetails;
use App\Services\InvoiceNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class InvoiceController extends Controller
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

    private function syncPaymentDetailOverpayment(string $customerCode, string $documentNo, string $type, float $debit): void
    {
        if (!Schema::connection('tenant')->hasColumn('payment_details', 'overpayment_amount')) {
            return;
        }

        $hasPaymentDetailsWhtColumns = Schema::connection('tenant')->hasColumn('payment_details', 'wht_amount')
            && Schema::connection('tenant')->hasColumn('payment_details', 'wht_status');

        $rows = PaymentDetails::on('tenant')
            ->where('customer_code', $customerCode)
            ->where('document_no', $documentNo)
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


    public function index(Request $request)
    {
        $query = Invoice::query();

        // Search functionality
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('particular', '!=', 'N/A')
                    ->where('reference_no', '!=', 'N/A')
                    ->where('name', 'like', '%' . $request->search . '%')
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


        // Price Group filters
        if ($request->type_filtersPriceGroup) {
            $types = is_array($request->type_filtersPriceGroup)
                ? $request->type_filtersPriceGroup
                : explode(',', $request->type_filtersPriceGroup);
            $query->whereIn('price_group', $types);
        }

        // Payment Mode filters
        if ($request->type_filtersPaymentMode) {
            $types = is_array($request->type_filtersPaymentMode)
                ? $request->type_filtersPaymentMode
                : explode(',', $request->type_filtersPaymentMode);
            $query->whereIn('payment_mode', $types);
        }

        // Code sorting
        if ($request->code_sort) {
            $query->orderBy('invoice_no', $request->code_sort === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('invoice_no', 'desc');
        }

        return Inertia::render('Invoice', [
            'invoices' => $query->where('particular', '!=', 'N/A')->where('reference_no', '!=', 'N/A')->paginate(10)->withQueryString(),
            'searchTerm' => $request->search,
            'filters' => [
                'code_sort' => $request->code_sort,
                'type_filtersPriceGroup' => $request->type_filtersPriceGroup ?
                    (is_array($request->type_filtersPriceGroup) ?
                        $request->type_filtersPriceGroup :
                        explode(',', $request->type_filtersPriceGroup)) :
                    [],
                'type_filtersPaymentMode' => $request->type_filtersPaymentMode ?
                    (is_array($request->type_filtersPaymentMode) ?
                        $request->type_filtersPaymentMode :
                        explode(',', $request->type_filtersPaymentMode)) :
                    [],
                'date_start' => $request->date_start,
                'date_end' => $request->date_end,
            ],
            'broadcastChannel' => 'invoices',
        ]);
    }

    public function validateInvoiceCashPayment(Request $request)
    {
        $request->validate(
            [
                'invoice_no' => ['required', 'string'],
                'receipt_date' => ['required', 'date', 'before_or_equal:today'],
                'transaction_date' => ['required', 'date', 'before_or_equal:today'],
                'customer_code' => ['required', 'string'],
                'name' => ['required', 'string'],
                'price_group' => ['required', 'string'],
                'payment_mode' => ['required', 'in:Account Receivables,Cash'],
                'chargeinvoice_type' => ['required', 'string'],
                'particular' => ['required', 'string'],
                'reference_no' => ['required', 'string'],
                'total_amount' => ['required', 'string'],
                'addVat' => 'nullable',
                'deductVat' => 'nullable',
                'freight' => 'nullable',
                'netTotal' => 'nullable',

                'invoices' => 'required|array',
                'invoices.*.item_code' => ['required_with:invoices', 'string'],
                'invoices.*.item_name' => ['required_with:invoices', 'string'],
                'invoices.*.packing' => ['required_with:invoices', 'string'],
                'invoices.*.quantity' => ['required_with:invoices', 'numeric'],
                'invoices.*.price' => ['required_with:invoices', 'numeric'],
                'invoices.*.amount' => ['required_with:invoices', 'string'],

            ],
            [
                'invoice_no.required' => 'Invoice Number Required',
                'receipt_date.required' => 'Date Required',
                'receipt_date.date' => 'Date Must Be Valid Date',
                'receipt_date.before_or_equal' => 'Date Cannot Be Advance',
                'transaction_date.required' => 'Date Required',
                'transaction_date.date' => 'Date Must Be Valid Date',
                'transaction_date.before_or_equal' => 'Date Cannot Be Advance',
                'customer_code.required' => 'Customer Code Required',
                'name.required' => 'Customer Name Required',
                'price_group.required' => 'Price Group Required',
                'payment_mode.required' => 'Payment Mode Required',
                'payment_mode.in' => 'Payment Mode Required',
                'chargeinvoice_type.required' => 'Charge Invoice Type Required',
                'particular.required' => 'Particular Required',
                'reference_no.required' => 'Reference Number Required',

                'invoices.required' => 'Please Add Item',
                'invoices.*.item_code.required_with' => 'Item Code Required',
                'invoices.*.item_name.required_with' => 'Item Name Required',
                'invoices.*.packing.required_with' => 'Packing Required',
                'invoices.*.quantity.required_with' => 'Quantity Required',
                'invoices.*.quantity.numeric' => 'Quantity Must Be Valid Number',
                'invoices.*.price.required_with' => 'Price Required',
                'invoices.*.price.numeric' => 'Price Must Be Valid Number',
                'invoices.*.amount.required_with' => 'Amount Required',
                'invoices.*.amount.numeric' => 'Amount Must Be Valid number',

                'total_amount.required' => 'Total Amount Required',
                'total_amount.numeric' => 'Total Amount Must Be Valid number',
            ]
        );
    }

    public function addInvoice(Request $request, InvoiceNumberService $invoiceNumberService)
    {
        $validated = $request->validate(
            [
                'invoice_no' => ['required', 'string'],
                'receipt_date' => ['required', 'date', 'before_or_equal:today'],
                'transaction_date' => ['required', 'date', 'before_or_equal:today'],
                'customer_code' => ['required', 'string'],
                'name' => ['required', 'string'],
                'price_group' => ['required', 'string'],
                'payment_mode' => ['required', 'in:Account Receivables,Cash'],
                'chargeinvoice_type' => ['required', 'string'],
                'particular' => ['required', 'string'],
                'reference_no' => ['required', 'string'],
                'total_amount' => ['required', 'string'],
                'payment_no' => ['nullable'],
                'added_vat' => ['nullable'],
                'deducted_vat' => ['nullable'],
                'freight' => ['nullable'],
                'net_total' => ['nullable'],

                'invoices' => 'required|array',
                'invoices.*.item_code' => ['required_with:invoices', 'string'],
                'invoices.*.item_name' => ['required_with:invoices', 'string'],
                'invoices.*.packing' => ['required_with:invoices', 'string'],
                'invoices.*.quantity' => ['required_with:invoices', 'numeric'],
                'invoices.*.price' => ['required_with:invoices', 'numeric'],
                'invoices.*.amount' => ['required_with:invoices', 'string'],

            ],
            [
                'invoice_no.required' => 'Invoice Number Required',
                'receipt_date.required' => 'Date Required',
                'receipt_date.date' => 'Date Must Be Valid Date',
                'receipt_date.before_or_equal' => 'Date Cannot Be Advance',
                'transaction_date.required' => 'Date Required',
                'transaction_date.date' => 'Date Must Be Valid Date',
                'transaction_date.before_or_equal' => 'Date Cannot Be Advance',
                'customer_code.required' => 'Customer Code Required',
                'name.required' => 'Customer Name Required',
                'price_group.required' => 'Price Group Required',
                'payment_mode.required' => 'Payment Mode Required',
                'payment_mode.in' => 'Payment Mode Required',
                'chargeinvoice_type.required' => 'Charge Invoice Type Required',
                'particular.required' => 'Particular Required',
                'reference_no.required' => 'Reference Number Required',

                'invoices.required' => 'Please Add Item',
                'invoices.*.item_code.required_with' => 'Item Code Required',
                'invoices.*.item_name.required_with' => 'Item Name Required',
                'invoices.*.packing.required_with' => 'Packing Required',
                'invoices.*.quantity.required_with' => 'Quantity Required',
                'invoices.*.quantity.numeric' => 'Quantity Must Be Valid Number',
                'invoices.*.price.required_with' => 'Price Required',
                'invoices.*.price.numeric' => 'Price Must Be Valid Number',
                'invoices.*.amount.required_with' => 'Amount Required',
                'invoices.*.amount.numeric' => 'Amount Must Be Valid number',

                'total_amount.required' => 'Total Amount Required',
                'total_amount.numeric' => 'Total Amount Must Be Valid number',
            ]
        );


        $invNo = DB::transaction(function () use ($validated, $request, $invoiceNumberService) {

            $invoiceNumber = $invoiceNumberService->generate();

            if (Invoice::where('invoice_no', $invoiceNumber)->exists()) {
                throw ValidationException::withMessages([
                    'general' => 'Error Please Try Again',
                ]);
            }

            $validated['total_amount'] = (float)preg_replace('/[^0-9.]/', '', $validated['total_amount']);
            $validated['added_vat'] = (float) preg_replace('/[^0-9.]/', '', $validated['added_vat'] ?? 0);
            $validated['deducted_vat'] = (float) preg_replace('/[^0-9.]/', '', $validated['deducted_vat'] ?? 0);
            $validated['freight'] = (float) preg_replace('/[^0-9.]/', '', $validated['freight'] ?? 0);
            $validated['net_total'] = (float) preg_replace('/[^0-9.]/', '', $validated['net_total'] ?? 0);

            $invoiceData = [
                'invoice_no' => $invoiceNumber,
                'payment_no' => $validated['payment_no'],
                'receipt_date' => $validated['receipt_date'],
                'transaction_date' => $validated['transaction_date'],
                'customer_code' => $validated['customer_code'],
                'name' => $validated['name'],
                'price_group' => $validated['price_group'],
                'payment_mode' => $validated['payment_mode'],
                'chargeinvoice_type' => $validated['chargeinvoice_type'],
                'particular' => $validated['particular'],
                'reference_no' => $validated['reference_no'],
                'total_amount' => $validated['total_amount'],
                'created_by' =>  $request->user()->name,
            ];
            if (Schema::connection('tenant')->hasColumn('invoice', 'added_vat')) {
                $invoiceData['added_vat'] = $validated['added_vat'];
            }
            if (Schema::connection('tenant')->hasColumn('invoice', 'deducted_vat')) {
                $invoiceData['deducted_vat'] = $validated['deducted_vat'];
            }
            if (Schema::connection('tenant')->hasColumn('invoice', 'freight')) {
                $invoiceData['freight'] = $validated['freight'];
            }
            if (Schema::connection('tenant')->hasColumn('invoice', 'net_total')) {
                $invoiceData['net_total'] = (!empty($validated['net_total'])) ? $validated['net_total'] : $validated['total_amount'];
            }
            Invoice::create($invoiceData);

            CustomerLedger::create([
                'invoice_number' => $invoiceNumber,
                'date' => $validated['receipt_date'],
                'type' => "Charge Invoice",
                'customer_code' => $validated['customer_code'],
                'customer_name' => $validated['name'],
                'currency' => "Php",
                'amount' => $validated['net_total'],
                'adjusted_amount' => 0.00,
                'amount_paid' => $validated['payment_mode'] === 'Cash'
                    ? $validated['net_total']
                    : 0.00,
                'running_balance' => $validated['payment_mode'] === 'Cash'
                    ? 0.00
                    : $validated['net_total'],
            ]);

            InvoiceItem::insert(array_map(function ($item) use ($invoiceNumber) {
                return [
                    'invoice_no' => $invoiceNumber,
                    'item_code' => $item['item_code'],
                    'item_name' => $item['item_name'],
                    'packing' => $item['packing'],
                    'quantity' => (float) $item['quantity'],
                    'price' => $item['price'],
                    'amount' => (float)preg_replace('/[^0-9.]/', '', $item['amount']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }, $validated['invoices']));

            if ($validated['payment_mode'] === 'Cash') {
                PaymentDetails::where('payment_no', $validated['payment_no'])
                    ->update(['document_no' => $invoiceNumber]);
                Payment::where('payment_no', $validated['payment_no'])
                    ->update(['document_no' => $invoiceNumber]);
            }


            return $invoiceNumber;
        });

        event(new NewCreated('invoice'));
        event(new NewCreated('customerledger'));

        session()->put('invoice_number', $invNo);
        return redirect()->back();
    }

    public function destroy($id)
    {
        $inv = Invoice::findOrFail($id);
        $inv->delete();
    }

    public function latest()
    {
        //return Invoice::orderByDesc('id')->value('invoice_no'); // returns "26000001" or null

        return DB::transaction(function () {
            $latestInvoice = Invoice::withTrashed()
                ->lockForUpdate() // Prevent concurrent access
                ->orderByDesc('invoice_no')
                ->first();

            $nextNumber = $latestInvoice ? $latestInvoice->invoice_no + 1 : 26000001;

            return response()->json([
                'next_invoice_no' => $nextNumber,
                'is_new_sequence' => !$latestInvoice
            ]);
        });
    }

    public function getInvoiceList(Request $request)
    {
        $customerCode = $request->input('type');
        $hasLedgerWhtAmount = Schema::connection('tenant')->hasColumn('customer_ledger', 'wht_amount');
        $hasLedgerOverpaymentAmount = Schema::connection('tenant')->hasColumn('customer_ledger', 'overpayment_amount');
        $hasPaymentDetailsWhtColumns = Schema::connection('tenant')->hasColumn('payment_details', 'wht_amount')
            && Schema::connection('tenant')->hasColumn('payment_details', 'wht_status');

        if (in_array($request->input('apply_to'), ['Sales Invoice', 'Other Income', 'Merchandise Transfer Out'])) {
            $applyTo = $request->input('apply_to');
            $ledgerType = match ($applyTo) {
                'Sales Invoice' => 'Sales Invoice',
                'Other Income' => 'Charge Invoice',
                'Merchandise Transfer Out' => 'Merchandise Transfer Out',
                default => 'Charge Invoice',
            };

            $ledgers = CustomerLedger::where('customer_code', $customerCode)
                ->where('type', $ledgerType)
                ->where('amount', '>', 0)
                ->get();

            $invoiceNumbers = $ledgers->pluck('invoice_number')->unique()->values();
            $paidAmounts = PaymentDetails::where('customer_code', $customerCode)
                ->whereIn('status', ['Paid', 'Cleared'])
                ->whereIn('document_no', $invoiceNumbers)
                ->selectRaw($this->paymentDetailsEffectivePaidSelect($hasPaymentDetailsWhtColumns))
                ->groupBy('document_no', 'type')
                ->get()
                ->groupBy(['document_no', 'type']);
            $grossAmounts = PaymentDetails::where('customer_code', $customerCode)
                ->whereIn('status', ['Floating', 'Paid', 'Cleared'])
                ->whereIn('document_no', $invoiceNumbers)
                ->selectRaw('document_no, type, SUM(COALESCE(amount_paid, 0)) as total_gross_paid')
                ->groupBy('document_no', 'type')
                ->get()
                ->groupBy(['document_no', 'type']);

            $whtAmounts = collect();
            if ($hasLedgerWhtAmount && Schema::connection('tenant')->hasColumn('payment_details', 'wht_amount')) {
                $whtQuery = PaymentDetails::where('customer_code', $customerCode)
                    ->whereIn('status', ['Paid', 'Cleared'])
                    ->whereIn('document_no', $invoiceNumbers)
                    ->where('wht_amount', '>', 0);

                if ($hasPaymentDetailsWhtColumns) {
                    $whtQuery->where(function ($q) {
                        $q->whereNull('wht_status')
                            ->orWhere('wht_status', 'Cleared');
                    });
                }

                $whtAmounts = $whtQuery
                    ->selectRaw('document_no, type, SUM(wht_amount) as total_wht')
                    ->groupBy('document_no', 'type')
                    ->get()
                    ->groupBy(['document_no', 'type']);
            }

            $result = $ledgers->map(function ($ledger) use ($customerCode, $applyTo, $paidAmounts, $grossAmounts, $whtAmounts, $hasLedgerWhtAmount, $hasLedgerOverpaymentAmount) {
                $existingPositive = Adjustment::where('invoice_no', $ledger->invoice_number)
                    ->where('apply_to', $applyTo)
                    ->where('type', 'Positive')
                    ->sum('amount');

                $existingNegative = Adjustment::where('invoice_no', $ledger->invoice_number)
                    ->where('apply_to', $applyTo)
                    ->where('type', 'Negative')
                    ->sum('amount');

                $shrinkage = $ledger->shrinkage ?? 0;
                $overage = $ledger->overage ?? 0;
                $returnAmount = $ledger->return ?? 0;

                $netAdjustment = $existingPositive - $existingNegative;
                $debit = ($ledger->amount ?? 0) + $netAdjustment - $shrinkage + $overage - $returnAmount;
                $paidRow = $paidAmounts
                    ->get($ledger->invoice_number, collect())
                    ->get($ledger->type, collect())
                    ->first();
                $paid = (float) ($paidRow->total_paid ?? $ledger->amount_paid ?? 0);
                $grossRow = $grossAmounts
                    ->get($ledger->invoice_number, collect())
                    ->get($ledger->type, collect())
                    ->first();
                $grossPaid = (float) ($grossRow->total_gross_paid ?? 0);
                $wht = 0.0;
                if ($hasLedgerWhtAmount) {
                    $whtRow = $whtAmounts
                        ->get($ledger->invoice_number, collect())
                        ->get($ledger->type, collect())
                        ->first();
                    $wht = (float) ($whtRow->total_wht ?? 0);
                }
                $totalCredited = $paid + $wht;
                $syncedRunningBalance = max(0, $debit - $totalCredited);
                $overpaymentAmount = max(0, $grossPaid - $debit);

                if (
                    $ledger->adjusted_amount != (($ledger->amount ?? 0) + $netAdjustment) ||
                    $ledger->positive_adjustment_amount != $existingPositive ||
                    $ledger->negative_adjustment_amount != $existingNegative ||
                    $ledger->amount_paid != $paid ||
                    $ledger->running_balance != $syncedRunningBalance ||
                    ($hasLedgerWhtAmount && (float) ($ledger->wht_amount ?? 0) !== $wht) ||
                    ($hasLedgerOverpaymentAmount && (float) ($ledger->overpayment_amount ?? 0) !== $overpaymentAmount)
                ) {
                    $ledgerUpdate = [
                        'adjusted_amount' => ($ledger->amount ?? 0) + $netAdjustment,
                        'positive_adjustment_amount' => $existingPositive,
                        'negative_adjustment_amount' => $existingNegative,
                        'amount_paid' => $paid,
                        'running_balance' => $syncedRunningBalance,
                    ];
                    if ($hasLedgerWhtAmount) {
                        $ledgerUpdate['wht_amount'] = $wht;
                    }
                    if ($hasLedgerOverpaymentAmount) {
                        $ledgerUpdate['overpayment_amount'] = $overpaymentAmount;
                    }
                    $ledger->update($ledgerUpdate);
                }

                $this->syncPaymentDetailOverpayment(
                    $customerCode,
                    $ledger->invoice_number,
                    $ledger->type,
                    $debit
                );

                return [
                    'invoice_no' => $ledger->invoice_number,
                    'receipt_date' => $ledger->date,
                    'type' => $ledger->type,
                    'amount' => $ledger->amount,
                    'amount_paid' => $paid,
                    'running_balance' => $syncedRunningBalance,
                ];
            })->filter(fn ($row) => ($row['running_balance'] ?? 0) > 0)->values();
        } else {
            $ledgers = CustomerLedger::where('customer_code', $customerCode)
                ->where('type', 'BG')
                ->where('running_balance', '>', 0)
                ->get();

            $invoiceNumbers = $ledgers->pluck('invoice_number')->unique()->values();
            $paidAmounts = PaymentDetails::where('customer_code', $customerCode)
                ->whereIn('status', ['Paid', 'Cleared'])
                ->whereIn('document_no', $invoiceNumbers)
                ->selectRaw($this->paymentDetailsEffectivePaidSelect($hasPaymentDetailsWhtColumns))
                ->groupBy('document_no', 'type')
                ->get()
                ->groupBy(['document_no', 'type']);
            $grossAmounts = PaymentDetails::where('customer_code', $customerCode)
                ->whereIn('status', ['Floating', 'Paid', 'Cleared'])
                ->whereIn('document_no', $invoiceNumbers)
                ->selectRaw('document_no, type, SUM(COALESCE(amount_paid, 0)) as total_gross_paid')
                ->groupBy('document_no', 'type')
                ->get()
                ->groupBy(['document_no', 'type']);

            $whtAmounts = collect();
            if ($hasLedgerWhtAmount && Schema::connection('tenant')->hasColumn('payment_details', 'wht_amount')) {
                $whtQuery = PaymentDetails::where('customer_code', $customerCode)
                    ->whereIn('status', ['Paid', 'Cleared'])
                    ->whereIn('document_no', $invoiceNumbers)
                    ->where('wht_amount', '>', 0);

                if ($hasPaymentDetailsWhtColumns) {
                    $whtQuery->where(function ($q) {
                        $q->whereNull('wht_status')
                            ->orWhere('wht_status', 'Cleared');
                    });
                }

                $whtAmounts = $whtQuery
                    ->selectRaw('document_no, type, SUM(wht_amount) as total_wht')
                    ->groupBy('document_no', 'type')
                    ->get()
                    ->groupBy(['document_no', 'type']);
            }

            $result = $ledgers->map(function ($ledger) use ($customerCode, $paidAmounts, $grossAmounts, $whtAmounts, $hasLedgerWhtAmount, $hasLedgerOverpaymentAmount) {
                $beginningBalance = BeginningBalance::where('beginningbalance_no', $ledger->invoice_number)->first();

                if ($beginningBalance && $ledger->amount != $beginningBalance->balance_amount) {
                    $ledger->update(['amount' => $beginningBalance->balance_amount]);
                    $ledger->refresh();
                }

                $existingPositive = Adjustment::where('invoice_no', $ledger->invoice_number)
                    ->where('apply_to', 'Beginning Balance')
                    ->where('type', 'Positive')
                    ->sum('amount');

                $existingNegative = Adjustment::where('invoice_no', $ledger->invoice_number)
                    ->where('apply_to', 'Beginning Balance')
                    ->where('type', 'Negative')
                    ->sum('amount');

                $amount = $ledger->amount;
                $currentAdjusted = $amount + $existingPositive - $existingNegative;
                $paidRow = $paidAmounts
                    ->get($ledger->invoice_number, collect())
                    ->get($ledger->type, collect())
                    ->first();
                $paid = (float) ($paidRow->total_paid ?? $ledger->amount_paid ?? 0);
                $grossRow = $grossAmounts
                    ->get($ledger->invoice_number, collect())
                    ->get($ledger->type, collect())
                    ->first();
                $grossPaid = (float) ($grossRow->total_gross_paid ?? 0);
                $wht = 0.0;
                if ($hasLedgerWhtAmount) {
                    $whtRow = $whtAmounts
                        ->get($ledger->invoice_number, collect())
                        ->get($ledger->type, collect())
                        ->first();
                    $wht = (float) ($whtRow->total_wht ?? 0);
                }
                $totalCredited = $paid + $wht;
                $syncedRunningBalance = max(0, $currentAdjusted - $totalCredited);
                $overpaymentAmount = max(0, $grossPaid - $currentAdjusted);

                if (
                    $ledger->adjusted_amount != $currentAdjusted ||
                    $ledger->amount_paid != $paid ||
                    $ledger->running_balance != $syncedRunningBalance ||
                    ($hasLedgerWhtAmount && (float) ($ledger->wht_amount ?? 0) !== $wht) ||
                    ($hasLedgerOverpaymentAmount && (float) ($ledger->overpayment_amount ?? 0) !== $overpaymentAmount)
                ) {
                    $ledgerUpdate = [
                        'adjusted_amount' => $currentAdjusted,
                        'positive_adjustment_amount' => $existingPositive,
                        'negative_adjustment_amount' => $existingNegative,
                        'amount_paid' => $paid,
                        'running_balance' => $syncedRunningBalance
                    ];
                    if ($hasLedgerWhtAmount) {
                        $ledgerUpdate['wht_amount'] = $wht;
                    }
                    if ($hasLedgerOverpaymentAmount) {
                        $ledgerUpdate['overpayment_amount'] = $overpaymentAmount;
                    }
                    $ledger->update($ledgerUpdate);
                }

                $this->syncPaymentDetailOverpayment(
                    $customerCode,
                    $ledger->invoice_number,
                    $ledger->type,
                    $currentAdjusted
                );

                return [
                    'invoice_no' => $ledger->invoice_number,
                    'receipt_date' => $ledger->date,
                    'type' => 'Beginning Balance',
                    'amount' => $syncedRunningBalance,
                    'amount_paid' => $paid,
                    'running_balance' => $syncedRunningBalance,
                ];
            });
        }

        return response()->json($result);
    }


    public function getInvoiceClearedList(Request $request)
    {
        $customerCode = $request->input('type');

        // Now get all ledger entries for these invoice numbers
        $ledgers = CustomerLedger::where('customer_code', $customerCode)
            ->onlyTrashed()
            ->select('invoice_number', 'date', 'type', 'amount', 'amount_paid', 'running_balance')
            ->get();

        // Format the results
        $result = $ledgers->map(function ($ledger) {
            return [
                'invoice_no' => $ledger->invoice_number,
                'receipt_date' => $ledger->date,
                'type' => $ledger->type,
                'amount' => $ledger->amount,
                'amount_paid' => $ledger->amount_paid,
                'running_balance' => $ledger->running_balance
            ];
        });

        return response()->json($result);
    }

    public function getCustomerBegBalList(Request $request)
    {
        $date = $request->input('date');

        // Validate date input
        if (!$date) {
            return response()->json(['error' => 'Date parameter is required'], 400);
        }

        // Get the latest ledger entries for each customer on or before the specified date
        $customerBalances = CustomerLedger::select(
            'customer_code',
            DB::raw('MAX(customers.cus_name) as customer_name'),
            DB::raw('SUM(CASE WHEN type != "BG" THEN running_balance ELSE 0 END) as total_balance')
        )
            ->join('customers', 'customer_ledger.customer_code', '=', 'customers.cus_code')
            ->where('date', '<=', $date)
            ->groupBy('customer_code')
            ->get();

        // Format the results
        $result = $customerBalances->map(function ($item) {
            return [
                'customer_code' => $item->customer_code,
                'customer_name' => $item->customer_name,
                'total_balance' => $item->total_balance
            ];
        });

        return response()->json($result);
    }

    public function getInvoiceListForPayment(Request $request)
    {
        $customerCode = $request->input('customer_code');
        $date = $request->input('date');
        $hasLedgerWhtAmount = Schema::connection('tenant')->hasColumn('customer_ledger', 'wht_amount');
        $hasLedgerOverpaymentAmount = Schema::connection('tenant')->hasColumn('customer_ledger', 'overpayment_amount');
        $hasPaymentDetailsWhtColumns = Schema::connection('tenant')->hasColumn('payment_details', 'wht_amount')
            && Schema::connection('tenant')->hasColumn('payment_details', 'wht_status');

        Log::info('getInvoiceListForPayment diagnostics', [
            'tenant_slug' => $request->route('tenant'),
            'database_default' => config('database.default'),
            'tenant_configured' => config()->has('database.connections.tenant'),
            'tenant_database' => config('database.connections.tenant.database') ?? null,
            'customer_code' => $customerCode,
            'date' => $date,
        ]);

        if ($customerCode && $date) {
            $ledgersToRecompute = CustomerLedger::on('tenant')
                ->where('customer_code', $customerCode)
                ->where('date', '<=', $date)
                ->whereIn('type', ['Sales Invoice', 'Charge Invoice', 'Merchandise Transfer Out', 'BG', 'Beginning Balance', 'Payment'])
                ->get([
                    'id',
                    'invoice_number',
                    'type',
                    'amount',
                    'amount_paid',
                    'adjusted_amount',
                    'positive_adjustment_amount',
                    'negative_adjustment_amount',
                    'running_balance',
                    'shrinkage',
                    'overage',
                    'return',
                    ...($hasLedgerWhtAmount ? ['wht_amount'] : []),
                    ...($hasLedgerOverpaymentAmount ? ['overpayment_amount'] : []),
                ]);

            if ($ledgersToRecompute->isNotEmpty()) {
                $invoiceNumbers = $ledgersToRecompute->pluck('invoice_number')->unique()->values();

                $beginningBalances = BeginningBalance::on('tenant')
                    ->whereIn('beginningbalance_no', $invoiceNumbers)
                    ->get(['beginningbalance_no', 'balance_amount'])
                    ->keyBy('beginningbalance_no');

                $adjustmentsPosNeg = Adjustment::on('tenant')
                    ->whereIn('invoice_no', $invoiceNumbers)
                    ->selectRaw("
                        invoice_no,
                        apply_to,
                        SUM(CASE WHEN type='Positive' THEN amount ELSE 0 END) as pos_sum,
                        SUM(CASE WHEN type='Negative' THEN amount ELSE 0 END) as neg_sum
                    ")
                    ->groupBy('invoice_no', 'apply_to')
                    ->get()
                    ->groupBy(['invoice_no', 'apply_to']);

                $paidAmounts = PaymentDetails::on('tenant')
                    ->where('customer_code', $customerCode)
                    ->whereIn('status', ['Paid', 'Cleared'])
                    ->whereIn('document_no', $invoiceNumbers)
                    ->selectRaw($this->paymentDetailsEffectivePaidSelect($hasPaymentDetailsWhtColumns))
                    ->groupBy('document_no', 'type')
                    ->get()
                    ->groupBy(['document_no', 'type']);
                $grossAmounts = PaymentDetails::on('tenant')
                    ->where('customer_code', $customerCode)
                    ->whereIn('status', ['Floating', 'Paid', 'Cleared'])
                    ->whereIn('document_no', $invoiceNumbers)
                    ->selectRaw('document_no, type, SUM(COALESCE(amount_paid, 0)) as total_gross_paid')
                    ->groupBy('document_no', 'type')
                    ->get()
                    ->groupBy(['document_no', 'type']);

                $whtAmounts = collect();
                if ($hasLedgerWhtAmount && Schema::connection('tenant')->hasColumn('payment_details', 'wht_amount')) {
                    $whtAmountsQuery = PaymentDetails::on('tenant')
                        ->where('customer_code', $customerCode)
                        ->whereIn('status', ['Paid', 'Cleared'])
                        ->whereIn('document_no', $invoiceNumbers)
                        ->where('wht_amount', '>', 0);

                    if ($hasPaymentDetailsWhtColumns) {
                        $whtAmountsQuery->where(function ($q) {
                            $q->whereNull('wht_status')
                                ->orWhere('wht_status', 'Cleared');
                        });
                    }

                    $whtAmounts = $whtAmountsQuery
                        ->selectRaw('document_no, type, SUM(wht_amount) as total_wht')
                        ->groupBy('document_no', 'type')
                        ->get()
                        ->groupBy(['document_no', 'type']);
                }

                foreach ($ledgersToRecompute as $ledger) {
                    $applyTo = $ledger->type;
                    if ($ledger->type === 'Charge Invoice') {
                        $applyTo = 'Other Income';
                    } elseif ($ledger->type === 'Merchandise Transfer Out') {
                        $applyTo = 'Merchandise Transfer Out';
                    } elseif ($ledger->type === 'BG' || $ledger->type === 'Beginning Balance') {
                        $applyTo = 'Beginning Balance';
                    }

                    $amount = (float) ($ledger->amount ?? 0);
                    if ($ledger->type === 'BG' || $ledger->type === 'Beginning Balance') {
                        $beginRow = $beginningBalances->get($ledger->invoice_number);
                        if ($beginRow) {
                            $amount = (float) $beginRow->balance_amount;
                        }
                    }

                    $posNeg = $adjustmentsPosNeg
                        ->get($ledger->invoice_number, collect())
                        ->get($applyTo, collect())
                        ->first();

                    $posSum = (float) ($posNeg->pos_sum ?? 0);
                    $negSum = (float) ($posNeg->neg_sum ?? 0);
                    $netAdjustment = $posSum - $negSum;

                    $shrinkage = (float) ($ledger->shrinkage ?? 0);
                    $overage = (float) ($ledger->overage ?? 0);
                    $returnAmount = (float) ($ledger->return ?? 0);

                    $debit = $amount + $netAdjustment - $shrinkage + $overage - $returnAmount;
                    $adjustedAmount = $amount + $netAdjustment;

                    $paidRow = $paidAmounts
                        ->get($ledger->invoice_number, collect())
                        ->get($ledger->type, collect())
                        ->first();
                    $paid = (float) ($paidRow->total_paid ?? $ledger->amount_paid ?? 0);
                    $grossRow = $grossAmounts
                        ->get($ledger->invoice_number, collect())
                        ->get($ledger->type, collect())
                        ->first();
                    $grossPaid = (float) ($grossRow->total_gross_paid ?? 0);
                    $wht = 0.0;
                    if ($hasLedgerWhtAmount) {
                        $whtRow = $whtAmounts
                            ->get($ledger->invoice_number, collect())
                            ->get($ledger->type, collect())
                            ->first();
                        $wht = (float) ($whtRow->total_wht ?? 0);
                    }

                    $totalCredited = $paid + $wht;
                    $runningBalance = max(0, $debit - $totalCredited);
                    $overpaymentAmount = max(0, $grossPaid - $debit);

                    if (
                        $ledger->amount != $amount ||
                        $ledger->amount_paid != $paid ||
                        $ledger->adjusted_amount != $adjustedAmount ||
                        $ledger->positive_adjustment_amount != $posSum ||
                        $ledger->negative_adjustment_amount != $negSum ||
                        $ledger->running_balance != $runningBalance ||
                        ($hasLedgerWhtAmount && (float) ($ledger->wht_amount ?? 0) !== $wht) ||
                        ($hasLedgerOverpaymentAmount && (float) ($ledger->overpayment_amount ?? 0) !== $overpaymentAmount)
                    ) {
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

                        CustomerLedger::on('tenant')->where('id', $ledger->id)->update($ledgerUpdate);
                    }

                    $this->syncPaymentDetailOverpayment(
                        $customerCode,
                        $ledger->invoice_number,
                        $ledger->type,
                        $debit
                    );
                }
            }
        }

        // Get all ledger entries for the customer
        $columns = ['invoice_number', 'date', 'type', 'amount', 'amount_paid', 'running_balance', 'trade_type'];
        if ($hasLedgerWhtAmount) {
            $columns[] = 'wht_amount';
        }
        $ledgers = CustomerLedger::on('tenant')->where('customer_code', $customerCode)
            ->select($columns)
            ->where('date', '<=', $date)
            ->orderBy('date')
            ->orderBy('created_at')
            ->get();

        $paymentDetailColumns = ['document_no', 'type', 'status', 'payment_type', 'check_type', 'amount_paid'];
        if ($hasPaymentDetailsWhtColumns) {
            $paymentDetailColumns[] = 'wht_amount';
            $paymentDetailColumns[] = 'wht_status';
        }

        $paymentDetailsByDocument = PaymentDetails::on('tenant')
            ->where('customer_code', $customerCode)
            ->whereIn('document_no', $ledgers->pluck('invoice_number')->filter()->unique()->values())
            ->get($paymentDetailColumns)
            ->groupBy(['document_no', 'type']);

        // Format the results and check payment details
        $result = $ledgers->map(function ($ledger) use ($paymentDetailsByDocument, $hasPaymentDetailsWhtColumns, $hasLedgerWhtAmount) {
            // Initialize floating amount
            $pdcFloatingAmount = 0;
            $dcFloatingAmount = 0;
            $whtFloatingAmount = 0;
            $hasWhtTransaction = false;
            $whtTransactionCount = 0;

            $paymentDetails = $paymentDetailsByDocument
                ->get($ledger->invoice_number, collect())
                ->get($ledger->type, collect());

            // If payment details exist, check status and calculate floating amount
            if ($paymentDetails->isNotEmpty()) {
                $floatingPayments = $paymentDetails->where('status', 'Floating');
                if ($floatingPayments->isNotEmpty()) {
                    $pdcFloatingAmount = $floatingPayments->where('payment_type', 'Check')
                        ->where('check_type', 'Post Dated Check')
                        ->sum('amount_paid');
                    $dcFloatingAmount = $floatingPayments->where('payment_type', 'Check')
                        ->where('check_type', 'Dated Check')
                        ->sum('amount_paid');
                }

                if ($hasPaymentDetailsWhtColumns) {
                    $whtFloatingAmount = $paymentDetails
                        ->filter(function ($pd) {
                            return ($pd->wht_status ?? null) === 'Floating'
                                && ($pd->status ?? null) !== 'Floating';
                        })
                        ->sum('wht_amount');
                }

                $whtTransactions = $paymentDetails
                    ->filter(function ($pd) use ($hasPaymentDetailsWhtColumns) {
                        if (($pd->status ?? null) === 'Cancelled') {
                            return false;
                        }
                        if ((float) ($pd->wht_amount ?? 0) <= 0) {
                            return false;
                        }
                        if ($hasPaymentDetailsWhtColumns && ($pd->wht_status ?? null) === 'Cancelled') {
                            return false;
                        }
                        return true;
                    });

                $whtTransactionCount = $whtTransactions->count();
                $hasWhtTransaction = $whtTransactionCount > 0;
            }

            return [
                'invoice_no' => $ledger->invoice_number,
                'receipt_date' => $ledger->date,
                'type' => $ledger->type,
                'amount' => $ledger->amount,
                'amount_paid' => $ledger->amount_paid,
                'applied_wht_amount' => $hasLedgerWhtAmount ? (float) ($ledger->wht_amount ?? 0) : 0.0,
                'running_balance' => $ledger->running_balance,
                'trade_type' => $ledger->trade_type ?? null,
                'pdc_floating_amount' => $pdcFloatingAmount,
                'has_pdc_floating_payments' => $pdcFloatingAmount > 0,
                'dc_floating_amount' => $dcFloatingAmount,
                'has_dc_floating_payments' => $dcFloatingAmount > 0,
                'wht_floating_amount' => $whtFloatingAmount,
                'has_wht_floating_payments' => $whtFloatingAmount > 0,
                'has_wht_transaction' => $hasWhtTransaction,
                'wht_transaction_count' => $whtTransactionCount,
            ];
        });

        return response()->json($result);
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
}
