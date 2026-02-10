<?php

namespace App\Services;

class ExportToGLIndicator
{
    /**
     * Create a new class instance.
     */
    public static function exportToGLIndicator()
    {
        $reportName = config('app.name');

        switch ($reportName) {
            case 'Bilar Breeder Local':
                $exportToGLIndicator = 'BB-LOCAL';
                break;
            case 'Bilar Breeder':
                $exportToGLIndicator = 'BB';
                break;
            case 'Gp Jagna':
                $exportToGLIndicator = 'GP';
                break;
            case 'Ice Plant':
                $exportToGLIndicator = 'IP';
                break;
            case 'Peanut Kisses':
                $exportToGLIndicator = 'PK';
                break;
            case 'Cortes Poultry':
                $exportToGLIndicator = 'CPY';
                break;
            case 'Cortes Piggery':
                $exportToGLIndicator = 'CPGY';
                break;
            case 'Canhayupon Breeder':
                $exportToGLIndicator = 'CB';
                break;
            case 'Bilar Hatchery':
                $exportToGLIndicator = 'BH';
                break;
            case 'Lapsaon Breeder':
                $exportToGLIndicator = 'LB';
                break;
            case 'Rizal Breeder':
                $exportToGLIndicator = 'RB';
                break;
            // ubay server 
            case 'Feedmill':
                $exportToGLIndicator = 'FDML';
                break;
            case 'Growout':
                $exportToGLIndicator = 'GRWT';
                break;
            case 'Cortes Fertilizer':
                $exportToGLIndicator = 'CF';
                break;
            case 'Ubay Fertilizer':
                $exportToGLIndicator = 'UF';
                break;
            case 'Piggery Untaga':
                $exportToGLIndicator = 'PU';
                break;
            case 'Demo Farm':
                $exportToGLIndicator = 'DF';
                break;
            case 'Dressing plant':
                $exportToGLIndicator = 'DP';
                break;
            case 'Farmers Market':
                $exportToGLIndicator = 'FM';
                break;
            case 'Meat Processing':
                $exportToGLIndicator = 'MP';
                break;
            case 'Rendering':
                $exportToGLIndicator = 'REND';
                break;
            default:
                throw new \Exception("Unknown app name: {$reportName}");
        }

        return $exportToGLIndicator;
    }
}
