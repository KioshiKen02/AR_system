<?php

namespace App\Services;

use App\Models\AccCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class SyncAccCodeService
{
    public function sync()
    {
        try {
            // Fetch data from the API
            //DYNAMIC API LINK
            $user = Auth::user();
            $appName = $user && $user->appSetting ? $user->appSetting->app_name : config('app.name');
            switch ($appName) {
                case 'Bilar Breeder Local':
                    $baseUrl = 'http://172.16.220.1:81/centralized_invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=13';
                    break;
                case 'Bilar Breeder':
                    $baseUrl = 'http://172.16.220.1:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=13';
                    break;
                case 'Gp Jagna':
                    $baseUrl = 'http://172.16.112.51:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=50';
                    break;
                case 'Ice Plant':
                    $baseUrl = 'http://172.16.184.49:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=25';
                    break;
                case 'Peanut Kisses':
                    $baseUrl = 'http://172.16.184.49:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=26';
                    break;
                case 'Cortes Poultry':
                    $baseUrl = 'http://172.16.192.68:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=12';
                    break;
                case 'Cortes Piggery':
                    $baseUrl = 'http://172.16.192.68:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=11';
                    break;
                case 'Canhayupon Breeder':
                    $baseUrl = 'http://172.16.220.223:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=15';
                    break;
                case 'Bilar Hatchery':
                    $baseUrl = 'http://172.16.219.200:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=14';
                    break;
                case 'Lapsaon Breeder':
                    $baseUrl = 'http://172.16.220.222:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=16';
                    break;
                case 'Rizal Breeder':
                    $baseUrl = 'http://172.16.217.11:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=43';
                    break;
                // ubay server 
                case 'Feedmill':
                    $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=19';
                    break;
                case 'Growout':
                    $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=20';
                    break;
                case 'Cortes Fertilizer':
                    $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=42';
                    break;
                case 'Ubay Fertilizer':
                    $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=22';
                    break;
                case 'Piggery Untaga':
                    $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=23';
                    break;
                case 'Demo Farm':
                    $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=21';
                    break;
                case 'Dressing Plant':
                    $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=17';
                    break;
                case 'Farmers Market':
                    $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=41';
                    break;
                case 'Meat Processing':
                    $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=46';
                    break;
                case 'Rendering':
                    $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/masterfileController/GlAccountCodeController/fetchGlAccountCode?noSession=true&bu=18';
                    break;
                default:
                    throw new \Exception("Unknown app name: {$appName}");
            }
            $url = $baseUrl;
            $response = Http::get($url);

            if (!$response->successful()) {
                Log::error('Failed to fetch acc code from API');
                return false;
            }

            $apiAccCodes = $response->json()['gl_account_code'] ?? [];
            $syncedIds = [];

            DB::connection('tenant')->transaction(function () use ($apiAccCodes, &$syncedIds) {
                foreach ($apiAccCodes as $apiAccCode) {
                    $syncedIds[] = $apiAccCode['gl_account_id'];

                    $data = [
                        'gl_account_navcode' => $apiAccCode['gl_account_navcode'],
                        'gl_account_name' => $apiAccCode['gl_account_name'],
                        'setup_by' => $apiAccCode['setup_by'],
                        'status' => $apiAccCode['status'] ?? false,
                        'business_unit' => $apiAccCode['business_unit'],
                    ];

                    AccCode::on('tenant')->updateOrCreate(
                        ['gl_account_id' => $apiAccCode['gl_account_id']],
                        $data
                    );
                }

                // Delete local acc code not present in the API
                AccCode::on('tenant')->whereNotIn('gl_account_id', $syncedIds)->delete();
            });

            return true;
        } catch (\Exception $e) {
            Log::error('Acc Code sync failed: ' . $e->getMessage());
            return false;
        }
    }
}
