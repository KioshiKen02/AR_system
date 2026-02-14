<?php

namespace App\Http\Controllers\UtilityControllers;

use App\Http\Controllers\Controller;
use App\Models\UtilityModels\CancelPaymentItems;
use App\Models\UtilityModels\CancelPayment;
use App\Models\TransactionModels\PaymentDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CancelPaymentItemsController extends Controller
{
    public function getByCancellationNo(Request $request, $tenant, $cancellation_no)
    {
        try {
            // Trim the cancellation number to avoid whitespace issues
            $cancellation_no = trim($cancellation_no);
            
            // Log for debugging
            Log::info("CancelPaymentItemsController: DB=" . DB::connection('tenant')->getDatabaseName() . ", CancellationNo=$cancellation_no, Tenant=$tenant");

            $items = CancelPaymentItems::on('tenant')
                ->where('cancellation_no', $cancellation_no)
                ->get();
            
            if ($items->isEmpty()) {
                Log::warning("No items found for $cancellation_no in " . DB::connection('tenant')->getDatabaseName());
                // Fallback: try to reconstruct from original payment details using the parent cancellation record
                $parent = CancelPayment::on('tenant')
                    ->where('cancellation_no', $cancellation_no)
                    ->first();

                if ($parent) {
                    $payments = collect();

                    if (!empty($parent->payment_no)) {
                        Log::info("Falling back to payment_no: " . $parent->payment_no);
                        $payments = PaymentDetails::on('tenant')
                            ->where('payment_no', $parent->payment_no)
                            ->get();
                    } elseif (!empty($parent->document_no)) {
                        Log::info("Falling back to document_no: " . $parent->document_no);
                        $payments = PaymentDetails::on('tenant')
                            ->where('document_no', $parent->document_no)
                            ->get();
                    }

                    if ($payments->isNotEmpty()) {
                        $reconstructed = $payments->map(function ($p) use ($cancellation_no) {
                            return [
                                'id' => $p->id,
                                'cancellation_no' => $cancellation_no,
                                'document_no' => $p->document_no,
                                'payment_no' => $p->payment_no,
                                'receipt_date' => $p->payment_receipt_date ?? $p->payment_date ?? $p->document_date,
                                'payment_type' => $p->payment_type,
                                'amount' => $p->amount_paid,
                                'amount_paid' => $p->amount_paid, // Map both to be safe
                                'remarks' => $p->remarks ?? 'Cancelled',
                            ];
                        });

                        Log::info("Reconstructed " . $reconstructed->count() . " items");
                        return response()->json($reconstructed);
                    }
                }

                return response()->json([]);
            }

            // Map amount to amount_paid for consistency if it's missing in some records
            $mappedItems = $items->map(function ($item) {
                $data = $item->toArray();
                if (!isset($data['amount_paid']) && isset($data['amount'])) {
                    $data['amount_paid'] = $data['amount'];
                }
                return $data;
            });

            return response()->json($mappedItems);
        } catch (\Exception $e) {
            Log::error("Error fetching cancel payment items: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
