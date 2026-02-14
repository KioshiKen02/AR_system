<?php

namespace App\Http\Controllers\UtilityControllers;

use App\Http\Controllers\Controller;
use App\Models\UtilityModels\WHTClearedItems;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class WHTClearedItemsController extends Controller
{
    public function getByWhtClearingNo(Request $request, $tenant, $wht_clearing_no)
    {
        try {
            $wht_clearing_no = trim($wht_clearing_no);
            
            Log::info("WHTClearedItemsController: DB=" . DB::connection('tenant')->getDatabaseName() . ", WhtClearingNo=$wht_clearing_no, Tenant=$tenant");

            $items = WHTClearedItems::on('tenant')
                ->where('wht_clearing_no', $wht_clearing_no)
                ->get();

            if ($items->isEmpty()) {
                Log::warning("No WHT cleared items found for $wht_clearing_no");
                return response()->json([]);
            }

            return response()->json($items);
        } catch (\Exception $e) {
            Log::error("Error fetching WHT cleared items: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch WHT cleared items',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
