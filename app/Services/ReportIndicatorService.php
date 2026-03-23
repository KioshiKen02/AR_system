<?php

namespace App\Services;

class ReportIndicatorService
{
    /**
     * Create a new class instance.
     */
    public static function reportIndicator($user = null)
    {
        $user = $user ?? auth()->user();
        
        // Check if we have a specific tenant slug in the route
        $tenantSlug = strtolower(trim((string) request()->route('tenant')));
        
        $reportName = null;
        
        if ($tenantSlug) {
             // Map slug to App Name directly for performance if possible, or query DB
             // We can query AppSetting to find the app_name for this slug
             $appSetting = \App\Models\AppSetting::on('mysql')
                ->whereRaw('LOWER(TRIM(base_url)) = ?', [$tenantSlug])
                ->orWhereRaw("REPLACE(LOWER(TRIM(app_name)), ' ', '') = ?", [$tenantSlug])
                ->first();
                
             if ($appSetting) {
                 $reportName = $appSetting->app_name;
             }
        }
        
        // Fallback to User's primary setting or Config
        if (!$reportName) {
            $reportName = $user && $user->appSetting ? $user->appSetting->app_name : config('app.name');
        }

        $key = strtolower(preg_replace('/\s+/', '', trim((string) $reportName)));

        switch ($key) {
            case 'bilarbreederlocal':
                $reportIndicator = 'BRDR BIL-AG003-Local';
                break;
            case 'bilarbreeder':
                $reportIndicator = 'BRDR BIL-AG003';
                break;
            case 'gpjagna':
                $reportIndicator = 'GPFARM-AG021';
                break;
            case 'iceplant':
                $reportIndicator = 'ICE-NF001';
                break;
            case 'peanutkisses':
                $reportIndicator = 'PK TAG-NF002';
                break;
            case 'cortespoultry':
                $reportIndicator = 'LAYER CORT-AG002';
                break;
            case 'cortespiggery':
                $reportIndicator = 'PGRY CORT-AG001';
                break;
            case 'canhayuponbreeder':
                $reportIndicator = 'BRDR DIMCAN-AG005';
                break;
            case 'bilarhatchery':
                $reportIndicator = 'HTCH BIL-AG004';
                break;
            case 'lapsaonbreeder':
                $reportIndicator = 'BRDR DIMLAP-AG006';
                break;
            case 'rizalbreeder':
                $reportIndicator = 'RIZAL BIL-AG015';
                break;
            case 'feedmill':
                $reportIndicator = 'FDML UBAY-AG009';
                break;
            case 'growout':
                $reportIndicator = 'GRWT UBAY-AG010';
                break;
            case 'cortesfertilizer':
                $reportIndicator = 'FERP CORTES-AG015';
                break;
            case 'ubayfertilizer':
                $reportIndicator = 'FERP UBAY-AG012';
                break;
            case 'piggeryuntaga':
                $reportIndicator = 'PGRY ALC-AG013';
                break;
            case 'demofarm':
                $reportIndicator = 'DMO UBAY-AG011';
                break;
            case 'dressingplant':
                $reportIndicator = 'DRSP UBAY-AG007';
                break;
            case 'farmersmarket':
                $reportIndicator = 'FARMERS MARKET';
                break;
            case 'meatprocessing':
                $reportIndicator = 'MPP-UBAY-AG017';
                break;
            case 'rendering':
                $reportIndicator = 'REND UBAY-AG008';
                break;
            default:
                throw new \Exception("Unknown app name: {$reportName}");
        }

        return $reportIndicator;
    }
}
