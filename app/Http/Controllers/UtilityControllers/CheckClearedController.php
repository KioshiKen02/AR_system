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
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Illuminate\Support\Str;

class CheckClearedController extends Controller
{
    private function syncLedgerForPayment(string $customerCode, string $documentNo, string $type): void
    {
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

        $totalPaid = (float) PaymentDetails::on('tenant')
            ->where('customer_code', $customerCode)
            ->where('document_no', $documentNo)
            ->where('type', $type)
            ->whereIn('status', ['Paid', 'Cleared'])
            ->sum('amount_paid');

        $baseAmount = (float) ($ledger->adjusted_amount ?? $ledger->amount ?? 0);
        $overage = (float) ($ledger->overage ?? 0);
        $shrinkage = (float) ($ledger->shrinkage ?? 0);
        $returnAmount = (float) ($ledger->return ?? 0);

        $runningBalance = max(
            0,
            $baseAmount - $totalPaid + $overage - $shrinkage - $returnAmount
        );

        $ledger->update([
            'amount_paid' => $totalPaid,
            'running_balance' => $runningBalance,
        ]);
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
