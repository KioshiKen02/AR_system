<?php

namespace App\Services;

class SignatoryService
{
    /**
     * Get the 'Noted By' signatory for a given tenant identifier.
     *
     * @param string $tenantIdentifier The tenant identifier (e.g., database name or app name)
     * @return string|null The name of the signatory, or null if not found.
     */
    public static function getNotedBy(?string $tenantIdentifier): ?string
    {
         if (! $tenantIdentifier) {
            return null;
        }

        $identifier = strtolower($tenantIdentifier);

        if (str_contains($identifier, 'bilar') && str_contains($identifier, 'breeder') && str_contains($identifier, 'local')) {
            return '';
        }

        if (str_contains($identifier, 'bilar') && str_contains($identifier, 'breeder')) {
            return 'MARCELA M. ORTOT';
        }

        if (str_contains($identifier, 'gp') && str_contains($identifier, 'jagna')) {
            return '';
        }

        if (str_contains($identifier, 'ice') && str_contains($identifier, 'plant')) {
            return '';
        }

        if (str_contains($identifier, 'peanut') && str_contains($identifier, 'kisses')) {
            return 'GABATO, DENNIS T.';
        }

        if (str_contains($identifier, 'cortes') && str_contains($identifier, 'poultry')) {
            return 'SAMUEL LUCIP';
        }

        if (str_contains($identifier, 'cortes') && str_contains($identifier, 'piggery')) {
            return '';
        }

        if (str_contains($identifier, 'canhayupon') && str_contains($identifier, 'breeder')) {
            return '';
        }

        if (str_contains($identifier, 'bilar') && str_contains($identifier, 'hatchery')) {
            return '';
        }

        if (str_contains($identifier, 'lapsaon') && str_contains($identifier, 'breeder')) {
            return '';
        }

        if (str_contains($identifier, 'rizal') && str_contains($identifier, 'breeder')) {
            return '';
        }

        if (str_contains($identifier, 'feedmill')) {
            return '';
        }

        if (str_contains($identifier, 'growout')) {
            return '';
        }

        if (str_contains($identifier, 'cortes') && str_contains($identifier, 'fertilizer')) {
            return '';
        }

        if (str_contains($identifier, 'ubay') && str_contains($identifier, 'fertilizer')) {
            return '';
        }

        if (str_contains($identifier, 'piggery') && str_contains($identifier, 'untaga')) {
            return '';
        }

        if (str_contains($identifier, 'demo') && str_contains($identifier, 'farm')) {
            return '';
        }

        if (str_contains($identifier, 'dressing') && str_contains($identifier, 'plant')) {
            return '';
        }

        if (str_contains($identifier, 'farmers') && str_contains($identifier, 'market')) {
            return '';
        }

        if (str_contains($identifier, 'meat') && str_contains($identifier, 'processing')) {
            return '';
        }

        if (str_contains($identifier, 'rendering')) {
            return '';
        }

        return null;
    }

    public static function getReviewedBy(?string $tenantIdentifier): ?string
    {
        if (! $tenantIdentifier) {
            return null;
        }

        $identifier = strtolower($tenantIdentifier);

        if (str_contains($identifier, 'bilar') && str_contains($identifier, 'breeder') && str_contains($identifier, 'local')) {
            return '';
        }

        if (str_contains($identifier, 'bilar') && str_contains($identifier, 'breeder')) {
            return '';
        }

        if (str_contains($identifier, 'gp') && str_contains($identifier, 'jagna')) {
            return '';
        }

        if (str_contains($identifier, 'ice') && str_contains($identifier, 'plant')) {
            return '';
        }

        if (str_contains($identifier, 'peanut') && str_contains($identifier, 'kisses')) {
            return 'GABATO, DENNIS T.';
        }

        if (str_contains($identifier, 'cortes') && str_contains($identifier, 'poultry')) {
            return '';
        }

        if (str_contains($identifier, 'cortes') && str_contains($identifier, 'piggery')) {
            return '';
        }

        if (str_contains($identifier, 'canhayupon') && str_contains($identifier, 'breeder')) {
            return '';
        }

        if (str_contains($identifier, 'bilar') && str_contains($identifier, 'hatchery')) {
            return '';
        }

        if (str_contains($identifier, 'lapsaon') && str_contains($identifier, 'breeder')) {
            return '';
        }

        if (str_contains($identifier, 'rizal') && str_contains($identifier, 'breeder')) {
            return '';
        }

        if (str_contains($identifier, 'feedmill')) {
            return '';
        }

        if (str_contains($identifier, 'growout')) {
            return '';
        }

        if (str_contains($identifier, 'cortes') && str_contains($identifier, 'fertilizer')) {
            return 'ALEXIO AVENIDO JR.';
        }

        if (str_contains($identifier, 'ubay') && str_contains($identifier, 'fertilizer')) {
            return 'ALEXIO AVENIDO JR.';
        }

        if (str_contains($identifier, 'piggery') && str_contains($identifier, 'untaga')) {
            return '';
        }

        if (str_contains($identifier, 'demo') && str_contains($identifier, 'farm')) {
            return '';
        }

        if (str_contains($identifier, 'dressing') && str_contains($identifier, 'plant')) {
            return 'MARILOU ORFANO/JERAMIE ARTIAGA ';
        }

        if (str_contains($identifier, 'farmers') && str_contains($identifier, 'market')) {
            return '';
        }

        if (str_contains($identifier, 'meat') && str_contains($identifier, 'processing')) {
            return 'MARILOU ORFANO/JERAMIE ARTIAGA ';
        }

        if (str_contains($identifier, 'rendering')) {
            return '';
        }

        return null;
    }

    public static function shouldHidePreparedChecked(?string $tenantIdentifier): bool
    {
        if (! $tenantIdentifier) {
            return false;
        }

        $identifier = strtolower($tenantIdentifier);

        // List of tenants (keywords) that should hide Prepared/Checked By
        // Add new tenant keywords here to enable hiding for them
        $tenantsToHide = [
            ['ubay', 'fertilizer'],
            ['cortes', 'fertilizer'],
            ['dressing', 'plant'],
            ['meat', 'processing'],

            // ['another', 'tenant'], // Example: Un-comment to enable
        ];

        foreach ($tenantsToHide as $keywords) {
            $match = true;
            foreach ($keywords as $keyword) {
                if (!str_contains($identifier, $keyword)) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                return true;
            }
        }

        return false;
    }
}
