<?php

namespace App\Http\Controllers\UtilityControllers;

use App\Events\NewCreated;
use App\Events\NotificationEvent;
use App\Http\Controllers\Controller;
use App\Http\Controllers\NotificationsController;
use App\Models\MasterfileModels\Customer;
use App\Models\MasterfileModels\User;
use App\Models\ReportModels\CustomerLedger;
use App\Models\TransactionModels\PaymentDetails;
use App\Models\UtilityModels\CheckCleared;
use App\Models\UtilityModels\CheckClearedItems;
use App\Services\CheckClearedNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Illuminate\Support\Str;

class CheckClearedController extends Controller
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

    private function syncLedgerForPayment(string $customerCode, string $documentNo, string $type): void
    {
        $hasLedgerWhtAmount = Schema::connection('tenant')->hasColumn('customer_ledger', 'wht_amount');
        $hasLedgerOverpaymentAmount = Schema::connection('tenant')->hasColumn('customer_ledger', 'overpayment_amount');
        $hasPaymentDetailsWhtColumns = Schema::connection('tenant')->hasColumn('payment_details', 'wht_amount')
            && Schema::connection('tenant')->hasColumn('payment_details', 'wht_status');

        $ledgerQuery = CustomerLedger::on('tenant')
            ->where('customer_code', $customerCode)
            ->where('invoice_number', $documentNo)
            ->where('type', $type)
            ->lockForUpdate();

        $ledgerCount = (clone $ledgerQuery)->count();

        if ($ledgerCount === 0) {
            throw ValidationException::withMessages([
                'customer_ledger' => "Customer ledger entry not found for document {$documentNo} ({$type}).",
            ]);
        }

        if ($ledgerCount > 1) {
            throw ValidationException::withMessages([
                'customer_ledger' => "Duplicate customer ledger entries found for document {$documentNo} ({$type}).",
            ]);
        }

        $ledger = $ledgerQuery->firstOrFail();
        $totalCollectiblePaid = (float) PaymentDetails::on('tenant')
            ->where('customer_code', $customerCode)
            ->where('document_no', $documentNo)
            ->where('type', $type)
            ->whereIn('status', ['Floating', 'Paid', 'Cleared'])
            ->sum('amount_paid');

        $totalPaid = (float) PaymentDetails::on('tenant')
            ->where('customer_code', $customerCode)
            ->where('document_no', $documentNo)
            ->where('type', $type)
            ->whereIn('status', ['Paid', 'Cleared'])
            ->get()
            ->sum(fn ($detail) => $this->getEffectivePaidAmount($detail, $hasPaymentDetailsWhtColumns));

        $totalWhtApplied = 0.0;
        if ($hasLedgerWhtAmount && Schema::connection('tenant')->hasColumn('payment_details', 'wht_amount')) {
            $whtQuery = PaymentDetails::on('tenant')
                ->where('customer_code', $customerCode)
                ->where('document_no', $documentNo)
                ->where('type', $type)
                ->whereIn('status', ['Paid', 'Cleared'])
                ->where('wht_amount', '>', 0);

            if ($hasPaymentDetailsWhtColumns) {
                $whtQuery->where(function ($q) {
                    $q->whereNull('wht_status')
                        ->orWhere('wht_status', 'Cleared');
                });
            }

            $totalWhtApplied = (float) $whtQuery->sum('wht_amount');
        }

        $baseAmount = (float) ($ledger->adjusted_amount ?? $ledger->amount ?? 0);
        $overage = (float) ($ledger->overage ?? 0);
        $shrinkage = (float) ($ledger->shrinkage ?? 0);
        $returnAmount = (float) ($ledger->return ?? 0);

        $debit = $baseAmount + $overage - $shrinkage - $returnAmount;
        $totalCredited = $totalPaid + $totalWhtApplied;
        $runningBalance = max(0, $debit - $totalCredited);
        $overpaymentAmount = max(0, $totalCollectiblePaid - $debit);

        $ledgerUpdate = [
            'amount_paid' => $totalPaid,
            'running_balance' => $runningBalance,
        ];
        if ($hasLedgerWhtAmount) {
            $ledgerUpdate['wht_amount'] = $totalWhtApplied;
        }
        if ($hasLedgerOverpaymentAmount) {
            $ledgerUpdate['overpayment_amount'] = $overpaymentAmount;
        }

        $ledger->update($ledgerUpdate);
        $this->syncPaymentDetailOverpayment($customerCode, $documentNo, $type, $debit);
    }

    public function index(Request $request)
    {
        $query = CheckCleared::on('tenant');

        // Search functionality
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('customer_name', 'like', '%' . $request->search . '%')
                    ->orWhere('clearing_no', 'like', '%' . $request->search . '%');
            });
        }

        // Date sorting
        if ($request->date_start) {
            $query->whereDate('clearing_date', '>=', $request->date_start);
        }

        if ($request->date_end) {
            $query->whereDate('clearing_date', '<=', $request->date_end);
        }

        // Check Type filters
        if ($request->type_filtersCheckType) {
            $types = is_array($request->type_filtersCheckType)
                ? $request->type_filtersCheckType
                : explode(',', $request->type_filtersCheckType);
            $query->whereIn('check_type', $types);
        }

        // Code sorting
        if ($request->code_sort) {
            $query->orderBy('clearing_no', $request->code_sort === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('updated_at', 'desc');
        }

        return Inertia::render('PdcAndDcClearing', [
            'check_clearings' => $query->paginate(10)->withQueryString(),
            'searchTerm' => $request->search,
            'filters' => [
                'code_sort' => $request->code_sort,
                'type_filtersCheckType' => $request->type_filtersCheckType ?
                    (is_array($request->type_filtersCheckType) ?
                        $request->type_filtersCheckType :
                        explode(',', $request->type_filtersCheckType)) :
                    [],
                'date_start' => $request->date_start,
                'date_end' => $request->date_end,
            ],
            'broadcastChannel' => 'check_clearings',
        ]);
    }

    public function clearChecks(Request $request, CheckClearedNumberService $checkClearedNumberService)
    {
        // Log::debug($request->payment_details);
        $validated = $request->validate([
            'clearing_no' => 'required|string',
            'transaction_date' => 'required|date',
            'clearing_date' => 'required|date',
            'customer_code' => 'required|string',
            'customer_name' => 'required|string',
            'check_type' => 'required|string',
            'payment_details' => 'required|array',
            'payment_details.*.payment_no' => 'required|string',
            'payment_details.*.check_no' => 'required|string',
            'payment_details.*.type' => 'required|string',
            'payment_details.*.document_no' => 'required|string',
            'payment_details.*.due_date' => 'required|date',
            'payment_details.*.amount' => 'required|numeric',
            'payment_details.*.status' => 'required|string',
            'payment_details.*.remarks' => 'nullable|string'
        ]);

        $notificationsController = new NotificationsController();

        $clrNo = DB::connection('tenant')->transaction(function () use ($validated, $request, $checkClearedNumberService) {
            $clearingNo = $checkClearedNumberService->generate();
            // Validate the payment number is unique (just in case)
            if (CheckCleared::on('tenant')->where('clearing_no', $clearingNo)->exists()) {
                throw ValidationException::withMessages([
                    'clearing_no' => 'Error Please Try Again',
                ]);
            }
            CheckCleared::on('tenant')->create([
                'clearing_no' => $clearingNo,
                'transaction_date' => $validated['transaction_date'],
                'clearing_date' => $validated['clearing_date'],
                'check_type' => $validated['check_type'],
                'customer_code' => $validated['customer_code'],
                'customer_name' => $validated['customer_name'],
                'created_by' => $request->user()->name,
            ]);

            $checkClearedItems = array_map(function ($item) use ($clearingNo) {
                return [
                    'clearing_no' => $clearingNo,
                    'payment_no' => $item['payment_no'],
                    'check_no' => $item['check_no'],
                    'document_no' => $item['document_no'],
                    'due_date' => $item['due_date'],
                    'amount' => $item['amount'],
                    'status' => $item['status'],
                    'remarks' => $item['remarks'] ?? null,
                ];
            }, $validated['payment_details']);

            CheckClearedItems::on('tenant')->insert($checkClearedItems);

            foreach ($validated['payment_details'] as $payment) {
                $pd = PaymentDetails::on('tenant')->where([
                    'payment_no' => $payment['payment_no'],
                    'check_no'   => $payment['check_no'],
                    'type'       => $payment['type'],
                    'status'     => 'Floating',
                    'document_no' => $payment['document_no'],
                ])->lockForUpdate()->first();

                if (!$pd) {
                    throw ValidationException::withMessages([
                        'payment_details' => "Floating cheque record not found for payment {$payment['payment_no']} / document {$payment['document_no']}.",
                    ]);
                }

                $pd->update([
                    'status' => $payment['status'],
                    'clearing_date' => $validated['clearing_date'],
                ]);

                if ($payment['status'] === 'Cleared') {
                    $this->syncLedgerForPayment(
                        $validated['customer_code'],
                        $payment['document_no'],
                        $payment['type']
                    );
                }

                if ($payment['status'] === 'Cancelled') {
                    $cust = Customer::on('tenant')->where('cus_code', $validated['customer_code'])
                        ->lockForUpdate()
                        ->first();

                    if ($cust) {
                        $cust->update([
                            'advanced_payment_balance' => $pd->advpy_amount_paid + $cust->advanced_payment_balance,
                        ]);
                    }
                }
            }
            return $clearingNo;
        });
        event(new NewCreated('check_clearing'));
        event(new NewCreated('customerledger'));

        $notificationsController->index($request);

        $userIds = User::whereIn('role', ['Admin', 'Accounting'])
            ->pluck('id')
            ->unique();

        foreach ($userIds as $userId) {
            $channel = 'notification-update.' . Str::random(20);
            broadcast(new NotificationEvent($userId, $channel));
        }

        session()->put('clearing_number', $clrNo);
        return redirect()->back();
    }

    public function applyToLedger(Request $request, $tenant, string $clearing_no)
    {
        $syncedCount = DB::connection('tenant')->transaction(function () use ($clearing_no) {
            $clearing = CheckCleared::on('tenant')
                ->where('clearing_no', trim($clearing_no))
                ->lockForUpdate()
                ->firstOrFail();

            $items = CheckClearedItems::on('tenant')
                ->where('clearing_no', $clearing->clearing_no)
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'clearing_no' => 'No cleared cheque items were found for this transaction.',
                ]);
            }

            $paymentQuery = PaymentDetails::on('tenant')
                ->where('customer_code', $clearing->customer_code)
                ->where('status', 'Cleared')
                ->where(function ($query) use ($items) {
                    foreach ($items as $item) {
                        $query->orWhere(function ($paymentQuery) use ($item) {
                            $paymentQuery->where('payment_no', $item->payment_no)
                                ->where('document_no', $item->document_no)
                                ->where('check_no', $item->check_no);
                        });
                    }
                });

            $documentsToSync = $paymentQuery
                ->get(['document_no', 'type'])
                ->unique(function ($payment) {
                    return $payment->document_no . '|' . $payment->type;
                })
                ->values();

            if ($documentsToSync->isEmpty()) {
                throw ValidationException::withMessages([
                    'payment_details' => 'No cleared payment details were found for this clearing transaction.',
                ]);
            }

            foreach ($documentsToSync as $payment) {
                $this->syncLedgerForPayment(
                    $clearing->customer_code,
                    $payment->document_no,
                    $payment->type
                );
            }

            return $documentsToSync->count();
        });

        event(new NewCreated('customerledger'));

        return response()->json([
            'message' => 'Customer ledger has been re-applied successfully.',
            'synced_documents' => $syncedCount,
        ]);
    }

    public function latest()
    {
        return DB::connection('tenant')->transaction(function () {
            $latestCheckCleared = CheckCleared::on('tenant')->lockForUpdate()
                ->orderByDesc('clearing_no')
                ->first();

            $nextNumber = $latestCheckCleared ? $latestCheckCleared->clearing_no + 1 : 26000001;

            return response()->json([
                'next_clearing_no' => $nextNumber,
                'is_new_sequence' => !$latestCheckCleared
            ]);
        });
    }
}
