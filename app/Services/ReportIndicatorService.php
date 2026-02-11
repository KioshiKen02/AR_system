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
        $tenantSlug = request()->route('tenant');
        
        $reportName = null;
        
        if ($tenantSlug) {
             // Map slug to App Name directly for performance if possible, or query DB
             // We can query AppSetting to find the app_name for this slug
             $appSetting = \App\Models\AppSetting::on('mysql')
                ->where('base_url', $tenantSlug) // Assuming base_url holds the slug 'feedmill', 'growout', etc.
                ->orWhereRaw("REPLACE(LOWER(app_name), ' ', '') = ?", [strtolower($tenantSlug)])
                ->first();
                
             if ($appSetting) {
                 $reportName = $appSetting->app_name;
             }
        }
        
        // Fallback to User's primary setting or Config
        if (!$reportName) {
            $reportName = $user && $user->appSetting ? $user->appSetting->app_name : config('app.name');
        }

        switch ($reportName) {
            case 'Bilar Breeder Local':
                $reportIndicator = 'BRDR BIL-AG003-Local';
                break;
            case 'Bilar Breeder':
                $reportIndicator = 'BRDR BIL-AG003';
                break;
            case 'Gp Jagna':
                $reportIndicator = 'GPFARM-AG021';
                break;
            case 'Ice Plant':
                $reportIndicator = 'ICE-NF001';
                break;
            case 'Peanut Kisses':
                $reportIndicator = 'PK TAG-NF002';
                break;
            case 'Cortes Poultry':
                $reportIndicator = 'LAYER CORT-AG002';
                break;
            case 'Cortes Piggery':
                $reportIndicator = 'PGRY CORT-AG001';
                break;
            case 'Canhayupon Breeder':
                $reportIndicator = 'BRDR DIMCAN-AG005';
                break;
            case 'Bilar Hatchery':
                $reportIndicator = 'HTCH BIL-AG004';
                break;
            case 'Lapsaon Breeder':
                $reportIndicator = 'BRDR DIMLAP-AG006';
                break;
            case 'Rizal Breeder':
                $reportIndicator = 'RIZAL BIL-AG015';
                break;
            // ubay server 
            case 'Feedmill':
                $reportIndicator = 'FDML UBAY-AG009';
                break;
            case 'Growout':
                $reportIndicator = 'GRWT UBAY-AG010';
                break;
            case 'Cortes Fertilizer':
                $reportIndicator = 'FERP CORTES-AG015';
                break;
            case 'Ubay Fertilizer':
                $reportIndicator = 'FERP UBAY-AG012';
                break;
            case 'Piggery Untaga':
                $reportIndicator = 'PGRY ALC-AG013';
                break;
            case 'Demo Farm':
                $reportIndicator = 'DMO UBAY-AG011';
                break;
            case 'Dressing Plant':
                $reportIndicator = 'DRSP UBAY-AG007';
                break;
            case 'Farmers Market':
                $reportIndicator = 'FARMS MARKET';
                break;
            case 'Meat Processing':
                $reportIndicator = 'MPP-UBAY-AG017';
                break;
            case 'Rendering':
                $reportIndicator = 'REND UBAY-AG008';
                break;
            default:
                throw new \Exception("Unknown app name: {$reportName}");
        }

        return $reportIndicator;
    }
}
