<?php

namespace App\Http\Controllers\UtilityControllers;

use App\Events\NewCreated;
use App\Http\Controllers\Controller;
use App\Models\MasterfileModels\Customer;
use App\Models\ReportModels\CustomerLedger;
use App\Models\UtilityModels\CancelPayment;
use App\Models\UtilityModels\CancelPaymentItems;
use App\Services\CancelPaymentNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CancelPaymentController extends Controller
{
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
                    ->update([
                        'status' => 'Cancelled',
                        'remarks' => 'Cancelled',
                    ]);
                $paymentItemsAdvPy += $payment['advpy_amount_paid'];
            }
            $ledger = CustomerLedger::on('tenant')->where('invoice_number', $validated['document_no'])->where('type', $validated['type'])->firstOrFail();

            $totalPaid = (float) DB::connection('tenant')->table('payment_details')
                ->where('document_no', $validated['document_no'])
                ->where('type', $validated['type'])
                ->where('status', '!=', 'Cancelled')
                ->sum('amount_paid');

            $baseAdjusted = (float) ($ledger->adjusted_amount ?? 0);
            $overage = (float) ($ledger->overage ?? 0);
            $shrinkage = (float) ($ledger->shrinkage ?? 0);
            $return = (float) ($ledger->return ?? 0);
            $newRunningBalance = max(
                0,
                $baseAdjusted - $totalPaid + ($overage - $shrinkage) - $return
            );

            $ledger->update([
                'running_balance' => $newRunningBalance,
                'amount_paid' => $totalPaid,
            ]);

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
                    ->update([
                        'status' => 'Cancelled',
                        'remarks' => 'Cancelled',
                    ]);

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
                $ledger = CustomerLedger::on('tenant')
                    ->where('invoice_number', $ref['document_no'])
                    ->where('type', $ref['type'])
                    ->first();

                if (!$ledger) {
                    continue;
                }

                $totalPaid = (float) DB::connection('tenant')->table('payment_details')
                    ->where('document_no', $ref['document_no'])
                    ->where('type', $ref['type'])
                    ->where('status', '!=', 'Cancelled')
                    ->sum('amount_paid');

                $baseAdjusted = (float) ($ledger->adjusted_amount ?? 0);
                $overage = (float) ($ledger->overage ?? 0);
                $shrinkage = (float) ($ledger->shrinkage ?? 0);
                $return = (float) ($ledger->return ?? 0);
                $newRunningBalance = max(
                    0,
                    $baseAdjusted - $totalPaid + ($overage - $shrinkage) - $return
                );

                $ledger->update([
                    'running_balance' => $newRunningBalance,
                    'amount_paid' => $totalPaid,
                ]);
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
