<?php

namespace App\Http\Controllers\MasterfileControllers;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\MasterfileModels\Customer;
use App\Services\SyncCustomerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CustomerController extends Controller
{

    public function index(Request $request)
    {
        $query = Customer::query();

        // Search functionality
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('cus_name', 'like', '%' . $request->search . '%')
                    ->orWhere('cus_code', 'like', '%' . $request->search . '%');
            });
        }

        // Type filters
        if ($request->type_filters) {
            $types = is_array($request->type_filters)
                ? $request->type_filters
                : explode(',', $request->type_filters);
            $query->whereIn('cus_type', $types);
        }

        // Status filters
        if ($request->status_filters) {
            $statuses = is_array($request->status_filters)
                ? $request->status_filters
                : explode(',', $request->status_filters);
            $query->whereIn('cus_status', $statuses);
        }

        // Code sorting
        if ($request->code_sort) {
            $query->orderBy('cus_code', $request->code_sort === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderByRaw("LTRIM(cus_name) ASC");
        }

        return Inertia::render('Customer', [
            'customers' => $query->paginate(10)->withQueryString(),
            'searchTerm' => $request->search,
            'filters' => [
                'code_sort' => $request->code_sort,
                'type_filters' => $request->type_filters ? (is_array($request->type_filters) ? $request->type_filters : explode(',', $request->type_filters)) : [],
                'status_filters' => $request->status_filters
                    ? (is_array($request->status_filters)
                        ? $request->status_filters
                        : explode(',', $request->status_filters))
                    : [],
            ],
        ]);
    }

    public function syncCustomers(SyncCustomerService $syncService)
    {
        try {
            // Fetch data from the API
            //DYNAMIC API LINK
            $user = auth()->user();
            $tenantSlug = request()->route('tenant');
            $defaultConn = config('database.default');
            $tenantConnConfigured = !is_null(config('database.connections.tenant'));
            $tenantDbName = $tenantConnConfigured ? (config('database.connections.tenant.database') ?? null) : null;
            $targetSetting = AppSetting::on('mysql')
                ->where('is_active', true)
                ->where(function ($q) use ($tenantSlug) {
                    $q->where('base_url', $tenantSlug)
                      ->orWhereRaw("REPLACE(LOWER(app_name), ' ', '') = ?", [strtolower($tenantSlug)])
                      ->orWhereRaw("? LIKE CONCAT('%', REPLACE(LOWER(app_name), ' ', ''), '%')", [strtolower($tenantSlug)]);
                })
                ->first();
            $appName = $targetSetting->app_name ?? ($user && $user->appSetting ? $user->appSetting->app_name : config('app.name'));
            Log::info('syncCustomers diagnostics', [
                'tenant_slug' => $tenantSlug,
                'database_default' => $defaultConn,
                'tenant_configured' => $tenantConnConfigured,
                'tenant_database' => $tenantDbName,
                'user_id' => $user?->id,
                'app_name' => $appName,
                'has_access' => $user ? ($user->appSettings->contains('id', $targetSetting?->id) || $user->role === 'Admin' || $user->app_setting_id == ($targetSetting->id ?? null)) : true,
            ]);
            switch ($appName) {
                case 'Bilar Breeder Local':
                    $baseUrl = 'http://172.16.43.148/centralized_invoicing/masterfileController/CustomerController/fetchCustomers?noSession=true&bu=13';
                    break;
                case 'Bilar Breeder':
                    $baseUrl = 'http://172.16.220.1:81/centralized-invoicing/masterfileController/CustomerController/fetchCustomers?noSession=true&bu=13';
                    break;
                case 'Gp Jagna':
                    $baseUrl = 'http://172.16.220.1:81/centralized-invoicing/masterfileController/CustomerController/fetchCustomers?noSession=true&bu=50';
                    break;
                case 'Ice Plant':
                    $baseUrl = 'http://172.16.184.49:81/centralized-invoicing/masterfileController/CustomerController/fetchCustomers?noSession=true&bu=25';
                    break;
                case 'Peanut Kisses':
                    $baseUrl = 'http://172.16.184.49:81/centralized-invoicing/masterfileController/CustomerController/fetchCustomers?noSession=true&bu=26';
                    break;
                case 'Cortes Poultry':
                    $baseUrl = 'http://172.16.192.68:81/centralized-invoicing/masterfileController/CustomerController/fetchCustomers?noSession=true&bu=12';
                    break;
                case 'Cortes Piggery':
                    $baseUrl = 'http://172.16.192.68:81/centralized-invoicing/masterfileController/CustomerController/fetchCustomers?noSession=true&bu=11';
                    break;
                case 'Canhayupon Breeder':
                    $baseUrl = 'http://172.16.220.223:81/centralized-invoicing/masterfileController/CustomerController/fetchCustomers?noSession=true&bu=15';
                    break;
                case 'Bilar Hatchery':
                    $baseUrl = 'http://172.16.219.200:81/centralized-invoicing/masterfileController/CustomerController/fetchCustomers?noSession=true&bu=14';
                    break;
                case 'Lapsaon Breeder':
                    $baseUrl = 'http://172.16.220.222:81/centralized-invoicing/masterfileController/CustomerController/fetchCustomers?noSession=true&bu=16';
                    break;
                case 'Rizal Breeder':
                    $baseUrl = 'http://172.16.217.11:81/centralized-invoicing/masterfileController/CustomerController/fetchCustomers?noSession=true&bu=43';
                    break;
                    // ubay server 
                case 'Feedmill':
                    $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/masterfileController/CustomerController/fetchCustomers?noSession=true&bu=19';
                    break;
                case 'Growout':
                    $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/masterfileController/CustomerController/fetchCustomers?noSession=true&bu=20';
                    break;
                case 'Cortes Fertilizer':
                    $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/masterfileController/CustomerController/fetchCustomers?noSession=true&bu=42';
                    break;
                case 'Ubay Fertilizer':
                    $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/masterfileController/CustomerController/fetchCustomers?noSession=true&bu=22';
                    break;
                case 'Piggery Untaga':
                    $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/masterfileController/CustomerController/fetchCustomers?noSession=true&bu=23';
                    break;
                case 'Demo Farm':
                    $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/masterfileController/CustomerController/fetchCustomers?noSession=true&bu=21';
                    break;
                case 'Dressing Plant':
                    $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/masterfileController/CustomerController/fetchCustomers?noSession=true&bu=17';
                    break;
                case 'Farmers Market':
                    $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/masterfileController/CustomerController/fetchCustomers?noSession=true&bu=41';
                    break;
                case 'Meat Processing':
                    $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/masterfileController/CustomerController/fetchCustomers?noSession=true&bu=46';
                    break;
                case 'Rendering':
                    $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/masterfileController/CustomerController/fetchCustomers?noSession=true&bu=18';
                    break;
                default:
                    throw new \Exception("Unknown app name: {$appName}");
            }
            $url = $baseUrl;

            Log::info('syncCustomers endpoint', [
                'url' => $url,
                'app_name' => $appName,
                'tenant_database' => $tenantDbName,
            ]);
            $response = Http::get($url);
            // dd($response->json()['customers']);

            if (!$response->successful()) {
                return response()->json(['error' => 'Failed to fetch customers from API'], 500);
            }

            $apiCustomers = $response->json()['customers'] ?? [];
            Log::info('syncCustomers api_data', [
                'count' => is_array($apiCustomers) ? count($apiCustomers) : 0,
                'status' => $response->status(),
            ]);

            $syncedIds = [];

            $stats = ['created' => 0, 'updated' => 0, 'deleted' => 0];
            DB::connection('tenant')->transaction(function () use ($apiCustomers, &$syncedIds, &$stats) {
                foreach ($apiCustomers as $apiCustomer) {
                    $syncedIds[] = $apiCustomer['cus_id'];

                    $existingCustomer = Customer::on('tenant')->where('cus_id', $apiCustomer['cus_id'])->first();

                    $data = [
                        'cus_code' => $apiCustomer['cus_code'],
                        'cus_name' => $apiCustomer['cus_name'],
                        'cus_type' => $apiCustomer['cus_type'],
                        'cus_price_group' => $apiCustomer['cus_price_group'],
                        'cus_address' => $apiCustomer['cus_address'],
                        'cus_tin' => $apiCustomer['cus_tin'] ?? "",
                        'cus_currency' => $apiCustomer['cus_currency'] ?? null,
                        'cus_bu' => $apiCustomer['cus_bu'] ?? null,
                        'nav_code' => $apiCustomer['nav_code'] ?? null,
                        'credit_limit' => $apiCustomer['credit_limit'] ?? 0,
                        'payment_terms' => $apiCustomer['payment_terms'] ?? null,
                        'non_trade' => $apiCustomer['cus_trade_type'] === 'NON-TRADE',
                        'applies_shrinkage' => $apiCustomer['applies_shrinkage'] ?? false,
                        'editable_wht' => $apiCustomer['editable_wht'] ?? false,
                        'journal_voucher' => $apiCustomer['journal_voucher'] ?? false,
                        'gen_posting' => $apiCustomer['gen_posting'] ?? null,
                        'cus_posting' => $apiCustomer['cus_posting'] ?? null,
                        'vat_posting' => $apiCustomer['vat_posting'] ?? null,
                        'cus_status' => $apiCustomer['cus_status'] ?? null,
                        'setup_by' => $apiCustomer['setup_by'] ?? 'system',
                    ];

                    // Add advance_payment_balance only if the customer is new
                    if (!$existingCustomer) {
                        $data['advanced_payment_balance'] = 0.00;
                        $stats['created']++;
                    } else {
                        $stats['updated']++;
                    }

                    Customer::on('tenant')->updateOrCreate(
                        ['cus_id' => $apiCustomer['cus_id']],
                        $data
                    );
                }
                // Delete local customers not present in the API
                $stats['deleted'] = Customer::on('tenant')->whereNotIn('cus_id', $syncedIds)->delete();
            });
            Log::info('syncCustomers db_write', $stats);

            // $syncService->sync();

            return response()->json([
                'success' => true,
                // 'message' => 'Successfully synchronized ' . count($syncedIds) . ' customers'
            ]);
        } catch (\Exception $e) {
            Log::error('Customer sync failed: ' . $e->getMessage());
            return response()->json([
                'error' => 'Synchronization failed: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    // public function getCustomerList()
    // {
    //     $list = Customer::select()->all()
    //         ->get();
    //     return response()->json($list);
    // }

    public function getCustomerList()
    {
        $customers = Customer::select([
            'cus_code', 
            'cus_name', 
            'cus_type', 
            'cus_price_group', 
            'advanced_payment_balance', 
            'editable_wht', 
            'journal_voucher'
        ])
        ->get()
        ->toArray();

        return response()->json($customers);
    }

    public function getAllCustomerList()
    {
        $customers = Customer::get();

        return response()->json($customers);
    }

    public function getAllCustomerAdvancePaymenBalanceList()
    {
        $customers = Customer::select('cus_code', 'cus_name', 'advance_payment_balance')->get();

        return response()->json($customers);
    }
}
