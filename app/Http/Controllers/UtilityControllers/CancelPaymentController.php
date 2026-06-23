<?php

namespace App\Http\Controllers\UtilityControllers;

use App\Events\NewCreated;
use App\Http\Controllers\Controller;
use App\Models\MasterfileModels\Customer;
use App\Models\ReportModels\CustomerLedger;
use App\Models\TransactionModels\PaymentDetails;
use App\Models\UtilityModels\CancelPayment;
use App\Models\UtilityModels\CancelPaymentItems;
use App\Services\CancelPaymentNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CancelPaymentController extends Controller
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

        $rows = PaymentDetails::on('tenant')
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

    private function syncLedgerAndPaymentDetails(string $documentNo, string $type): void
    {
        $hasLedgerWhtAmount = Schema::connection('tenant')->hasColumn('customer_ledger', 'wht_amount');
        $hasLedgerOverpaymentAmount = Schema::connection('tenant')->hasColumn('customer_ledger', 'overpayment_amount');
        $hasPaymentDetailsWhtColumns = Schema::connection('tenant')->hasColumn('payment_details', 'wht_amount')
            && Schema::connection('tenant')->hasColumn('payment_details', 'wht_status');

        $ledger = CustomerLedger::on('tenant')
            ->where('invoice_number', $documentNo)
            ->where('type', $type)
            ->lockForUpdate()
            ->firstOrFail();

        $rows = PaymentDetails::on('tenant')
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

        $baseAdjusted = (float) ($ledger->adjusted_amount ?? 0);
        $overage = (float) ($ledger->overage ?? 0);
        $shrinkage = (float) ($ledger->shrinkage ?? 0);
        $return = (float) ($ledger->return ?? 0);
        $debit = $baseAdjusted + ($overage - $shrinkage) - $return;
        $totalCredited = $totalPaid + $totalWhtApplied;
        $newRunningBalance = max(0, $debit - $totalCredited);
        $overpaymentAmount = max(0, $totalCollectiblePaid - $debit);

        $ledgerUpdate = [
            'running_balance' => $newRunningBalance,
            'amount_paid' => $totalPaid,
        ];

        if ($hasLedgerWhtAmount) {
            $ledgerUpdate['wht_amount'] = $totalWhtApplied;
        }

        if ($hasLedgerOverpaymentAmount) {
            $ledgerUpdate['overpayment_amount'] = $overpaymentAmount;
        }

        $ledger->update($ledgerUpdate);
        $this->syncPaymentDetailOverpayment($documentNo, $type, $debit);
    }

    public function index(Request $request, $tenant)
    {
        $query = CancelPayment::on('tenant');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('customer_name', 'like', '%' . $request->search . '%')
                    ->orWhere('document_no', 'like', '%' . $request->search . '%');
            });
        }

        $query->orderBy('created_at', 'desc');

        return Inertia::render('CancelPayment', [
            'cancel_payments' => $query->paginate(10)->withQueryString(),
            'searchTerm' => $request->search,
            'broadcastChannel' => 'cancel_payments',
        ]);
    }

    public function cancelPaymentUsingDocumentNo(Request $request, $tenant, CancelPaymentNumberService $cancelPaymentNumberService)
    {
        $validated = $request->validate([
            'document_no' => 'required|string',
            'type' => 'required|string',
            'customer_code' => 'required|string',
            'customer_name' => 'required|string',
            'payment_details' => 'required|array',
            'payment_details.*.id' => 'required|numeric',
            'payment_details.*.payment_no' => 'required|string',
            'payment_details.*.document_no' => 'nullable|string',
            'payment_details.*.receipt_date' => 'required|date',
            'payment_details.*.payment_type' => 'required|string',
            'payment_details.*.advpy_amount_paid' => 'required|numeric',
            'payment_details.*.amount' => 'required|numeric',
            'payment_details.*.remarks' => 'nullable|string'
        ]);

        DB::connection('tenant')->transaction(function () use ($validated, $request, $cancelPaymentNumberService) {
            $hasWhtStatus = Schema::connection('tenant')->hasColumn('payment_details', 'wht_status');
            $cancellationNo = $cancelPaymentNumberService->generate();
            // Validate the payment number is unique (just in case)
            if (CancelPayment::on('tenant')->where('cancellation_no', $cancellationNo)->exists()) {
                throw ValidationException::withMessages([
                    'cancellation_no' => 'Error Please Try Again',
                ]);
            }
            CancelPayment::on('tenant')->create([
                'cancellation_no' => $cancellationNo,
                'document_no' => $validated['document_no'],
                'type' => $validated['type'],
                'customer_code' => $validated['customer_code'],
                'customer_name' => $validated['customer_name'],
                'created_by' => $request->user()->name,
            ]);

            $cancelPaymentItems = array_map(function ($item) use ($cancellationNo) {
                return [
                    'cancellation_no' => $cancellationNo,
                    'payment_no' => $item['payment_no'],
                    'document_no' => $item['document_no'] ?? null,
                    'receipt_date' => $item['receipt_date'],
                    'payment_type' => $item['payment_type'],
                    'amount' => $item['amount'],
                    'remarks' => 'Cancelled',
                ];
            }, $validated['payment_details']);

            CancelPaymentItems::on('tenant')->insert($cancelPaymentItems);

            $paymentItemsAdvPy = 0;
            foreach ($validated['payment_details'] as $payment) {
                // Update the original payment status
                DB::connection('tenant')->table('payment_details')
                    ->where('id', $payment['id'])
                    ->update(array_filter([
                        'status' => 'Cancelled',
                        'wht_status' => $hasWhtStatus ? 'Cancelled' : null,
                        'remarks' => 'Cancelled',
                    ], fn ($v) => $v !== null));
                $paymentItemsAdvPy += $payment['advpy_amount_paid'];
            }
            $this->syncLedgerAndPaymentDetails($validated['document_no'], $validated['type']);

            $cust = Customer::on('tenant')->where('cus_code', $validated['customer_code'])
                ->lockForUpdate()
                ->first();

            if (!$cust) {
                 Log::error("CancelPayment(DocNo): Customer not found", ['customer_code' => $validated['customer_code']]);
                 throw ValidationException::withMessages([
                     'customer_code' => 'Customer record not found for code: ' . $validated['customer_code'],
                 ]);
            }

            $cust->update([
                'advanced_payment_balance' => $paymentItemsAdvPy + $cust->advanced_payment_balance,
            ]);

            event(new NewCreated('cancel_payment'));
        });
    }

    public function cancelPaymentUsingPaymentNo(Request $request, $tenant, CancelPaymentNumberService $cancelPaymentNumberService)
    {
        $validated = $request->validate([
            'payment_no' => 'required|string',
            'type' => 'required|string',
            'customer_code' => 'required|string',
            'customer_name' => 'required|string',
            'payment_details' => 'required|array',
            'payment_details.*.id' => 'required|numeric',
            'payment_details.*.document_no' => 'required|string',
            'payment_details.*.payment_no' => 'nullable|string',
            'payment_details.*.receipt_date' => 'required|date',
            'payment_details.*.payment_type' => 'required|string',
            'payment_details.*.type' => 'required|string',
            'payment_details.*.advpy_amount_paid' => 'required|numeric',
            'payment_details.*.amount' => 'required|numeric',
            'payment_details.*.remarks' => 'nullable|string'
        ]);

        DB::connection('tenant')->transaction(function () use ($validated, $request, $cancelPaymentNumberService) {
            $hasWhtStatus = Schema::connection('tenant')->hasColumn('payment_details', 'wht_status');
            $cancellationNo = $cancelPaymentNumberService->generate();
            // Validate the payment number is unique (just in case)
            if (CancelPayment::on('tenant')->where('cancellation_no', $cancellationNo)->exists()) {
                throw ValidationException::withMessages([
                    'cancellation_no' => 'Error Please Try Again',
                ]);
            }

            // Fetch customer once and validate existence
            $cust = Customer::on('tenant')->where('cus_code', $validated['customer_code'])
                ->lockForUpdate()
                ->first();

            if (!$cust) {
                Log::error("CancelPayment: Customer not found", ['customer_code' => $validated['customer_code']]);
                throw ValidationException::withMessages([
                    'customer_code' => 'Customer record not found for code: ' . $validated['customer_code'],
                ]);
            }

            CancelPayment::on('tenant')->create([
                'cancellation_no' => $cancellationNo,
                'payment_no' => $validated['payment_no'],
                'type' => $validated['type'],
                'customer_code' => $validated['customer_code'],
                'customer_name' => $validated['customer_name'],
                'created_by' => $request->user()->name,
            ]);

            $cancelPaymentItems = array_map(function ($item) use ($cancellationNo) {
                return [
                    'cancellation_no' => $cancellationNo,
                    'document_no' => $item['document_no'],
                    'payment_no' => $item['payment_no'] ?? null,
                    'receipt_date' => $item['receipt_date'],
                    'payment_type' => $item['payment_type'],
                    'amount' => $item['amount'],
                    'remarks' => 'Cancelled',
                ];
            }, $validated['payment_details']);

            CancelPaymentItems::on('tenant')->insert($cancelPaymentItems);

            $affectedLedgers = [];
            foreach ($validated['payment_details'] as $payment) {
                $paymentRow = DB::connection('tenant')->table('payment_details')->where('id', $payment['id'])->first();

                if (!$paymentRow) {
                    continue; // skip if no payment found (safety check)
                }

                // Update the original payment status
                DB::connection('tenant')->table('payment_details')
                    ->where('id', $payment['id'])
                    ->update(array_filter([
                        'status' => 'Cancelled',
                        'wht_status' => $hasWhtStatus ? 'Cancelled' : null,
                        'remarks' => 'Cancelled',
                    ], fn ($v) => $v !== null));

                $cust->update([
                    'advanced_payment_balance' => $payment['advpy_amount_paid'] + $cust->advanced_payment_balance,
                ]);

                $affectedLedgers[] = [
                    'document_no' => $payment['document_no'],
                    'type' => $payment['type'],
                ];
            }

            $affectedLedgers = collect($affectedLedgers)
                ->unique(fn ($x) => $x['document_no'] . '|' . $x['type'])
                ->values();

            foreach ($affectedLedgers as $ref) {
                $this->syncLedgerAndPaymentDetails($ref['document_no'], $ref['type']);
            }


            event(new NewCreated('cancel_payment'));
        });
    }

    public function latest(Request $request, $tenant)
    {
        return DB::connection('tenant')->transaction(function () {
            $latestCancellationNo = CancelPayment::on('tenant')->lockForUpdate()
                ->orderByDesc('cancellation_no')
                ->first();

            $nextNumber = $latestCancellationNo ? $latestCancellationNo->cancellation_no + 1 : 26000001;

            return response()->json([
                'next_clearing_no' => $nextNumber,
                'is_new_sequence' => !$latestCancellationNo
            ]);
        });
    }
}
