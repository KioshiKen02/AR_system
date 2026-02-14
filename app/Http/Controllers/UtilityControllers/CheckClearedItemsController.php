<?php

namespace App\Http\Controllers\UtilityControllers;

use App\Http\Controllers\Controller;
use App\Models\UtilityModels\CheckClearedItems;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CheckClearedItemsController extends Controller
{
    public function getByClearingNo(Request $request, $tenant, $clearing_no)
    {
        try {
            $clearing_no = trim($clearing_no);
            
            Log::info("CheckClearedItemsController: DB=" . DB::connection('tenant')->getDatabaseName() . ", ClearingNo=$clearing_no, Tenant=$tenant");

            $items = CheckClearedItems::on('tenant')
                ->where('clearing_no', $clearing_no)
                ->get();

            if ($items->isEmpty()) {
                Log::warning("No check cleared items found for $clearing_no");
                return response()->json([]);
            }

            return response()->json($items);
        } catch (\Exception $e) {
            Log::error("Error fetching check cleared items: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch check cleared items',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
