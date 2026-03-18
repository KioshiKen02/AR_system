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

    public function getPrefix($locCode = null)
    {
        if ($this->appName === 'Feedmill') {
            $loc = strtoupper(trim((string) $locCode));
            return $loc === 'FEED2' ? 'FM2' : 'FM1';
        }

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

    public function getDeptCode($locCode = null)
    {
        if ($this->appName === 'Feedmill') {
            $loc = strtoupper(trim((string) $locCode));
            return $loc === 'FEED2' ? '03.01.3.02' : '03.01.3.01';
        }

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

    public function getTextFileBaseName(string $exportType): string
    {
        $key = strtolower(trim($exportType));

        $overrides = [
            'Feedmill' => [
                'payment' => 'FM_COLL',
                'adjustment' => 'FM_ADJSALES',
                'other income' => 'FM_OCASHSALES',
                'charge invoice cash' => 'FM_OCASHSALES',
                'charges invoice cash' => 'FM_OCASHSALES',
                'charge invoice ar' => 'FM_OCRDTSALES',
                'charges invoice ar' => 'FM_OCRDTSALES',
            ],
            'Dressing Plant' => [
                'payment' => 'DP_DPCOLL',
                'adjustment' => 'DP_ADJSALES',
                'charge invoice cash' => 'DP_OCASHSALES',
                'charges invoice cash' => 'DP_OCASHSALES',
                'charge invoice ar' => 'DP_OCRDTSALES',
                'charges invoice ar' => 'DP_OCRDTSALES',
            ],
            'Farmers Market' => [
                'payment' => 'UFM_DPCOLL',
                'adjustment' => 'UFM_ADJSALES',
                'charge invoice cash' => 'UFM_OCASHSALES',
                'charges invoice cash' => 'UFM_OCASHSALES',
                'charge invoice ar' => 'UFM_OCRDTSALES',
                'charges invoice ar' => 'UFM_OCRDTSALES',
            ],
            'Ubay Fertilizer' => [
                'payment' => 'FP_COLL',
                'adjustment' => 'FP_ADJSALES',
                'charge invoice cash' => 'FP_OCASHSALES',
                'charges invoice cash' => 'FP_OCASHSALES',
                'charge invoice ar' => 'FP_OCRDTSALES',
                'charges invoice ar' => 'FP_OCRDTSALES',
            ],
            'Cortes Fertilizer' => [
                'payment' => 'CFP_COLL',
                'adjustment' => 'CFP_ADJSALES',
                'charge invoice cash' => 'CFP_OCASHSALES',
                'charges invoice cash' => 'CFP_OCASHSALES',
                'charge invoice ar' => 'CFP_OCRDTSALES',
                'charges invoice ar' => 'CFP_OCRDTSALES',
            ],
            'Meat Processing' => [
                'payment' => 'UMP_DPCOLL',
                'adjustment' => 'UMP_ADJSALES',
                'charge invoice cash' => 'UMP_OCASHSALES',
                'charges invoice cash' => 'UMP_OCASHSALES',
                'charge invoice ar' => 'UMP_OCRDTSALES',
                'charges invoice ar' => 'UMP_OCRDTSALES',
            ],
            'Rendering' => [
                'payment' => 'R_RPCOLL',
                'adjustment' => 'R_ADJSALES',
                'charge invoice cash' => 'R_OCASHSALES',
                'charges invoice cash' => 'R_OCASHSALES',
                'charge invoice ar' => 'R_OCRDTSALES',
                'charges invoice ar' => 'R_OCRDTSALES',
            ],
            'Piggery Untaga' => [
                'payment' => 'UPG_UNTCOLL',
                'adjustment' => 'UPG_ADJSALES',
                'charge invoice cash' => 'UPG_OCASHSALES',
                'charges invoice cash' => 'UPG_OCASHSALES',
                'charge invoice ar' => 'UPG_OCRDTSALES',
                'charges invoice ar' => 'UPG_OCRDTSALES',
            ],
            'Growout' => [
                'payment' => 'GRW_GRWCOLL',
                'adjustment' => 'GRW_ADJSALES',
                'charge invoice cash' => 'GRW_OCASHSALES',
                'charges invoice cash' => 'GRW_OCASHSALES',
                'charge invoice ar' => 'GRW_OCRDTSALES',
                'charges invoice ar' => 'GRW_OCRDTSALES',
            ],
        ];

        if (isset($overrides[$this->appName][$key])) {
            return $overrides[$this->appName][$key];
        }

        $prefix = $this->getPrefix();

        return match ($key) {
            'payment' => "{$prefix}_{$prefix}COLL",
            'adjustment' => "{$prefix}_ADJSALES",
            'other income', 'charge invoice cash', 'charges invoice cash' => "{$prefix}_OCASHSALES",
            'charge invoice ar', 'charges invoice ar' => "{$prefix}_OCRDTSALES",
            default => "{$prefix}_EXPORT",
        };
    }
}
