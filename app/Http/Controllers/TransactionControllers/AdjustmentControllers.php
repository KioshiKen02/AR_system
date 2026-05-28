<?php

namespace App\Http\Controllers\TransactionControllers;

use App\Events\NewCreated;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
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
                'adjustment_code' => ['nullable', 'string'],
                'particulars' => ['required', 'string'],
                'amount' => [
                    'required',
                    'numeric',
                    function ($attribute, $value, $fail) use ($request) {
                        $amount = round((float) $value, 2);
                        $balance = round((float) $request->balance, 2);
                        if ($request->type === 'Negative' && $amount > $balance) {
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

                $ledger = CustomerLedger::where('invoice_number', $validated['invoice_no']) 
                    ->where('type', $formattedType) 
                    ->firstOrFail();

                $beginningBalance = BeginningBalance::where('beginningbalance_no', $validated['invoice_no'])->first();

                if ($beginningBalance && $ledger->amount != $beginningBalance->balance_amount) {
                    $ledger->update(['amount' => $beginningBalance->balance_amount]);
                    $ledger->refresh();
                }

                $existingPositive = Adjustment::where('invoice_no', $validated['invoice_no'])
                    ->where('apply_to', 'Beginning Balance')
                    ->where('type', 'Positive')
                    ->sum('amount');

                $existingNegative = Adjustment::where('invoice_no', $validated['invoice_no'])
                    ->where('apply_to', 'Beginning Balance')
                    ->where('type', 'Negative')
                    ->sum('amount');

                $amount = $ledger->amount;
                $currentAdjusted = $amount + $existingPositive - $existingNegative;
                $syncedRunningBalance = $currentAdjusted - $ledger->amount_paid;

                $floatingPaid = PaymentDetails::where('document_no', $ledger->invoice_number)
                    ->where('type', $ledger->type)
                    ->whereIn('status', ['Floating', 'Paid'])
                    ->sum('amount_paid');

                $newPositive = $existingPositive;
                $newNegative = $existingNegative;

                if (strtolower($validated['type']) === 'positive') {
                    $newPositive += $validated['amount'];
                } elseif (strtolower($validated['type']) === 'negative') {
                    $prospectiveNegative = $existingNegative + $validated['amount'];
                    $prospectiveAdjusted = $amount + $newPositive - $prospectiveNegative;

                    if (round((float) $prospectiveAdjusted - (float) $floatingPaid, 2) < 0) {
                        throw ValidationException::withMessages([
                            'amount' => 'Amount Exceeds Available Balance. Selected Document Has A Total Payment (Paid + Floating) of ' . number_format($floatingPaid, 2),
                        ]);
                    }

                    $newNegative = $prospectiveNegative;
                }

                $newAdjustedAmount = $amount + $newPositive - $newNegative;
                $newRunningBalance = $newAdjustedAmount - $ledger->amount_paid;

                $ledger->update([
                    'adjusted_amount' => $newAdjustedAmount,
                    'positive_adjustment_amount' => $newPositive,
                    'negative_adjustment_amount' => $newNegative,
                    'running_balance' => $newRunningBalance,
                ]);

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

            $availableBalance = round((float) $ledger->running_balance - (float) $floatingPaid, 2);
            $adjustmentAmount = round((float) $validated['amount'], 2);
            if (strtolower($validated['type']) === 'negative' && round($availableBalance - $adjustmentAmount, 2) < 0) {
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
                
                $baseUrl = $this->adjustmentSalesBaseUrlForApp($appName);
                if ($baseUrl === null && $appName !== 'Ar System') {
                    throw new \Exception("Unknown app name: {$appName}");
                }
                
                if ($baseUrl) {
                    $url = preg_replace('/^(https?:\/\/)\s+/', '$1', trim($baseUrl));
                    if (!filter_var($url, FILTER_VALIDATE_URL)) {
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
                            'exception' => 'Invalid URL',
                        ]);
                        return $adjustmentNumber;
                    }
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

    public function syncAdjustmentSales(Request $request, $adjustment)
    {
        $invoiceNo = $request->input('invoice_no');

        $adjustment = Adjustment::withTrashed()->find($adjustment);
        if ($adjustment) {
            $invoiceNo = $adjustment->invoice_no;
            if ($adjustment->apply_to !== 'Sales Invoice') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only Sales Invoice adjustments can be synced.',
                ], 422);
            }
        }

        if (!$invoiceNo) {
            return response()->json([
                'success' => false,
                'message' => 'invoice_no is required.',
            ], 422);
        }

        $ledger = CustomerLedger::where('invoice_number', $invoiceNo)
            ->where('type', 'Sales Invoice')
            ->first();

        if (!$ledger) {
            return response()->json([
                'success' => false,
                'message' => 'Customer ledger not found for this Sales Invoice.',
            ], 404);
        }

        $userAppSetting = $request->user()->appSetting;
        $appName = $userAppSetting ? $userAppSetting->app_name : config('app.name');

        $currentSettingId = config('tenant.current_app_setting_id');
        if ($currentSettingId) {
            $currentSetting = AppSetting::on('mysql')->find($currentSettingId);
            if ($currentSetting?->app_name) {
                $appName = $currentSetting->app_name;
            }
        }
        if (is_string($appName) && strcasecmp($appName, 'ar system') === 0) {
            $appName = 'Ar System';
        }

        $tenant = (string) $request->route('tenant');
        if ((!$userAppSetting || $appName === 'Ar System') && $tenant !== '') {
            $appNameFromTenant = $this->appNameFromTenant($tenant);
            if ($appNameFromTenant) {
                $appName = $appNameFromTenant;
            }
        }

        $baseUrl = $this->adjustmentSalesBaseUrlForApp($appName);
        if ($baseUrl === null && $appName !== 'Ar System') {
            return response()->json([
                'success' => false,
                'message' => "Unknown app name: {$appName}",
            ], 422);
        }

        if (!$baseUrl) {
            return response()->json([
                'success' => false,
                'message' => 'No sync URL configured for this app.',
            ], 422);
        }

        $url = preg_replace('/^(https?:\/\/)\s+/', '$1', trim($baseUrl));
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            Log::error('Adjustment Sales API Failed', [
                'app_name' => $appName,
                'url' => $url,
                'status' => null,
                'response_body' => null,
                'response_json' => null,
                'payload' => [
                    'adj_sales' => (string) ($ledger->adjusted_amount ?? 0),
                    'tds_no'    => $invoiceNo,
                ],
                'exception' => 'Invalid URL',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid sync URL.',
            ], 422);
        }

        try {
            $response = Http::timeout(5)
                ->retry(2, 200)
                ->withHeaders([
                    'Accept' => 'application/json',
                ])->post($url, [
                    'adj_sales' => (string) ($ledger->adjusted_amount ?? 0),
                    'tds_no' => $invoiceNo,
                ]);

            if (!$response->successful()) {
                Log::error('Adjustment Sales API Failed', [
                    'app_name' => $appName,
                    'url' => $url,
                    'status' => $response->status(),
                    'response_body' => $response->body(),
                    'response_json' => $response->json(),
                    'payload' => [
                        'adj_sales' => (string) ($ledger->adjusted_amount ?? 0),
                        'tds_no'    => $invoiceNo,
                    ],
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Sync failed. Please check logs for details.',
                ], 502);
            }

            return response()->json([
                'success' => true,
                'message' => 'Sync successful.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Adjustment Sales API Failed', [
                'app_name' => $appName,
                'url' => $url,
                'status' => null,
                'response_body' => null,
                'response_json' => null,
                'payload' => [
                    'adj_sales' => (string) ($ledger->adjusted_amount ?? 0),
                    'tds_no'    => $invoiceNo,
                ],
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Sync failed due to a network/connection error.',
            ], 502);
        }
    }

    private function adjustmentSalesBaseUrlForApp(string $appName): ?string
    {
        $map = [
            'Bilar Breeder Local' => 'http://172.16.43.148/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=13',
            'Bilar Breeder' => 'http://172.16.220.1:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=13',
            'Gp Jagna' => 'http://172.16.112.51:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=50',
            'Ice Plant' => 'http://172.16.184.49:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=25',
            'Peanut Kisses' => 'http://172.16.184.49:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=26',
            'Cortes Poultry' => 'http://172.16.192.68:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=12',
            'Cortes Piggery' => 'http://172.16.192.68:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=11',
            'Canhayupon Breeder' => 'http://172.16.220.223:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=15',
            'Bilar Hatchery' => 'http://172.16.219.200:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=14',
            'Lapsaon Breeder' => 'http://172.16.220.222:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=16',
            'Rizal Breeder' => 'http://172.16.217.11:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=43',
            'Feedmill' => 'http://172.16.105.2:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=19',
            'Growout' => 'http://172.16.105.2:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=20',
            'Cortes Fertilizer' => 'http://172.16.105.2:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=42',
            'Ubay Fertilizer' => 'http://172.16.105.2:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=22',
            'Piggery Untaga' => 'http://172.16.105.2:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=23',
            'Demo Farm' => 'http://172.16.105.2:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=21',
            'Dressing Plant' => 'http://172.16.105.2:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=17',
            'Farmers Market' => 'http://172.16.105.2:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=41',
            'Meat Processing' => 'http://172.16.105.2:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=46',
            'Rendering' => 'http://172.16.105.2:81/centralized-invoicing/sales-invoice/update/adjustment-sales?bu=18',
            'Ar System' => null,
        ];

        return $map[$appName] ?? null;
    }

    private function appNameFromTenant(string $tenant): ?string
    {
        $key = strtolower(trim($tenant));

        return match ($key) {
            'bilarbreeder' => 'Bilar Breeder',
            'bilarbreederlocal' => 'Bilar Breeder Local',
            'gpjagna' => 'Gp Jagna',
            'iceplant' => 'Ice Plant',
            'peanutkisses' => 'Peanut Kisses',
            'cortespoultry' => 'Cortes Poultry',
            'cortespiggery' => 'Cortes Piggery',
            'canhayuponbreeder' => 'Canhayupon Breeder',
            'bilarhatchery' => 'Bilar Hatchery',
            'lapsaonbreeder' => 'Lapsaon Breeder',
            'rizalbreeder' => 'Rizal Breeder',
            'feedmill' => 'Feedmill',
            'growout' => 'Growout',
            'mficortesfertilizer' => 'Cortes Fertilizer',
            'mfiubayfertilizer' => 'Ubay Fertilizer',
            'piggeryuntaga' => 'Piggery Untaga',
            'demofarm' => 'Demo Farm',
            'dressingplant' => 'Dressing Plant',
            'farmersmarket' => 'Farmers Market',
            'meatprocessing' => 'Meat Processing',
            'rendering' => 'Rendering',
            default => null,
        };
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

        $reasons = AdjustmentReasonSetup::where('type', $apply_to)
            ->where('status', 'Active')
            ->get(['reason_name', 'acc_code']);

        return response()->json($reasons);
    }
}
