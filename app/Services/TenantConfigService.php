<?php

namespace App\Services;

class TenantConfigService
{
    protected $appName;

    /**
     * Create a new class instance.
     */
    public function __construct($appName)
    {
        $this->appName = $appName;
    }

    public function getPrefix()
    {
        // Define prefix logic based on appName
        return match ($this->appName) {
            'Bilar Breeder', 'Bilar Breeder Local' => 'BB',
            'Gp Jagna' => 'GP',
            'Ice Plant' => 'IP',
            'Peanut Kisses' => 'PK',
            'Cortes Poultry' => 'CP',
            'Cortes Piggery' => 'CPIG',
            'Canhayupon Breeder' => 'CB',
            'Bilar Hatchery' => 'BH',
            'Lapsaon Breeder' => 'LB',
            'Rizal Breeder' => 'RB',

            'Feedmill' => 'FM',
            'Growout' => 'GRW',
            'Cortes Fertilizer' => 'MFC',
            'Ubay Fertilizer' => 'MF',
            'Piggery Untaga' => 'PGRYU',
            'Demo Farm' => 'DF',
            'Dressing Plant' => 'DP',
            'Farmers Market' => 'UFM',
            'Meat Processing' => 'MP',
            'Rendering' => 'REN',
             default => 'BB', // Default fallback
        };
    }

    public function getCompanyCode()
    {
        return match ($this->appName) {
            'Bilar Breeder', 'Bilar Breeder Local' => '3',
            'Gp Jagna' => '50',
            'Ice Plant' => '25',
            'Peanut Kisses' => '26',
            'Cortes Poultry' => '12',
            'Cortes Piggery' => '11',
            'Canhayupon Breeder' => '15',
            'Bilar Hatchery' => '14',
            'Lapsaon Breeder' => '16',
            'Rizal Breeder' => '43',

            'Feedmill' => '03.00',
            'Growout' => '03.00',
            'Cortes Fertilizer' => '03.00',
            'Ubay Fertilizer' => '03.00',
            'Piggery Untaga' => '03.00',
            'Demo Farm' => '03.00',
            'Dressing Plant' => '03.00',
            'Farmers Market' => '03.00',
            'Meat Processing' => '03.00',
            'Rendering' => '03.00',
            default => '03.00',
        };
    }

    public function getDeptCode()
    {
        return match ($this->appName) {
            'Bilar Breeder', 'Bilar Breeder Local' => '03.01.2.02.2',
            'Gp Jagna' => '03.01.2.02.2',
            'Ice Plant' => '03.01.2.02.2',
            'Peanut Kisses' => '03.01.2.02.2',
            'Cortes Poultry' => '03.01.2.02.2',
            'Cortes Piggery' => '03.01.2.02.2',
            'Canhayupon Breeder' => '03.01.2.02.2',
            'Bilar Hatchery' => '03.01.2.02.2',
            'Lapsaon Breeder' => '03.01.2.02.2',
            'Rizal Breeder' => '03.01.2.02.2',

            'Feedmill' => '03.01.3.01',
            'Growout' => '03.01.2.04.1',
            'Cortes Fertilizer' => '03.01.10.02',
            'Ubay Fertilizer' => '03.01.10',
            'Piggery Untaga' => '03.01.1.2',
            'Demo Farm' => '03.01.2.03',
            'Dressing Plant' => '03.01.2.05',
            'Farmers Market' => '03.01.9',
            'Meat Processing' => '30.01.11',
            'Rendering' => '03.01.8',
            default => '03.01.2.02.2',
        };
    }
    
    public function getJournalCode()
    {
        return match ($this->appName) {
            'Bilar Breeder', 'Bilar Breeder Local' => 'SALESJNL',
            'Gp Jagna' => 'SALESJNL',
            'Ice Plant' => 'SALESJNL',
            'Peanut Kisses' => 'SALESJNL',
            'Cortes Poultry' => 'SALESJNL',
            'Cortes Piggery' => 'SALESJNL',
            'Canhayupon Breeder' => 'SALESJNL',
            'Bilar Hatchery' => 'SALESJNL',
            'Lapsaon Breeder' => 'SALESJNL',
            'Rizal Breeder' => 'SALESJNL',
            'Feedmill' => 'SALESJNL',
            'Growout' => 'SALESJNL',
            'Cortes Fertilizer' => 'SALESJNL',
            'Ubay Fertilizer' => 'SALESJNL',
            'Piggery Untaga' => 'SALESJNL',
            'Demo Farm' => 'SALESJNL',
            'Dressing Plant' => 'SALESJNL',
            'Farmers Market' => 'SALESJNL',
            'Meat Processing' => 'SALESJNL',
            'Rendering' => 'SALESJNL',
            default => 'SALESJNL',
        };
    }
}
