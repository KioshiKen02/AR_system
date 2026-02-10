<?php

namespace App\Http\Controllers\TransactionControllers;

use App\Http\Controllers\Controller;
use App\Models\TransactionModels\Invoice;
use App\Models\TransactionModels\InvoiceItem;
use Illuminate\Http\Request;

class InvoiceItemController extends Controller
{

    public function getByInvoiceNo(Request $request, $invoice_no)
    {
        try {
            // Fix for route parameter mapping in tenant-prefixed routes
            $targetId = $request->route('invoice_no');
            if (!$targetId) {
                 $targetId = $invoice_no;
            }
            $items = InvoiceItem::where('invoice_no', $targetId)
                ->select([
                    'item_code',
                    'item_name',
                    'packing',
                    'quantity',
                    'price',
                    'amount'
                ])
                ->get();

            return response()->json($items);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch invoice items',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
