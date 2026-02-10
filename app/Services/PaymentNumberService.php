<?php

namespace App\Services;

use App\Models\TransactionModels\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PaymentNumberService
{
    protected const DEFAULT_START_NUMBER = 26000001;

    public function generate(): int
    {
        $latest = Payment::withTrashed()
            ->lockForUpdate()
            ->orderByDesc('payment_no')
            ->first();

        $localNumber = $latest ? $latest->payment_no + 1 : self::DEFAULT_START_NUMBER;

        try {
            $apiNumber = $this->fetchFromApi();
            return max($localNumber, $apiNumber);
        } catch (\Exception $e) {
            return $localNumber;
        }
    }

    protected function fetchFromApi(): int
    {
        //DYNAMIC API LINK
        $user = auth()->user();
        $appName = $user && $user->appSetting ? $user->appSetting->app_name : config('app.name');

        switch ($appName) {
            case 'Bilar Breeder Local':
                $baseUrl = 'http://172.16.43.148/centralized_invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=13';
                break;
            case 'Bilar Breeder':
                $baseUrl = 'http://172.16.220.1:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=13';
                break;
            case 'Gp Jagna':
                $baseUrl = 'http://172.16.220.1:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=50';
                break;
            case 'Ice Plant':
                $baseUrl = 'http://172.16.184.49:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=25';
                break;
            case 'Peanut Kisses':
                $baseUrl = 'http://172.16.184.49:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=26';
                break;
            case 'Cortes Poultry':
                $baseUrl = 'http://172.16.192.68:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=12';
                break;
            case 'Cortes Piggery':
                $baseUrl = 'http://172.16.192.68:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=11';
                break;
            case 'Canhayupon Breeder':
                $baseUrl = 'http://172.16.220.223:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=15';
                break;
            case 'Bilar Hatchery':
                $baseUrl = 'http://172.16.219.200:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=14';
                break;
            case 'Lapsaon Breeder':
                $baseUrl = 'http://172.16.220.222:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=16';
                break;
            case 'Rizal Breeder':
                $baseUrl = 'http://172.16.217.11:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=43';
                break;
            // ubay server 
            case 'Feedmill':
                $baseUrl = 'http://172.16.105.1:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=19';
                break;
            case 'Growout':
                $baseUrl = 'http://172.16.105.1:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=20';
                break;
            case 'Cortes Fertilizer':
                $baseUrl = 'http://172.16.105.1:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=42';
                break;
            case 'Ubay Fertilizer':
                $baseUrl = 'http://172.16.105.1:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=22';
                break;
            case 'Piggery Untaga':
                $baseUrl = 'http://172.16.105.1:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=23';
                break;
            case 'Demo Farm':
                $baseUrl = 'http://172.16.105.1:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=21';
                break;
            case 'Dressing Plant':
                $baseUrl = 'http://172.16.105.1:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=17';
                break;
            case 'Farmers Market':
                $baseUrl = 'http://172.16.105.1:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=41';
                break;
            case 'Meat Processing':
                $baseUrl = 'http://172.16.105.1:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=46';
                break;
            case 'Rendering':
                $baseUrl = 'http://172.16.105.1:81/centralized-invoicing/transactionController/SalesInvoiceController/getLatestPaymentNo?noSession=true&bu=18';
                break;
            default:
                throw new \Exception("Unknown app name: {$appName}");
        }
        $url = $baseUrl;

        $response = Http::timeout(3)
            ->retry(2, 100)
            ->get($url);

        if ($response->successful()) {
            return (int)($response->json()['next_payment_no'] ?? 0);
        }

        throw new \Exception('Payment API request failed');
    }
}
