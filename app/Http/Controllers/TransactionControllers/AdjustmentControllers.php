<?php

namespace App\Http\Controllers\TransactionControllers;

use App\Events\NewCreated;
use App\Http\Controllers\Controller;
use App\Models\MasterfileModels\AdjustmentReasonSetup;
use App\Models\ReportModels\CustomerLedger;
use App\Models\TransactionModels\Adjustment;
use App\Models\TransactionModels\BeginningBalance;
use App\Models\TransactionModels\Invoice;
use App\Models\TransactionModels\PaymentDetails;
use App\Services\AdjustmentNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AdjustmentControllers extends Controller
{

    public function index(Request $request)
    {
        $query = Adjustment::query();

        // Search functionality
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('adjustment_no', 'like', '%' . $request->search . '%');
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
        if ($request->type_filtersApplyTo) {
            $types = is_array($request->type_filtersApplyTo)
                ? $request->type_filtersApplyTo
                : explode(',', $request->type_filtersApplyTo);
            $query->whereIn('apply_to', $types);
        }



        // Code sorting
        if ($request->code_sort) {
            $query->orderBy('adjustment_no', $request->code_sort === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('adjustment_no', 'desc');
        }

        return Inertia::render('Adjustment', [
            'adjustments' => $query->paginate(10)->withQueryString(),
            'searchTerm' => $request->search,
            'filters' => [
                'code_sort' => $request->code_sort,
                'type_filters' => $request->type_filters ?
                    (is_array($request->type_filters) ?
                        $request->type_filters :
                        explode(',', $request->type_filters)) :
                    [],
                'type_filtersApplyTo' => $request->type_filtersApplyTo ?
                    (is_array($request->type_filtersApplyTo) ?
                        $request->type_filtersApplyTo :
                        explode(',', $request->type_filtersApplyTo)) :
                    [],
                'date_start' => $request->date_start,
                'date_end' => $request->date_end,
            ],
            'broadcastChannel' => 'adjustments',
        ]);
    }

    public function addAdjustment(Request $request, AdjustmentNumberService $adjustmentNumberService)
    {

        $cl_type = $request->input('_cl_type', '');

        $validated = $request->validate(
            [
                'adjustment_no' => ['required', 'string'],
                'receipt_date' => ['required', 'date', 'before_or_equal:today'],
                'transaction_date' => ['required', 'date', 'before_or_equal:today'],
                'customer_code' => ['required', 'string'],
                'name' => ['required', 'string'],
                'type' => ['required', 'string'],
                'apply_to' => ['required', 'in:Sales Invoice,Other Income,Beginning Balance'],
                'invoice_no' => ['required', 'string'],
                'balance' => ['required', 'numeric'],
                'adjustment_reason' => ['required', 'string'],
                'particulars' => ['required', 'string'],
                'amount' => [
                    'required',
                    'numeric',
                    function ($attribute, $value, $fail) use ($request) {
                        if ($request->type === 'Negative' && $value > $request->balance) {
                            $fail('Amount Should Not Be Greater Than Available Balance');
                        }
                    },
                ],
            ],
            [
                'adjustment_no.required' => 'Adjustment Number Required',
                'receipt_date.required' => 'Date Required',
                'receipt_date.date' => 'Date Must Be Valid Date',
                'receipt_date.before_or_equal' => 'Date Cannot Be Future',
                'transaction_date.required' => 'Date Required',
                'transaction_date.date' => 'Date Must Be Valid Date',
                'transaction_date.before_or_equal' => 'Date Cannot Be Future',
                'customer_code.required' => 'Customer Code Required',
                'name.required' => 'Customer Name Required',
                'type.required' => 'Type Required',
                'apply_to.required' => 'Apply To Required',
                'apply_to.in' => 'Apply To Required',
                'invoice_no.required' => 'Document Number Required',
                'adjustment_reason.required' => 'Adjustment Reason Required',
                'particulars.required' => 'Particular Required',
                'amount.required' => 'Amount Required',
                'amount.numeric' => 'Amount Must Be Valid number',
            ]
        );

        $adjNo = DB::transaction(function () use ($validated, $request, $cl_type, $adjustmentNumberService) {
            $adjustmentNumber = $adjustmentNumberService->generate();

            if (Adjustment::where('adjustment_no', $adjustmentNumber)->exists()) {
                throw ValidationException::withMessages([
                    'general' => 'Error Please Try Again',
                ]);
            }
            if ($cl_type === 'Beginning Balance') {
                $formattedType = 'BG';

                $ledger = CustomerLedger::where('invoice_number', $validated['invoice_no'])->where('type', $formattedType)->first();
                
                if ($ledger) {
                    $ledgerCurrentAdjustedAmount = $ledger->adjusted_amount ?? 0;
                    $ledgerCurrentRunningBalance = $ledger->running_balance;

                    if (strtolower($validated['type']) === 'positive') {
                        $newRunningBalance = $ledgerCurrentRunningBalance + $validated['amount'];
                        $newAdjustedAmount = $ledgerCurrentAdjustedAmount + $validated['amount'];
                    } elseif (strtolower($validated['type']) === 'negative') {
                        $newRunningBalance = max($ledgerCurrentRunningBalance - $validated['amount'], 0);
                        $newAdjustedAmount = $ledgerCurrentAdjustedAmount - $validated['amount'];
                    }

                    $ledger->update([
                        'running_balance' => $newRunningBalance,
                        'adjusted_amount' => $newAdjustedAmount
                    ]);
                }

                $dbData = collect($validated)
                    ->except(['_cl_type'])
                    ->all();
                $dbData['adjustment_no'] = $adjustmentNumber;
                $dbData['created_by'] = $request->user()->name;
                Adjustment::create($dbData);

                return $adjustmentNumber;
            }

            $ledger = CustomerLedger::where('invoice_number', $validated['invoice_no'])->where('type', $cl_type)->firstOrFail();

            $floatingPaid = PaymentDetails::where('document_no', $ledger->invoice_number)
                ->where('type', $ledger->type)
                ->where('status', 'Floating')
                ->sum('amount_paid');

            if (strtolower($validated['type']) === 'negative' && ($ledger->running_balance - $floatingPaid) - $validated['amount'] < 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Amount Exceeds Available Balance. Selected Document Has A Total Floating Payment of ' . $floatingPaid,
                ]);
            }

            $currentRunningBalance = $ledger->running_balance;
            $currentAmount = $ledger->adjusted_amount;

            if (strtolower($validated['type']) === 'positive') {
                $newAmount = $currentRunningBalance + $validated['amount'];
                $newAdjustmentAmount = $validated['amount'] + $currentAmount;
            } elseif (strtolower($validated['type']) === 'negative') {
                $newAmount = $currentRunningBalance - $validated['amount'];
                $newAmount = max($newAmount, 0);
                $newAdjustmentAmount = $currentAmount - $validated['amount'];
            }

            $ledger->update([
                'running_balance' => $newAmount,
                'adjusted_amount' => $newAdjustmentAmount
            ]);

            $dbData = collect($validated)
                ->except(['_cl_type'])
                ->all();
            $dbData['adjustment_no'] = $adjustmentNumber;
            $dbData['created_by'] = $request->user()->name;
            Adjustment::create($dbData);

            if ($cl_type === 'Sales Invoice') {
                //DYNAMIC API LINK
                // Use App Setting of the authenticated user
                $userAppSetting = $request->user()->appSetting;
                
                // We need a mapping between App Settings (DB config) and the Centralized Invoicing API URLs.
                // The switch case below hardcodes URLs based on app name. 
                // We should ideally store these URLs in the AppSetting model or derive them.
                // For now, we will try to use the user's app setting NAME to switch, 
                // effectively replacing config('app.name') with $userAppSetting->app_name.
                
                $appName = $userAppSetting ? $userAppSetting->app_name : config('app.name');
                
                switch ($appName) {
                    case 'Bilar Breeder Local':
                        $baseUrl = 'http://172.16.43.148/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=13';
                        break;
                    case 'Bilar Breeder':
                        $baseUrl = 'http://172.16.220.1:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=13';
                        break;
                    case 'Gp Jagna':
                        $baseUrl = 'http://172.16.220.1:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=50';
                        break;
                    case 'Ice Plant':
                        $baseUrl = 'http://172.16.184.49:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=25';
                        break;
                    case 'Peanut Kisses':
                        $baseUrl = 'http://172.16.184.49:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=26';
                        break;
                    case 'Cortes Poultry':
                        $baseUrl = 'http://172.16.192.68:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=12';
                        break;
                    case 'Cortes Piggery':
                        $baseUrl = 'http://172.16.192.68:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=11';
                        break;
                    case 'Canhayupon Breeder':
                        $baseUrl = 'http://172.16.220.223:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=15';
                        break;
                    case 'Bilar Hatchery':
                        $baseUrl = 'http://172.16.219.200:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=14';
                        break;
                    case 'Lapsaon Breeder':
                        $baseUrl = 'http://172.16.220.222:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=16';
                        break;
                    case 'Rizal Breeder':
                        $baseUrl = 'http://172.16.217.11:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=43';
                        break;
                    // ubay server 
                    case 'Feedmill':
                        $baseUrl = 'http:// 172.16.18.27/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=19';
                        break;
                    case 'Growout':
                        $baseUrl = 'http:// 172.16.18.27/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=20';
                        break;
                    case 'Cortes Fertilizer':
                        $baseUrl = 'http:// 172.16.18.27/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=42';
                        break;
                    case 'Ubay Fertilizer':
                        $baseUrl = 'http:// 172.16.18.27/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=22';
                        break;
                    case 'Piggery Untaga':
                        $baseUrl = 'http:// 172.16.18.27/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=23';
                        break;
                    case 'Demo Farm':
                        $baseUrl = 'http:// 172.16.18.27/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=21';
                        break;
                    case 'Dressing Plant':
                        $baseUrl = 'http:// 172.16.18.27/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=17';
                        break;
                    case 'Farmers Market':
                        $baseUrl = 'http:// 172.16.18.27/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=41';
                        break;
                    case 'Meat Processing':
                        $baseUrl = 'http:// 172.16.18.27/centralized-invoicingg/sales-invoice/update/adjustment-sales?bu=46';
                        break;
                    case 'Rendering':
                        $baseUrl = 'http:// 172.16.18.27/centralized-invoicingg/sales-invoice/update/adjustment-sales?bu=18';
                        break;
                    case 'Ar System':
                        // Fallback or specific logic for Ar System if needed
                        $baseUrl = null; 
                        break;
                    default:
                        throw new \Exception("Unknown app name: {$appName}");
                }
                
                if ($baseUrl) {
                    $url = $baseUrl;
                    try {
                        $response = Http::timeout(3)
                            ->retry(2, 200)
                            ->withHeaders([
                                'Accept' => 'application/json',
                            ])->post($url, [
                                'adj_sales' => (string) $newAdjustmentAmount,
                                'tds_no' => $validated['invoice_no'],
                            ]);
                        if (!$response->successful()) {
                            Log::error('Adjustment Sales API Failed', [
                                'app_name' => $appName,
                                'url' => $url,
                                'status' => $response->status(),
                                'response_body' => $response->body(),
                                'response_json' => $response->json(),
                                'payload' => [
                                    'adj_sales' => (string) $newAdjustmentAmount,
                                    'tds_no'    => $validated['invoice_no'],
                                ],
                            ]);
                        }
                    } catch (\Throwable $e) {
                        Log::error('Adjustment Sales API Failed', [
                            'app_name' => $appName,
                            'url' => $url,
                            'status' => null,
                            'response_body' => null,
                            'response_json' => null,
                            'payload' => [
                                'adj_sales' => (string) $newAdjustmentAmount,
                                'tds_no'    => $validated['invoice_no'],
                            ],
                            'exception' => $e->getMessage(),
                        ]);
                    }
                }
            }
            return $adjustmentNumber;
        });

        event(new NewCreated('adjustment'));
        event(new NewCreated('customerledger'));

        session()->put('adjustment_number', $adjNo);
        return redirect()->back();
    }

    public function destroy($id)
    {
        $adj = Adjustment::findorFail($id);
        $adj->delete();
    }

    public function latest()
    {
        //return Adjustment::orderByDesc('id')->value('adjustment_no'); // returns "26000001" or null
        return DB::transaction(function () {
            $latestAdjustment = Adjustment::withTrashed()
                ->lockForUpdate() // Prevent concurrent access
                ->orderByDesc('adjustment_no')
                ->first();

            $nextNumber = $latestAdjustment ? $latestAdjustment->adjustment_no + 1 : 26000001;

            return response()->json([
                'next_adjustment_no' => $nextNumber,
                'is_new_sequence' => !$latestAdjustment
            ]);
        });
    }

    public function getAdjustmentReasonSetup(Request $request)
    {
        $apply_to = $request->input('apply_to');

        $types = AdjustmentReasonSetup::where('type', $apply_to)->pluck('reason_name');
        return response()->json($types);
    }
}
