<?php

namespace App\Http\Controllers\UtilityControllers;

use App\Events\NewCreated;
use App\Events\NotificationEvent;
use App\Http\Controllers\Controller;
use App\Http\Controllers\NotificationsController;
use App\Models\MasterfileModels\Customer;
use App\Models\MasterfileModels\User;
use App\Models\ReportModels\CustomerLedger;
use App\Models\TransactionModels\Payment;
use App\Models\TransactionModels\PaymentDetails;
use App\Models\UtilityModels\WHTCleared;
use App\Models\UtilityModels\WHTClearedItems;
use App\Services\WHTClearedNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Illuminate\Support\Str;

class WHTClearedController extends Controller
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

    private function calculateLedgerDebit(CustomerLedger $ledger): float
    {
        $baseAmount = (float) ($ledger->adjusted_amount ?? $ledger->amount ?? 0);
        $overage = (float) ($ledger->overage ?? 0);
        $shrinkage = (float) ($ledger->shrinkage ?? 0);
        $returnAmount = (float) ($ledger->return ?? 0);

        return $baseAmount + $overage - $shrinkage - $returnAmount;
    }

    private function syncPaymentDetailOverpayment(string $customerCode, string $documentNo, string $type, float $debit): void
    {
        if (!Schema::connection('tenant')->hasColumn('payment_details', 'overpayment_amount')) {
            return;
        }

        $hasPaymentDetailsWhtColumns = Schema::connection('tenant')->hasColumn('payment_details', 'wht_amount')
            && Schema::connection('tenant')->hasColumn('payment_details', 'wht_status');

        $rows = PaymentDetails::where('customer_code', $customerCode)
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

        $ledger = CustomerLedger::where('customer_code', $customerCode)
            ->where('invoice_number', $documentNo)
            ->where('type', $type)
            ->lockForUpdate()
            ->firstOrFail();

        $rows = PaymentDetails::where('customer_code', $customerCode)
            ->where('document_no', $documentNo)
            ->where('type', $type)
            ->where('status', '!=', 'Cancelled')
            ->lockForUpdate()
            ->get();
        $totalCollectiblePaid = (float) $rows
            ->filter(fn($row) => in_array($row->status, ['Floating', 'Paid', 'Cleared'], true))
            ->sum('amount_paid');

        $totalPaid = 0.0;
        $totalWhtApplied = 0.0;

        foreach ($rows as $row) {
            $totalPaid += $this->getEffectivePaidAmount($row, $hasPaymentDetailsWhtColumns);

            if (
                $hasLedgerWhtAmount
                && Schema::connection('tenant')->hasColumn('payment_details', 'wht_amount')
                && (float) ($row->wht_amount ?? 0) > 0
                && (
                    !$hasPaymentDetailsWhtColumns
                    || $row->wht_status === null
                    || $row->wht_status === 'Cleared'
                )
            ) {
                $totalWhtApplied += (float) ($row->wht_amount ?? 0);
            }
        }

        $ledgerDebit = $this->calculateLedgerDebit($ledger);
        $totalCredited = $totalPaid + $totalWhtApplied;
        $ledgerUpdateData = [
            'running_balance' => max(0, $ledgerDebit - $totalCredited),
            'amount_paid' => $totalPaid,
        ];

        if ($hasLedgerWhtAmount) {
            $ledgerUpdateData['wht_amount'] = $totalWhtApplied;
        }
        if ($hasLedgerOverpaymentAmount) {
            $ledgerUpdateData['overpayment_amount'] = max(0, $totalCollectiblePaid - $ledgerDebit);
        }

        $ledger->update($ledgerUpdateData);
        $this->syncPaymentDetailOverpayment($customerCode, $documentNo, $type, $ledgerDebit);
    }

    public function index(Request $request)
    {
        $query = WHTCleared::query();

        // Search functionality
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('customer_name', 'like', '%' . $request->search . '%')
                    ->orWhere('wht_clearing_no', 'like', '%' . $request->search . '%');
            });
        }

        // Date sorting
        if ($request->date_start) {
            $query->whereDate('clearing_date', '>=', $request->date_start);
        }

        if ($request->date_end) {
            $query->whereDate('clearing_date', '<=', $request->date_end);
        }

        // Code sorting
        if ($request->code_sort) {
            $query->orderBy('wht_clearing_no', $request->code_sort === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('updated_at', 'desc');
        }

        return Inertia::render('WithHoldingTaxClearing', [
            'wht_clearings' => $query->paginate(10)->withQueryString(),
            'searchTerm' => $request->search,
            'filters' => [
                'code_sort' => $request->code_sort,
                'date_start' => $request->date_start,
                'date_end' => $request->date_end,
            ],
            'broadcastChannel' => 'wht_clearings',
        ]);
    }

    public function clearWht(Request $request, WHTClearedNumberService $wHTClearedNumberService)
    {
        $validated = $request->validate([
            'wht_clearing_no' => 'required|string',
            'transaction_date' => 'required|date',
            'clearing_date' => 'required|date',
            'customer_code' => 'required|string',
            'customer_name' => 'required|string',
            'payment_details' => 'required|array',
            'payment_details.*.payment_no' => 'required|string',
            'payment_details.*.wht_no' => 'nullable|string',
            'payment_details.*.type' => 'required|string',
            'payment_details.*.document_no' => 'required|string',
            'payment_details.*.receipt_date' => 'required|date',
            'payment_details.*.amount' => 'required|numeric',
            'payment_details.*.status' => 'required|string',
            'payment_details.*.remarks' => 'nullable|string'
        ]);

        $notificationsController = new NotificationsController();


        $whtclrNo = DB::transaction(function () use ($validated, $request, $wHTClearedNumberService) {
            $whtClearingNo = $wHTClearedNumberService->generate();
            // Validate the payment number is unique (just in case)
            if (WHTCleared::where('wht_clearing_no', $whtClearingNo)->exists()) {
                throw ValidationException::withMessages([
                    'wht_clearing_no' => 'Error Please Try Again',
                ]);
            }
            WHTCleared::create([
                'wht_clearing_no' => $whtClearingNo,
                'transaction_date' => $validated['transaction_date'],
                'clearing_date' => $validated['clearing_date'],
                'customer_code' => $validated['customer_code'],
                'customer_name' => $validated['customer_name'],
                'created_by' => $request->user()->name,
            ]);

            $whtClearedItems = array_map(function ($item) use ($whtClearingNo) {
                return [
                    'wht_clearing_no' => $whtClearingNo,
                    'payment_no' => $item['payment_no'],
                    'wht_no' => $item['wht_no'] ?? null,
                    'type' => $item['type'],
                    'document_no' => $item['document_no'],
                    'receipt_date' => $item['receipt_date'],
                    'amount' => $item['amount'],
                    'status' => $item['status'],
                    'remarks' => $item['remarks'] ?? null,
                ];
            }, $validated['payment_details']);

            WHTClearedItems::insert($whtClearedItems);

            foreach ($validated['payment_details'] as $payment) {
                // Update the original payment status
                $pdQuery = PaymentDetails::where(function($query) use ($payment) {
                        $query->where('status', 'Floating')
                              ->orWhere('wht_status', 'Floating');
                    })
                    ->where('payment_no', $payment['payment_no'])
                    ->where ('type', $payment['type'])
                    ->where('document_no', $payment['document_no']);

                $pd = $pdQuery->lockForUpdate()->first();
                $wasFloatingWht = $pd && $pd->wht_status === 'Floating';

                if ($pd) {
                    $updateData = [];
                    if (Schema::connection('tenant')->hasColumn('payment_details', 'wht_clearing_date')) {
                        $updateData['wht_clearing_date'] = $validated['clearing_date'];
                    } else {
                        $updateData['clearing_date'] = $validated['clearing_date'];
                    }
                    
                    if ($pd->status === 'Floating') {
                        $updateData['status'] = $payment['status'];
                    }
                    if ($pd->wht_status === 'Floating') {
                        $updateData['wht_status'] = $payment['status'];
                    }
                    
                    $pd->update($updateData);
                }

                if ($payment['status'] === 'Cleared') {
                    if ($pd && $wasFloatingWht) {
                        $whtAmount = $pd->wht_amount ?? $payment['amount'];
                        if (
                            Schema::connection('tenant')->hasColumn('payment_details', 'amount_paid') &&
                            Schema::connection('tenant')->hasColumn('payment_details', 'amount') &&
                            Schema::connection('tenant')->hasColumn('payment_details', 'balance')
                        ) {
                            $expectedGross = max(0, ($pd->amount ?? 0) - ($pd->balance ?? 0));
                            $currentPaid = (float) ($pd->amount_paid ?? 0);

                            if (abs($currentPaid - $expectedGross) > 0.009 && abs(($currentPaid + $whtAmount) - $expectedGross) < 0.011) {
                                $pd->update([
                                    'amount_paid' => $currentPaid + $whtAmount,
                                    'balance' => max(0, ($pd->balance ?? 0) - $whtAmount),
                                ]);
                            }
                        }

                        $p = Payment::where('payment_no', $payment['payment_no'])->lockForUpdate()->first();
                        if ($p && Schema::connection('tenant')->hasColumn('payment', 'amount_paid')) {
                            $p->update([
                                'amount_paid' => ($p->amount_paid ?? 0) + $whtAmount,
                            ]);
                        }
                    }

                    $this->syncLedgerForPayment(
                        $validated['customer_code'],
                        $payment['document_no'],
                        $payment['type']
                    );
                }

                if ($payment['status'] === 'Cancelled') {
                    $cust = Customer::where('cus_code', $validated['customer_code'])
                        ->lockForUpdate()
                        ->first();

                    if ($cust && $pd) {
                        $cust->update([
                            'advanced_payment_balance' => $pd->advpy_amount_paid + $cust->advanced_payment_balance,
                        ]);
                    }
                }
            }
            return $whtClearingNo;
        });
        event(new NewCreated('wht_clearing'));
        event(new NewCreated('customerledger'));

        $notificationsController->index($request);

        $userIds = User::whereIn('role', ['Admin', 'Accounting'])
            ->pluck('id')
            ->unique();

        foreach ($userIds as $userId) {
            $channel = 'notification-update.' . Str::random(20);
            broadcast(new NotificationEvent($userId, $channel));
        }

        session()->put('whtclearing_number', $whtclrNo);
        return redirect()->back();
    }

    public function latest()
    {
        return DB::transaction(function () {
            $latestWhtCleared = WHTCleared::lockForUpdate()
                ->orderByDesc('wht_clearing_no')
                ->first();

            $nextNumber = $latestWhtCleared ? $latestWhtCleared->wht_clearing_no + 1 : 26000001;

            return response()->json([
                'next_clearing_no' => $nextNumber,
                'is_new_sequence' => !$latestWhtCleared
            ]);
        });
    }
}
