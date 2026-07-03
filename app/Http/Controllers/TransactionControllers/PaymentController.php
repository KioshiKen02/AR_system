<?php

namespace App\Http\Controllers\TransactionControllers;

use App\Events\NewCreated;
use App\Events\NotificationEvent;
use App\Http\Controllers\Controller;
use App\Http\Controllers\NotificationsController;
use App\Models\MasterfileModels\Customer;
use App\Models\MasterfileModels\User;
use App\Models\ReportModels\CustomerLedger;
use App\Models\TransactionModels\Invoice;
use App\Models\TransactionModels\Payment;
use App\Models\TransactionModels\PaymentDetails;
use App\Models\AppSetting;
use App\Services\CustomerService;
use App\Services\InvoiceNumberService;
use App\Services\InvoiceService;
use App\Services\PaymentNumberService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    private const WHT_SUBJECT_PAYMENT_TYPES = [
        '5A - Cash',
        '5B - Journal Voucher',
        '5C - Online Deposit',
        '5D - Check',
    ];

    private function isPaymentTypeSubjectToWht(string $paymentType): bool
    {
        return in_array($paymentType, self::WHT_SUBJECT_PAYMENT_TYPES, true);
    }

    private function isSingleUseWhtDocumentType(string $documentType): bool
    {
        return in_array($documentType, ['Sales Invoice', 'Charge Invoice'], true);
    }

    private function ensureDocumentAllowsAdditionalWht(string $customerCode, string $documentNo, string $documentType): void
    {
        if (!$this->isSingleUseWhtDocumentType($documentType)) {
            return;
        }

        $existing = PaymentDetails::where('customer_code', $customerCode)
            ->where('document_no', $documentNo)
            ->where('type', $documentType)
            ->where('status', '!=', 'Cancelled');

        if (Schema::connection('tenant')->hasColumn('payment_details', 'wht_amount')) {
            $existing->where('wht_amount', '>', 0);
        } else {
            return;
        }

        if (Schema::connection('tenant')->hasColumn('payment_details', 'wht_status')) {
            $existing->where(function ($query) {
                $query->whereNull('wht_status')
                    ->orWhere('wht_status', '!=', 'Cancelled');
            });
        }

        if ($existing->exists()) {
            throw ValidationException::withMessages([
                'document_no' => 'WHT can only be applied once for Sales/Charge Invoice. Document: ' . $documentNo,
            ]);
        }
    }

    private function resolveWhtStatus(float $whtAmount, bool $applyBir2307): ?string
    {
        if ($whtAmount <= 0) {
            return null;
        }

        return $applyBir2307 ? 'Cleared' : 'Floating';
    }

    private function normalizeSelectedDocumentsForWht(array $validated): array
    {
        if (empty($validated['selectedDocuments']) || !is_array($validated['selectedDocuments'])) {
            return $validated;
        }

        if ($this->isPaymentTypeSubjectToWht($validated['payment_type'] ?? '')) {
            return $validated;
        }

        foreach ($validated['selectedDocuments'] as &$doc) {
            $oldWht = isset($doc['wht_amount']) ? floatval($doc['wht_amount']) : 0.0;
            $amountToPay = isset($doc['amountToPay']) ? floatval($doc['amountToPay']) : 0.0;

            if ($oldWht > 0) {
                $amountToPay += $oldWht;
            }

            $doc['wht_amount'] = 0.0;
            $doc['amountToPay'] = $amountToPay;
            $doc['total_amount_less_wht'] = $amountToPay;
        }
        unset($doc);

        return $validated;
    }
    protected $cashPaymentFromInvoiceDirect = false;

    public function index(Request $request)
    {
        $query = Payment::query()->with(['paymentDetails:id,payment_no,status']);

        // Search functionality
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('payment_no', 'like', '%' . $request->search . '%');
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
        if ($request->type_filtersPaymentType) {
            $types = is_array($request->type_filtersPaymentType)
                ? $request->type_filtersPaymentType
                : explode(',', $request->type_filtersPaymentType);
            $query->whereIn('payment_type', $types);
        }

        // Code sorting
        if ($request->code_sort) {
            $query->orderBy('payment_no', $request->code_sort === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('payment_no', 'desc');
        }

        $payments = $query->paginate(10)->withQueryString();
        $payments->setCollection(
            $payments->getCollection()->map(function ($payment) {
                $payment->status = $this->resolvePaymentStatus($payment);
                $payment->unsetRelation('paymentDetails');

                return $payment;
            })
        );

        return Inertia::render('Payment', [
            'payments' => $payments,
            'searchTerm' => $request->search,
            'filters' => [
                'code_sort' => $request->code_sort,
                'type_filters' => $request->type_filters ?
                    (is_array($request->type_filters) ?
                        $request->type_filters :
                        explode(',', $request->type_filters)) :
                    [],
                'type_filtersPaymentType' => $request->type_filtersPaymentType ?
                    (is_array($request->type_filtersPaymentType) ?
                        $request->type_filtersPaymentType :
                        explode(',', $request->type_filtersPaymentType)) :
                    [],
                'date_start' => $request->date_start,
                'date_end' => $request->date_end,
            ],
            'broadcastChannel' => 'payments',
        ]);
    }

    private function resolvePaymentStatus(Payment $payment): string
    {
        $statuses = $payment->paymentDetails
            ->pluck('status')
            ->filter()
            ->unique()
            ->values();

        if ($statuses->contains('Floating')) {
            return 'Floating';
        }

        if ($statuses->contains('Cleared')) {
            return 'Cleared';
        }

        if ($statuses->contains('Paid')) {
            return 'Paid';
        }

        return $statuses->first() ?? 'N/A';
    }

    public function addPayment(Request $request, PaymentNumberService $paymentNumberService, InvoiceNumberService $invoiceNumberService)
    {
        $odConfirmed = $request->input('_od_confirmation', false);
        $checkConfirmed = $request->input('_check_confirmation', false);
        $cashConfirmed = $request->input('_cash_confirmation', false);
        $cl_type = $request->input('_cl_type', '');

        $customMessages = [
            // Base rules messages
            'payment_no.required' => 'Payment Number Required',
            'receipt_date.required' => 'Receipt Date Required',
            'receipt_date.before_or_equal' => 'Receipt Date Cannot Be Advance',
            'transaction_date.required' => 'Transaction Date Required',
            'transaction_date.before_or_equal' => 'Transaction Date Cannot Be Advance',
            'customer_code.required' => 'Customer Code Required',
            'name.required' => 'Customer Name Required',
            'payment_type.required' => 'Payment Type Required',
            'type.required' => 'Transaction Type Required',
            'document_no.required' => 'Document Number Required',
            'document_date.required' => 'Document Date Required',
            'total_amount.required' => 'Total Amount Required',
            'net_total.required' => 'Net Total Amount Required',
            'amount_paid.required' => 'Amount Paid Required',

            // Payment type specific messages
            'ds_no.required' => 'DS Number Required',
            'cash_in_bank.required' => 'Cash in Bank Required',
            'reference_no.required' => 'Reference Number Required',
            'acc_code.required' => 'Account Code Required',
            'cust_code.required' => 'Customer Code Required',
            'check_type.required' => 'Check Type Required',
            'aging_basis.required' => 'Aging Basis Required',
            'aging_days.required' => 'Aging Days Required',
            'acc_name_address.required' => 'Account Name/Address Required',
            'referral_name.required' => 'Referral Name Required',
            'acc_number.required' => 'Account Number Required',
            'due_date.required' => 'Due Date Required',
            'withBIR.required' => 'With BIR Status Required',
            'witholdingtax.required' => 'Withholding Tax Information Required',
        ];

        // Common validation rules for all payment types
        $baseRules = [
            'payment_no' => ['required', 'string'],
            'receipt_date' => ['required', 'date', 'before_or_equal:today'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'customer_code' => ['required', 'string'],
            'name' => ['required', 'string'],
            'payment_type' => ['required', 'in:5A - Cash,5B - Journal Voucher,5C - Online Deposit,5D - Check'],
            'type' => ['required'],
            'document_no' => ['required', 'string'],
            'document_date' => ['required', 'date'],
            'advanced_payment_balance' => ['nullable', 'string'],
            'total_amount' => ['nullable', 'string'],
            'net_total' => ['nullable', 'string'],
            'amount_paid' => [
                'required',
                'numeric',
                'between:0,999999999999.99',
            ],
            'selectedDocuments' => ['nullable', 'array'],
        ];

        // Payment type specific rules
        $typeSpecificRules = [
            '5A - Cash' => [
                'ds_no' => [
                    function ($attribute, $value, $fail) use ($cashConfirmed) {
                        if (!$cashConfirmed) {
                            if (empty($value) || $value === 'DS#') {
                                $fail('DS Number is required');
                            }
                        }
                    },
                    'string',
                ],
                'cash_in_bank' => 'required|string',
            ],
            '5B - Journal Voucher' => [
                'reference_no' => [
                    'required',
                    'string',
                    function ($attribute, $value, $fail) {
                        if ($value === 'JV#') {
                            $fail('Reference Number is required');
                        }
                    },
                ],
                'acc_code' => [
                    'nullable',
                    'string',
                    'required_without:cust_code',
                ],
                'cust_code' => [
                    'nullable',
                    'string',
                    'required_without:acc_code',
                ],
            ],
            '5C - Online Deposit' => $odConfirmed ? [
                'ds_no' => [
                    'required',
                    'string',
                    function ($attribute, $value, $fail) {
                        if ($value === 'DS#') {
                            $fail('DS Number is required');
                        }
                    },
                ],
                'acc_code' => [
                    'nullable',
                    'string',
                    'required_without:cust_code',
                ],
                'cust_code' => [
                    'nullable',
                    'string',
                    'required_without:acc_code',
                ],
            ] : [
                'ds_no' => [
                    'required',
                    'string',
                    function ($attribute, $value, $fail) {
                        if ($value === 'DS#') {
                            $fail('DS Number is required');
                        }
                    },
                ],
                'cash_in_bank' => 'required|string',
            ],
            '5D - Check' => $checkConfirmed ? [
                'ds_no' => ['nullable', 'string'],
                'reference_no' => [
                    'required',
                    'string',
                    function ($attribute, $value, $fail) {
                        if ($value === 'CHK#') {
                            $fail('Reference Number is required');
                        }
                    },
                ],
                'cust_code' => 'required|string',
                'check_type' => 'required|string',
                'aging_basis' => $request->check_type === 'Dated Check' ? 'nullable' : 'required|string',
                'aging_days' => $request->check_type === 'Dated Check' ? 'nullable' : 'required|integer|min:0',
                'acc_name_address' => 'required|string',
                'referral_name' => 'required|string',
                'acc_number' => 'required|string',
                'due_date' => 'required|date',
            ] : [
                'ds_no' => ['nullable', 'string'],
                'reference_no' => [
                    'required',
                    'string',
                    function ($attribute, $value, $fail) {
                        if ($value === 'CHK#') {
                            $fail('Reference Number is required');
                        }
                    },
                ],
                'cash_in_bank' => 'required|string',
                'check_type' => 'required|string',
                'aging_basis' => $request->check_type === 'Dated Check' ? 'nullable' : 'required|string',
                'aging_days' => $request->check_type === 'Dated Check' ? 'nullable' : 'required|integer|min:0',
                'acc_name_address' => 'required|string',
                'referral_name' => 'required|string',
                'acc_number' => 'required|string',
                'due_date' => 'required|date',
            ],
        ];

        // Merge base rules with payment type specific rules
        $validationRules = array_merge(
            $baseRules,
            $typeSpecificRules[$request->payment_type] ?? []
        );

        $validationRules['apply_bir_2307'] = ['nullable', 'boolean'];
        $validationRules['tax_rate'] = ['nullable', 'string'];

        $validated = $request->validate($validationRules, $customMessages);

        $validated = $this->normalizeSelectedDocumentsForWht($validated);

        $validated['total_amount'] = (float) preg_replace('/[^0-9.]/', '', $validated['total_amount']);
        $validated['net_total'] = isset($validated['net_total'])
            ? (float) preg_replace('/[^0-9.]/', '', $validated['net_total'])
            : $validated['total_amount'];

        $whtAmount = !empty($validated['wht_amount'])
            ? (float) preg_replace('/[^0-9.]/', '', $validated['wht_amount'])
            : 0;

        $totalAmountLessWht = !empty($validated['total_amount_less_wht'])
            ? (float) preg_replace('/[^0-9.]/', '', $validated['total_amount_less_wht'])
            : 0;

        if (!$this->isPaymentTypeSubjectToWht($validated['payment_type'])) {
            $whtAmount = 0;
            $totalAmountLessWht = 0;
        }

        $amountApplied = $whtAmount > 0
            ? $totalAmountLessWht
            : $validated['net_total'];

        $this->validateSubmittedPaymentBalances($validated);

        $notificationsController = new NotificationsController();

        // Payment Process
        switch ($validated['payment_type']) {
            case '5A - Cash':
                if ($cashConfirmed) {
                    $processedDocuments = [];
                    $validated['advanced_payment_balance'] = 0;
                    $processedDocuments[] = [
                        'document_no' => $validated['document_no'],
                        'type' => $validated['type'],
                        'amount' => $validated['net_total'],
                        'balance' => 0,
                        'amount_applied' => $amountApplied,
                        'wht_amount' => $whtAmount,
                        'total_amount_less_wht' => $totalAmountLessWht,
                        'document_date' => $validated['document_date'],
                        'overage_shortage' => 0
                    ];
                    $validated['selectedDocuments'] = $processedDocuments;
                    // this will be trigger if the payment is from invoice module cash payment type
                    $pyNo = $this->createPaymentRecords($validated, $request, 'Paid', $paymentNumberService, 'Cash Confirmed', $processedDocuments);
                } else {
                    // this will be trigger if the payment is from payment module 
                    $pyNo = $this->processPayment($validated, $request, 'Paid', $cl_type, $paymentNumberService);
                }

                break;

            case '5B - Journal Voucher':
                $pyNo = DB::transaction(function () use ($validated, $request, $cl_type, $invoiceNumberService, $paymentNumberService) {
                    $pynum = $this->processPayment($validated, $request, 'Paid', $cl_type, $paymentNumberService);
                    if (!empty($validated['cust_code'] ?? null)) {
                        $this->createArRecords($validated, $request, $invoiceNumberService);
                    }
                    return $pynum;
                });
                break;

            case '5C - Online Deposit':
                if ($odConfirmed) {
                    $pyNo = DB::transaction(function () use ($validated, $request, $cl_type, $invoiceNumberService, $paymentNumberService) {
                        $pynum = $this->processPayment($validated, $request, 'Paid', $cl_type, $paymentNumberService);
                        if (!empty($validated['cust_code'] ?? null)) {
                            $this->createArRecords($validated, $request, $invoiceNumberService);
                        }
                        return $pynum;
                    });
                } else {
                    $pyNo = $this->processPayment($validated, $request, 'Paid', $cl_type, $paymentNumberService);
                }
                break;

            case '5D - Check':
                if ($checkConfirmed) {
                    $pyNo = DB::transaction(function () use ($validated, $request, $invoiceNumberService, $paymentNumberService) {
                        $pynum = $this->createDirectPaymentRecords($validated, $request, $paymentNumberService);
                        $this->createArRecords($validated, $request, $invoiceNumberService);
                        return $pynum;
                    });
                } else {
                    $pyNo = $this->createDirectPaymentRecords($validated, $request, $paymentNumberService);
                }
                $notificationsController->index($request);

                $userIds = User::whereIn('role', ['Admin', 'Accounting'])
                    ->pluck('id')
                    ->unique();

                foreach ($userIds as $userId) {
                    $channel = 'notification-update.' . Str::random(20);
                    broadcast(new NotificationEvent($userId, $channel));
                }
                break;

        }

        event(new NewCreated('payment'));

        session()->put('payment_number', $pyNo);
        return redirect()->back();
    }

    private function validateSubmittedPaymentBalances(array $validated): void
    {
        if (!empty($validated['selectedDocuments'])) {
            foreach ($validated['selectedDocuments'] as $document) {
                $documentNo = $document['docunumber'] ?? null;
                $documentType = $document['type'] ?? null;

                if (!$documentNo || !$documentType) {
                    continue;
                }

                $availableBalance = $this->getAvailableDocumentBalance(
                    $validated['customer_code'],
                    $documentNo,
                    $documentType
                );

                $requestedAmount = round(
                    (float) ($document['amountToPay'] ?? 0) +
                        (float) ($document['wht_amount'] ?? 0),
                    2
                );

                if ($availableBalance <= 0) {
                    throw ValidationException::withMessages([
                        'document_no' => "Document {$documentNo} is already fully paid.",
                    ]);
                }

                if ($requestedAmount > ($availableBalance + 0.009)) {
                    continue;
                }
            }

            return;
        }

        if (($validated['document_no'] ?? null) !== 'Oldest to Newest Applied') {
            return;
        }

        $openLedgers = CustomerLedger::where('customer_code', $validated['customer_code'])
            ->where('running_balance', '>', 0)
            ->get(['invoice_number', 'type']);

        $hasPayableDocument = $openLedgers->contains(function ($ledger) use ($validated) {
            return $this->getAvailableDocumentBalance(
                $validated['customer_code'],
                $ledger->invoice_number,
                $ledger->type
            ) > 0;
        });

        if (!$hasPayableDocument) {
            throw ValidationException::withMessages([
                'document_no' => 'Selected transaction is already fully paid.',
            ]);
        }
    }

    private function getFloatingDocumentCredit(string $customerCode, string $documentNo, string $documentType): float
    {
        $floatingPaid = (float) PaymentDetails::where('customer_code', $customerCode)
            ->where('document_no', $documentNo)
            ->where('type', $documentType)
            ->where('status', 'Floating')
            ->sum('amount_paid');

        $floatingWht = 0.0;
        if (
            Schema::connection('tenant')->hasColumn('payment_details', 'wht_amount')
            && Schema::connection('tenant')->hasColumn('payment_details', 'wht_status')
        ) {
            $floatingWht = (float) PaymentDetails::where('customer_code', $customerCode)
                ->where('document_no', $documentNo)
                ->where('type', $documentType)
                ->where('wht_status', 'Floating')
                ->where(function ($query) {
                    $query->whereNull('status')
                        ->orWhere('status', '!=', 'Floating');
                })
                ->sum('wht_amount');
        }

        return $floatingPaid + $floatingWht;
    }

    private function getAvailableDocumentBalance(
        string $customerCode,
        string $documentNo,
        string $documentType,
        ?CustomerLedger $ledger = null
    ): float
    {
        $ledger = $ledger ?: CustomerLedger::where('customer_code', $customerCode)
            ->where('invoice_number', $documentNo)
            ->where('type', $documentType)
            ->first();

        if (!$ledger) {
            return 0;
        }

        $floatingCredit = $this->getFloatingDocumentCredit($customerCode, $documentNo, $documentType);

        return max(0, round((float) $ledger->running_balance - $floatingCredit, 2));
    }

    private function tenantAllowsOverpayment(): bool
    {
        $appSettingId = config('tenant.current_app_setting_id');

        if (
            !$appSettingId
            || !Schema::connection('mysql')->hasColumn('app_settings', 'allow_overpayment')
        ) {
            return true;
        }

        $setting = AppSetting::on('mysql')
            ->select('allow_overpayment')
            ->find($appSettingId);

        return (bool) ($setting?->allow_overpayment ?? true);
    }

    private function ensureOverpaymentAllowed(float $overpaymentAmount): void
    {
        if ($overpaymentAmount <= 0) {
            return;
        }

        if ($this->tenantAllowsOverpayment()) {
            return;
        }

        throw ValidationException::withMessages([
            'amount_paid' => 'Overpayment is not allowed for this tenant.',
        ]);
    }

    //THERE IS UPDATE IN CUSTOMER LEDGER
    private function processPayment($validated, $request, $status, $cl_type, $paymentNumberService)
    {
        return DB::transaction(function () use ($validated, $request, $status, $cl_type, $paymentNumberService) {
            $processedDocuments = [];

            if (!empty($validated['selectedDocuments'])) { //MANUAL PAYMENT
                foreach ($validated['selectedDocuments'] as $doc) {
                    $amountToApply = floatval($doc['amountToPay']); // Directly use amountToPay
                    if ($amountToApply <= 0) {
                        throw ValidationException::withMessages([
                            'general' => 'Error Please Try Again',
                        ]);
                    }

                    // Find the specific ledger row by document_no and type
                    $ledger = CustomerLedger::where('customer_code', $validated['customer_code'])
                        ->where('invoice_number', $doc['docunumber'])
                        ->where('type', $doc['type'])
                        ->lockForUpdate()
                        ->first();

                    if (!$ledger) {
                        throw ValidationException::withMessages([
                            'general' => 'Error Please Try Again',
                        ]);
                    }

                    $availableBalance = $this->getAvailableDocumentBalance(
                        $validated['customer_code'],
                        $doc['docunumber'],
                        $doc['type'],
                        $ledger
                    );
                    $floatingValue = max(0, round((float) $ledger->running_balance - $availableBalance, 2));

                    // Calculate WHT if provided in the doc
                    $whtApplied = isset($doc['wht_amount']) ? floatval($doc['wht_amount']) : 0;
                    if ($whtApplied > 0) {
                        $this->ensureDocumentAllowsAdditionalWht(
                            $validated['customer_code'],
                            $doc['docunumber'],
                            $doc['type']
                        );
                    }
                    $applyBir2307 = $validated['apply_bir_2307'] ?? false;
                    $grossApplied = $amountToApply + $whtApplied;
                    $detailsBalance = max(0, $availableBalance - $grossApplied);
                    $overpaymentAmount = isset($doc['overpayment_amount'])
                        ? (float) $doc['overpayment_amount']
                        : max(0, $grossApplied - $availableBalance);
                    $this->ensureOverpaymentAllowed($overpaymentAmount);

                    $updateData = [
                        'running_balance' => max(0, $ledger->running_balance - $amountToApply),
                        'amount_paid' => $ledger->amount_paid + $amountToApply,
                    ];
                    if (Schema::connection('tenant')->hasColumn('customer_ledger', 'overpayment_amount')) {
                        $updateData['overpayment_amount'] = $overpaymentAmount;
                    }
                    $ledger->update($updateData);

                    // Updated document balance after total applied
                    // This load is pass to creating new payment details
                    $processedDocuments[] = [
                        'document_no' => $doc['docunumber'],
                        'type' => $doc['type'],
                        'amount' => $doc['amount'],
                        'balance' => $detailsBalance,
                        'amount_applied' => $amountToApply, // cash only
                        'wht_amount' => $whtApplied,
                        'wht_status' => $this->resolveWhtStatus($whtApplied, $applyBir2307),
                        'total_amount_less_wht' => $amountToApply, // cash only
                        'document_date' => $ledger->date,
                        'floating_deducted' => $floatingValue,
                        'overage_shortage' => $detailsBalance,
                        'overpayment_amount' => $overpaymentAmount,
                    ];
                }
            } else {
                //OLDEST TO NEWEST PAYMENT
                {

                    // $remainingAmount = $validated['amount_paid'] + (float)preg_replace('/[^0-9.]/', '', $validated['advanced_payment_balance']);
                    $cust = Customer::where('cus_code', $validated['customer_code'])->lockForUpdate()->firstOrFail();

                    $remainingAdvPayment = $cust->advanced_payment_balance;
                    $remainingAmountPaid = $validated['amount_paid'];

                    // Get all customer ledgers ordered by date and ID (oldest first)
                    $ledgers = CustomerLedger::where('customer_code', $validated['customer_code'])
                        ->where('running_balance', '!=', 0)
                        ->orderBy('date')
                        ->orderBy('created_at')
                        ->lockForUpdate()
                        ->get();

                    foreach ($ledgers as $ledger) {
                        if ($remainingAdvPayment <= 0 && $remainingAmountPaid <= 0) {
                            break;
                        }

                        $effectiveBalance = $this->getAvailableDocumentBalance(
                            $validated['customer_code'],
                            $ledger->invoice_number,
                            $ledger->type,
                            $ledger
                        );
                        $floatingValue = max(0, round((float) $ledger->running_balance - $effectiveBalance, 2));

                        // Only proceed if there's positive balance after floating deduction
                        if ($effectiveBalance > 0) {
                            // 1️⃣ Apply advance payment first
                            $fromAdv = min($remainingAdvPayment, $effectiveBalance);
                            $remainingAdvPayment -= $fromAdv;

                            // 2️⃣ Apply normal payment for remaining balance
                            $remainingDocBalance = $effectiveBalance - $fromAdv;
                            $fromPaid = min($remainingAmountPaid, $remainingDocBalance);
                            $remainingAmountPaid -= $fromPaid;

                            // Total applied to this document
                            $amountToApply = $fromAdv + $fromPaid;

                            if ($amountToApply > 0) {
                                $whtApplied = 0.0;
                                $applyBir2307 = $validated['apply_bir_2307'] ?? false;

                                $docbalance = max(0, $effectiveBalance - $amountToApply);

                                $updateData = [
                                    'running_balance' => max(0, $ledger->running_balance - $amountToApply),
                                    'amount_paid' => $ledger->amount_paid + $amountToApply,
                                ];
                                if (Schema::connection('tenant')->hasColumn('customer_ledger', 'overpayment_amount')) {
                                    $updateData['overpayment_amount'] = 0;
                                }
                                $ledger->update($updateData);

                                $processedDocuments[] = [
                                    'document_no' => $ledger->invoice_number,
                                    'type' => $ledger->type,
                                    'amount' => $ledger->amount,
                                    'balance' => $docbalance,
                                    'amount_applied' => $amountToApply, // cash only
                                    'advpy_amount_applied' => $fromAdv,
                                    'wht_amount' => $whtApplied,
                                    'wht_status' => $this->resolveWhtStatus($whtApplied, $applyBir2307),
                                    'total_amount_less_wht' => $amountToApply,
                                    'document_date' => $ledger->date,
                                    'floating_deducted' => $floatingValue,
                                    'overage_shortage' => $docbalance,
                                    'overpayment_amount' => 0,
                                ];
                            }
                        }
                    }

                    $cust->update([
                        'advanced_payment_balance' => $remainingAdvPayment + $remainingAmountPaid,
                    ]);
                }
            }

            return $this->createPaymentRecords($validated, $request, $status, $paymentNumberService, '', $processedDocuments);
        });
    }

    //NO UPDATE IN CUSTOMER LEDGER
    private function createDirectPaymentRecords($validated, $request, $paymentNumberService)
    {
        return DB::transaction(function () use ($validated, $request, $paymentNumberService) {
            $processedDocuments = [];

            if (!empty($validated['selectedDocuments'])) { //MANUAL PAYMENT
                foreach ($validated['selectedDocuments'] as $doc) {
                    $amountToApply = floatval($doc['amountToPay']); // Directly use amountToPay
                    if ($amountToApply <= 0) {
                        throw ValidationException::withMessages([
                            'general' => 'Error Please Try Again',
                        ]);
                    }

                    // Find the specific ledger row by document_no and type
                    $ledger = CustomerLedger::where('customer_code', $validated['customer_code'])
                        ->where('invoice_number', $doc['docunumber'])
                        ->where('type', $doc['type'])
                        ->lockForUpdate()
                        ->first();

                    if (!$ledger) {
                        throw ValidationException::withMessages([
                            'general' => 'Error Please Try Again',
                        ]);
                    }

                    $availableBalance = $this->getAvailableDocumentBalance(
                        $validated['customer_code'],
                        $doc['docunumber'],
                        $doc['type'],
                        $ledger
                    );
                    $floatingValue = max(0, round((float) $ledger->running_balance - $availableBalance, 2));

                    $whtApplied = isset($doc['wht_amount']) ? floatval($doc['wht_amount']) : 0;
                    if ($whtApplied > 0) {
                        $this->ensureDocumentAllowsAdditionalWht(
                            $validated['customer_code'],
                            $doc['docunumber'],
                            $doc['type']
                        );
                    }
                    $applyBir2307 = $validated['apply_bir_2307'] ?? false;
                    $grossApplied = $amountToApply + $whtApplied;
                    $detailsBalance = max(0, $availableBalance - $grossApplied);
                    $overpaymentAmount = isset($doc['overpayment_amount'])
                        ? (float) $doc['overpayment_amount']
                        : max(0, $grossApplied - $availableBalance);
                    $this->ensureOverpaymentAllowed($overpaymentAmount);

                    $processedDocuments[] = [
                        'document_no' => $doc['docunumber'],
                        'type' => $doc['type'],
                        'amount' => $doc['amount'],
                        'balance' => $detailsBalance,
                        'amount_applied' => $amountToApply,
                        'wht_amount' => $whtApplied,
                        'wht_status' => $this->resolveWhtStatus($whtApplied, $applyBir2307),
                        'total_amount_less_wht' => $amountToApply,
                        'document_date' => $ledger->date,
                        'floating_deducted' => $floatingValue,
                        'overage_shortage' => $detailsBalance,
                        'overpayment_amount' => $overpaymentAmount,
                    ];
                }
            } else { //OLDEST TO NEWEST PAYMENT
                {
                    $cust = Customer::where('cus_code', $validated['customer_code'])->lockForUpdate()->firstOrFail();

                    $remainingAdvPayment = $cust->advanced_payment_balance;
                    $remainingAmountPaid = $validated['amount_paid'];

                    $whtAmount = !empty($validated['wht_amount'])
                        ? (float) preg_replace('/[^0-9.]/', '', $validated['wht_amount'])
                        : 0;

                    $totalAmountLessWht = !empty($validated['total_amount_less_wht'])
                        ? (float) preg_replace('/[^0-9.]/', '', $validated['total_amount_less_wht'])
                        : 0;

                    $hasWht = $whtAmount > 0;

                    // Get all customer ledgers ordered by date and ID (oldest first)
                    $ledgers = CustomerLedger::where('customer_code', $validated['customer_code'])
                        ->orderBy('date')
                        ->orderBy('created_at')
                        ->lockForUpdate()
                        ->get();

                    foreach ($ledgers as $ledger) {
                        if ($remainingAdvPayment <= 0 && $remainingAmountPaid <= 0) {
                            break;
                        }

                        $effectiveBalance = $this->getAvailableDocumentBalance(
                            $validated['customer_code'],
                            $ledger->invoice_number,
                            $ledger->type,
                            $ledger
                        );
                        $floatingValue = max(0, round((float) $ledger->running_balance - $effectiveBalance, 2));

                        // Only proceed if there's positive balance after floating deduction
                        if ($effectiveBalance > 0) {
                            // 1️⃣ Apply advance payment first
                            $fromAdv = min($remainingAdvPayment, $effectiveBalance);
                            $remainingAdvPayment -= $fromAdv;

                            // 2️⃣ Apply normal payment for remaining balance
                            $remainingDocBalance = $effectiveBalance - $fromAdv;
                            $fromPaid = min($remainingAmountPaid, $remainingDocBalance);
                            $remainingAmountPaid -= $fromPaid;

                            // Total applied to this document
                            $amountToApply = $fromAdv + $fromPaid;

                            if ($amountToApply > 0) {
                                $whtApplied = 0.0;
                                $applyBir2307 = $validated['apply_bir_2307'] ?? false;
                                $detailsBalance = max(0, $effectiveBalance - $amountToApply);

                                $processedDocuments[] = [
                                    'document_no' => $ledger->invoice_number,
                                    'type' => $ledger->type,
                                    'amount' => $ledger->amount,
                                    'balance' => $detailsBalance,
                                    'amount_applied' => $amountToApply,
                                    'advpy_amount_applied' => $fromAdv,
                                    'wht_amount' => $whtApplied,
                                    'wht_status' => $this->resolveWhtStatus($whtApplied, $applyBir2307),
                                    'total_amount_less_wht' => $amountToApply,
                                    'document_date' => $ledger->date,
                                    'floating_deducted' => $floatingValue,
                                    'overage_shortage' => $detailsBalance,
                                    'overpayment_amount' => 0,
                                ];
                            }
                        }
                    }

                    $cust->update([
                        'advanced_payment_balance' => $remainingAdvPayment + $remainingAmountPaid,
                    ]);
                    // if ($remainingAmount > 0) {
                    //     throw ValidationException::withMessages([
                    //         'amount_paid' => 'Payment amount exceeds total outstanding balance with/without floating deductions',
                    //     ]);
                    // }
                }
            }
            return $this->createPaymentRecords($validated, $request, 'Floating', $paymentNumberService, '', $processedDocuments);
        });
    }

    private function createPaymentRecords($validated, $request, $status, $paymentNumberService, $cashConfirm, $processedDocuments)
    {
        return DB::transaction(function () use ($validated, $request, $status, $paymentNumberService, $cashConfirm, $processedDocuments) {
            $nextNumber = $paymentNumberService->generate();

            // Validate the payment number is unique
            if (Payment::where('payment_no', $nextNumber)->exists()) {
                throw ValidationException::withMessages([
                    'general' => 'Error Please Try Again',
                ]);
            }

            $documentNumbers = collect($processedDocuments)
                ->pluck('document_no')
                ->unique()
                ->implode(',');

            $dbData = collect($validated)
                ->except(['_od_confirmation', '_check_confirmation', '_cl_type'])
                ->all();


            $totalAmount = isset($dbData['total_amount'])
                ? (float) preg_replace('/[^0-9.]/', '', $dbData['total_amount'])
                : 0.0;
            $headerDocumentTotal = collect($processedDocuments)->sum(
                fn($doc) => (float) ($doc['amount'] ?? 0)
            );

            $totalWht = collect($processedDocuments)->sum(fn($doc) => (float) ($doc['wht_amount'] ?? 0));
            $netPaid = collect($processedDocuments)->sum(fn($doc) => (float) ($doc['amount_applied'] ?? 0));
            $grossPaid = $netPaid + $totalWht;

            $applyBir2307 = $validated['apply_bir_2307'] ?? false;

            $dbData['total_amount'] = !empty($validated['selectedDocuments']) && $headerDocumentTotal > 0
                ? $headerDocumentTotal
                : ($totalAmount > 0 ? $totalAmount : $grossPaid);
            $dbData['amount_paid'] = $grossPaid;
            $dbData['wht_amount'] = $this->isPaymentTypeSubjectToWht($validated['payment_type']) ? $totalWht : 0.0;
            $dbData['total_amount_less_wht'] = $this->isPaymentTypeSubjectToWht($validated['payment_type'])
                ? $netPaid
                : $grossPaid;

            $dbData['created_by'] = $request->user()->name;
            $dbData['payment_no'] = $nextNumber;
            $dbData['document_no'] = $documentNumbers;

            if (empty($validated['selectedDocuments'])) {
                $dbData['advpy_amount_paid'] = (float)preg_replace('/[^0-9.]/', '', $dbData['advanced_payment_balance']);
            } else {
                $dbData['advpy_amount_paid'] = 0;
            }

            if ($this->isPaymentTypeSubjectToWht($validated['payment_type']) && $totalWht > 0) {
                $expectedGross = $grossPaid;
                if ($totalAmount > 0 && bccomp((string) $expectedGross, (string) $totalAmount, 2) > 0) {
                    throw ValidationException::withMessages([
                        'amount_paid' => 'Total applied (net + WHT) cannot exceed total amount.',
                    ]);
                }
            }

            if (!Schema::connection('tenant')->hasColumn('payment', 'wht_amount')) {
                unset($dbData['wht_amount']);
            }
            if (!Schema::connection('tenant')->hasColumn('payment', 'total_amount_less_wht')) {
                unset($dbData['total_amount_less_wht']);
            }
            if (!Schema::connection('tenant')->hasColumn('payment', 'advpy_amount_paid')) {
                unset($dbData['advpy_amount_paid']);
            }
            Payment::create($dbData);

            $checkno = null;

            if ($cashConfirm != 'Cash Confirmed' && $validated['payment_type'] === '5D - Check') {
                if ($validated['check_type']) {
                    $checkno = $validated['reference_no'];
                }
            }

            if (empty($validated['selectedDocuments'])) { //OLDEST TO NEWEST
                //    if you pay cash in payment module 
                foreach ($processedDocuments as $doc) {
                    $whtPerDoc = isset($doc['wht_amount'])
                        ? (float) preg_replace('/[^0-9.]/', '', $doc['wht_amount'])
                        : 0;

                    $amountApplied = (float) preg_replace('/[^0-9.]/', '', $doc['amount_applied']);

                    $finalAmountPaid = $whtPerDoc > 0 ? $amountApplied + $whtPerDoc : $amountApplied;

                    $detailsData = [
                        'payment_no' => $nextNumber,
                        'check_no' => $checkno,
                        'document_no' => $doc['document_no'],
                        'document_date' => $doc['document_date'],
                        'payment_receipt_date' => $validated['receipt_date'],
                        'payment_date' => $validated['transaction_date'],
                        'payment_type' => substr($validated['payment_type'], 5),
                        'type' => $doc['type'],
                        'customer_code' => $validated['customer_code'],
                        'customer_name' => $validated['name'],
                        'check_type' => $status === 'Floating' ? ($validated['check_type'] ?? 'N/A') : 'N/A',
                        'advpy_amount_paid' => $doc['advpy_amount_applied'],
                        'amount' => $doc['amount'],
                        'balance' => $doc['balance'],
                        'amount_paid' => $finalAmountPaid,
                        'due_date' => $validated['due_date'] ?? null,
                        'status' => $status,
                        'overage_shortage' => $doc['overage_shortage'],
                        'created_by' => $request->user()->name,
                    ];
                    if (Schema::connection('tenant')->hasColumn('payment_details', 'wht_amount')) {
                        $detailsData['wht_amount'] = $whtPerDoc;
                        if ($whtPerDoc > 0 && isset($doc['wht_status'])) {
                            $detailsData['wht_status'] = $doc['wht_status'];
                        }
                    }
                    if (Schema::connection('tenant')->hasColumn('payment_details', 'floating_deducted_amount')) {
                        $detailsData['floating_deducted_amount'] = isset($doc['floating_deducted'])
                            ? (float) $doc['floating_deducted']
                            : 0;
                    }
                    if (Schema::connection('tenant')->hasColumn('payment_details', 'overpayment_amount')) {
                        $detailsData['overpayment_amount'] = isset($doc['overpayment_amount'])
                            ? (float) $doc['overpayment_amount']
                            : 0;
                    }
                    PaymentDetails::create($detailsData);
                }
            }
            // if you pay cash in invoice module
            else { //MANUAL PAYMENT
                foreach ($processedDocuments as $doc) {
                    $whtPerDoc = isset($doc['wht_amount'])
                        ? (float) preg_replace('/[^0-9.]/', '', $doc['wht_amount'])
                        : 0;

                    $amountApplied = (float) preg_replace('/[^0-9.]/', '', $doc['amount_applied']);

                    $finalAmountPaid = $whtPerDoc > 0 ? $amountApplied + $whtPerDoc : $amountApplied;

                    $detailsData = [
                        'payment_no' => $nextNumber,
                        'check_no' => $checkno,
                        'document_no' => $doc['document_no'],
                        'document_date' => $doc['document_date'],
                        'payment_receipt_date' => $validated['receipt_date'],
                        'payment_date' => $validated['transaction_date'],
                        'payment_type' => substr($validated['payment_type'], 5),
                        'type' => $doc['type'],
                        'customer_code' => $validated['customer_code'],
                        'customer_name' => $validated['name'],
                        'check_type' => $status === 'Floating' ? ($validated['check_type'] ?? 'N/A') : 'N/A',
                        'advpy_amount_paid' => 0,
                        'amount' => $doc['amount'],
                        'balance' => $doc['balance'],
                        'amount_paid' => $finalAmountPaid,
                        'due_date' => $validated['due_date'] ?? null,
                        'status' => $status,
                        'overage_shortage' => $doc['overage_shortage'],
                        'created_by' => $request->user()->name,
                    ];
                    if (Schema::connection('tenant')->hasColumn('payment_details', 'wht_amount')) {
                        $detailsData['wht_amount'] = $whtPerDoc;
                        if ($whtPerDoc > 0 && isset($doc['wht_status'])) {
                            $detailsData['wht_status'] = $doc['wht_status'];
                        }
                    }
                    if (Schema::connection('tenant')->hasColumn('payment_details', 'floating_deducted_amount')) {
                        $detailsData['floating_deducted_amount'] = isset($doc['floating_deducted'])
                            ? (float) $doc['floating_deducted']
                            : 0;
                    }
                    if (Schema::connection('tenant')->hasColumn('payment_details', 'overpayment_amount')) {
                        $detailsData['overpayment_amount'] = isset($doc['overpayment_amount'])
                            ? (float) $doc['overpayment_amount']
                            : 0;
                    }
                    PaymentDetails::create($detailsData);
                }
            }

            return $nextNumber;
        });
    }

    private function createArRecords($validated, $request, $invoiceNumberService)
    {
        DB::transaction(function () use ($validated, $request, $invoiceNumberService) {
            $customer = CustomerService::getCustomerByCode($validated['cust_code']);
            $invoiceNumber = $invoiceNumberService->generate(true);

            $ledgerData = [
                'invoice_number' => $invoiceNumber,
                'date' => $validated['receipt_date'],
                'type' => "Payment",
                'customer_code' => $validated['cust_code'],
                'customer_name' => $customer->cus_name,
                'currency' => "Php",
                'amount' => $validated['amount_paid'],
                'adjusted_amount' => 0.00,
                'amount_paid' => 0.00,
                'running_balance' => $validated['amount_paid'],
            ];

            if (Schema::connection('tenant')->hasColumn('customer_ledger', 'transfer_from')) {
                $sourceCustomer = trim(($validated['customer_code'] ?? '') . ' - ' . ($validated['name'] ?? ''), ' -');
                $ledgerData['transfer_from'] = $sourceCustomer !== '' ? $sourceCustomer : null;
            }

            CustomerLedger::create($ledgerData);
        });
    }

    public function destroy($id)
    {
        $adj = Payment::findorFail($id);
        $adj->delete();
    }

    public function latest(PaymentNumberService $paymentNumberService)
    {
        //return Payment::orderByDesc('id')->value('payment_no'); // returns "26000001" or null
        return DB::transaction(function () use ($paymentNumberService) {
            $nextNumber = $paymentNumberService->generate();
            // $latestPayment = Payment::withTrashed()
            //     ->lockForUpdate() // Prevent concurrent access
            //     ->orderByDesc('payment_no')
            //     ->first();

            // $nextNumber = $latestPayment ? $latestPayment->payment_no + 1 : 26000001;

            return response()->json([
                'next_payment_no' => $nextNumber,
            ]);
        });
    }

    public function latestPaymentNO()
    {
        return DB::transaction(function () {
            // Get the latest payment number from local database
            $latestPayment = Payment::withTrashed()
                ->lockForUpdate()
                ->orderByDesc('payment_no')
                ->first();

            $localNextNumber = $latestPayment ? $latestPayment->payment_no + 1 : 26000001;

            // Get the latest payment number from external API
            try {
                //DYNAMIC API LINK
                $user = Auth::user();
                $tenantSlug = request()->route('tenant');
                $targetSetting = AppSetting::on('mysql')
                    ->where('is_active', true)
                    ->where(function ($q) use ($tenantSlug) {
                        $q->where('base_url', $tenantSlug)
                          ->orWhereRaw("REPLACE(LOWER(app_name), ' ', '') = ?", [strtolower($tenantSlug)])
                          ->orWhereRaw("? LIKE CONCAT('%', REPLACE(LOWER(app_name), ' ', ''), '%')", [strtolower($tenantSlug)]);
                    })
                    ->first();
                $appName = $targetSetting->app_name ?? ($user && $user->appSetting ? $user->appSetting->app_name : config('app.name'));
                switch ($appName) {
                    case 'Bilar Breeder Local':
                        $baseUrl = 'http://172.16.43.148/centralized_invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=13';
                        break;
                    case 'Bilar Breeder':
                        $baseUrl = 'http://172.16.220.1:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=13';
                        break;
                    case 'Gp Jagna':
                        $baseUrl = 'http://172.16.112.51:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=50';
                        break;
                    case 'Ice Plant':
                        $baseUrl = 'http://172.16.184.49:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=25';
                        break;
                    case 'Peanut Kisses':
                        $baseUrl = 'http://172.16.184.49:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=26';
                        break;
                    case 'Cortes Poultry':
                        $baseUrl = 'http://172.16.192.68:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=12';
                        break;
                    case 'Cortes Piggery':
                        $baseUrl = 'http://172.16.192.68:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=11';
                        break;
                    case 'Canhayupon Breeder':
                        $baseUrl = 'http://172.16.220.223:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=15';
                        break;
                    case 'Bilar Hatchery':
                        $baseUrl = 'http://172.16.219.200:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=14';
                        break;
                    case 'Lapsaon Breeder':
                        $baseUrl = 'http://172.16.220.222:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=16';
                        break;
                    case 'Rizal Breeder':
                        $baseUrl = 'http://172.16.217.11:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=43';
                        break;
                    // ubay server 
                    case 'Feedmill':
                        $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=19';
                        break;
                    case 'Growout':
                        $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=20';
                        break;
                    case 'Cortes Fertilizer':
                        $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=42';
                        break;
                    case 'Ubay Fertilizer':
                        $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=22';
                        break;
                    case 'Piggery Untaga':
                        $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=23';
                        break;
                    case 'Demo Farm':
                        $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=21';
                        break;
                    case 'Dressing Plant':
                        $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=17';
                        break;
                    case 'Farmers Market':
                        $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=41';
                        break;
                    case 'Meat Processing':
                        $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=46';
                        break;
                    case 'Rendering':
                        $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=18';
                        break;
                    default:
                        throw new \Exception("Unknown app name: {$appName}");
                }
                $url = $baseUrl;
                Log::info('latestPaymentNO endpoint', ['url' => $url, 'app_name' => $appName]);
                $response = Http::timeout(3)->retry(2, 100)->get($url);

                if ($response->successful()) {
                    $apiNextNumber = $response->json()['next_payment_no'] ?? 0;
                    $nextNumber = max($localNextNumber, $apiNextNumber);
                } else {
                    // If API fails, fall back to local number
                    $nextNumber = $localNextNumber;
                }
            } catch (\Exception $e) {
                // If there's any exception (connection error, etc.), use local number
                $nextNumber = $localNextNumber;
            }

            return response()->json([
                'next_payment_no' => $nextNumber,
            ]);
        });
    }

    public function generateNextPaymentNumber()
    {
        // Lock and get the highest payment number
        $latestPayment = Payment::withTrashed()
            ->lockForUpdate()
            ->orderByDesc('payment_no')
            ->first();

        // Extract numeric part (assuming payment_no is numeric)
        $localNextNumber = $latestPayment ? $latestPayment->payment_no + 1 : 26000001;

        // Get the latest payment number from external API
        try {
            $user = Auth::user();
            $tenantSlug = request()->route('tenant');
            $targetSetting = AppSetting::on('mysql')
                ->where('is_active', true)
                ->where(function ($q) use ($tenantSlug) {
                    $q->where('base_url', $tenantSlug)
                      ->orWhereRaw("REPLACE(LOWER(app_name), ' ', '') = ?", [strtolower($tenantSlug)])
                      ->orWhereRaw("? LIKE CONCAT('%', REPLACE(LOWER(app_name), ' ', ''), '%')", [strtolower($tenantSlug)]);
                })
                ->first();
            $appName = $targetSetting->app_name ?? ($user && $user->appSetting ? $user->appSetting->app_name : config('app.name'));
            switch ($appName) {
                case 'Bilar Breeder Local':
                    $baseUrl = 'http://172.16.43.148/centralized_invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=13';
                    break;
                case 'Bilar Breeder':
                    $baseUrl = 'http://172.16.220.1:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=13';
                    break;
                case 'Gp Jagna':
                    $baseUrl = 'http://172.16.112.51:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=50';
                    break;
                case 'Ice Plant':
                    $baseUrl = 'http://172.16.184.49:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=25';
                    break;
                case 'Peanut Kisses':
                    $baseUrl = 'http://172.16.184.49:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=26';
                    break;
                case 'Cortes Poultry':
                    $baseUrl = 'http://172.16.192.68:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=12';
                    break;
                case 'Cortes Piggery':
                    $baseUrl = 'http://172.16.192.68:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=11';
                    break;
                case 'Canhayupon Breeder':
                    $baseUrl = 'http://172.16.220.223:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=15';
                    break;
                case 'Bilar Hatchery':
                    $baseUrl = 'http://172.16.219.200:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=14';
                    break;
                case 'Lapsaon Breeder':
                    $baseUrl = 'http://172.16.220.222:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=16';
                    break;
                case 'Rizal Breeder':
                    $baseUrl = 'http://172.16.217.11:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=43';
                    break;
                // ubay server 
                case 'Feedmill':
                    $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=19';
                    break;
                case 'Growout':
                    $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=20';
                    break;
                case 'Cortes Fertilizer':
                    $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=42';
                    break;
                case 'Ubay Fertilizer':
                    $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=22';
                    break;
                case 'Piggery Untaga':
                    $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=23';
                    break;
                case 'Demo Farm':
                    $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=21';
                    break;
                case 'Dressing Plant':
                    $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=17';
                    break;
                case 'Farmers Market':
                    $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=41';
                    break;
                case 'Meat Processing':
                    $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=46';
                    break;
                case 'Rendering':
                    $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=18';
                    break;
                default:
                    throw new \Exception("Unknown app name: {$appName}");
            }
            $url = $baseUrl;

            $response = Http::timeout(3)->retry(2, 100)->get($url);
            if ($response->successful()) {
                $apiData = $response->json();
                $apiNextNumber = $apiData['next_payment_no'] ?? 0;
                $nextNumber = max($localNextNumber, $apiNextNumber);
            } else {
                $nextNumber = $localNextNumber;
            }
        } catch (\Exception $e) {
            $nextNumber = $localNextNumber;
        }

        return $nextNumber; // Return the number directly, not a JSON response
    }

    public function storePaymentAPI(Request $request, PaymentNumberService $paymentNumberService)
    {
        try {
            $validated = $request->validate([
                'payment_no' => ['required', 'string'],
                'receipt_date' => ['required', 'date', 'before_or_equal:today'],
                'transaction_date' => ['required', 'date', 'before_or_equal:today'],
                'customer_code' => ['required', 'string'],
                'name' => ['required', 'string'],
                'payment_type' => ['required', 'in:5A - Cash,5B - Journal Voucher,5C - Online Deposit,5D - Check'],
                'type' => ['required', 'in:Sales Invoice,Charge Invoice,Payment,BG'],
                'document_no' => ['required', 'string'],
                'document_date' => ['required', 'date'],
                'advpy_amount_paid' => ['required', 'numeric'],
                'total_amount' => ['required', 'string'],
                'amount_paid' => [
                    'required',
                    'numeric',
                    'between:0,999999999999.99',
                    function ($attribute, $value, $fail) use ($request) {
                        if ($value > (float)preg_replace('/[^0-9.]/', '', $request->total_amount)) {
                            $fail('Amount Should Not Be Greater Than Available Balance');
                        }
                    },
                ],
                'wht_amount' => [
                    'nullable',
                    'numeric',
                    'between:0,9999999999999.99',
                ],
                'created_by' => ['required', 'string'],
            ]);

            $pyNo = DB::transaction(function () use ($validated, $request, $paymentNumberService) {
                $nextNumber = $paymentNumberService->generate();

                if (Payment::where('payment_no', $nextNumber)->where('type', $validated['type'])->exists()) {
                    throw ValidationException::withMessages([
                        'general' => 'Duplicate payment number generated. Please retry.',
                    ]);
                }

                $validated['payment_no'] = $nextNumber;
                $validated['total_amount'] = (float)preg_replace('/[^0-9.]/', '', $validated['total_amount']);

                if (array_key_exists('wht_amount', $validated) && $validated['wht_amount'] !== null) {
                    $validated['wht_amount'] = (float)$validated['wht_amount'];
                }

                $payment = Payment::create($validated);

                $paymentDetailsData = [
                    'payment_no' => $nextNumber,
                    'document_no' => $validated['document_no'],
                    'document_date' => $validated['document_date'],
                    'payment_receipt_date' => $validated['receipt_date'],
                    'payment_date' => $validated['transaction_date'],
                    'payment_type' => substr($validated['payment_type'], 5),
                    'type' => $validated['type'],
                    'customer_code' => $validated['customer_code'],
                    'customer_name' => $validated['name'],
                    'check_type' => 'N/A',
                    'advpy_amount_paid' => $validated['advpy_amount_paid'],
                    'amount' => $validated['total_amount'],
                    'balance' => $validated['total_amount'],
                    'amount_paid' => $validated['amount_paid'],
                    'status' => 'Paid',
                    'overage_shortage' => 0,
                    'created_by' => $validated['created_by'],
                ];

                if (array_key_exists('wht_amount', $validated) && $validated['wht_amount'] !== null) {
                    $paymentDetailsData['wht_amount'] = $validated['wht_amount'];
                }

                $paymentDetails = PaymentDetails::create($paymentDetailsData);

                return [
                    'payment_no' => $nextNumber,
                    'payment' => $payment,
                    'payment_details' => $paymentDetails,
                ];
            });

            event(new NewCreated('payment'));
            event(new NewCreated('customerledger'));

            return response()->json([
                'success' => true,
                'message' => 'Payment recorded successfully.',
                'data' => [
                    'payment_no' => $pyNo['payment_no'],
                    'payment' => $pyNo['payment'],
                    'payment_details' => $pyNo['payment_details'],
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->getMessage(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
