<?php

namespace App\Services;

use App\Models\MasterfileModels\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncCustomerService
{
    public function sync()
    {
        try {
            // Fetch data from the API
            //DYNAMIC API LINK
            $user = auth()->user();
            $appName = $user && $user->appSetting ? $user->appSetting->app_name : config('app.name');
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
            $response = Http::get($url);

            if (!$response->successful()) {
                Log::error('Failed to fetch customers from API');
                return false;
            }

            $apiCustomers = $response->json()['customers'] ?? [];
            $syncedIds = [];

            DB::connection('tenant')->transaction(function () use ($apiCustomers, &$syncedIds) {
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
                    }

                    Customer::on('tenant')->updateOrCreate(
                        ['cus_id' => $apiCustomer['cus_id']],
                        $data
                    );
                }

                // Delete local customers not present in the API
                Customer::on('tenant')->whereNotIn('cus_id', $syncedIds)->delete();
            });

            return true;
        } catch (\Exception $e) {
            Log::error('Customer sync failed: ' . $e->getMessage());
            return false;
        }
    }
}
