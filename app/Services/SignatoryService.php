<?php

namespace App\Services;

class SignatoryService
{
    private const SIGNATORY_RULES = [
        [
            'keywords' => ['bilar', 'breeder', 'local'],
            'noted' => '',
            'reviewed' => '',
            'checked' => '',
        ],
        [
            'keywords' => ['bilar', 'breeder'],
            'noted' => 'MARCELA M. OROT',
            'reviewed' => 'MARCELA M. OROT',
            'checked' => 'ELVIE SEIT',
        ],
        [
            'keywords' => ['gp', 'jagna'],
            'noted' => 'LOURDES JALOP',
            'reviewed' => 'LOURDES JALOP',
            'checked' => '',
        ],
        [
            'keywords' => ['ice', 'plant'],
            'noted' => 'HAYZELMAE P. OROT',
            'reviewed' => 'HAYZELMAE P. OROT',
            'checked' => '',
        ],
        [
            'keywords' => ['peanut', 'kisses'],
            'noted' => 'GABATO, DENNIS T.',
            'reviewed' => 'GABATO, DENNIS T.',
            'checked' => '',
        ],
        [
            'keywords' => ['cortes', 'poultry'],
            'noted' => 'SAMUEL LUCIP',
            'reviewed' => 'SAMUEL LUCIP',
            'checked' => '',
        ],
        [
            'keywords' => ['cortes', 'piggery'],
            'noted' => 'JOESPH M. GILDORE',
            'reviewed' => 'JOESPH M. GILDORE',
            'checked' => '',
        ],
        [
            'keywords' => ['canhayupon', 'breeder'],
            'noted' => 'MELISSA A. QUIÑANOLA',
            'reviewed' => 'MELISSA A. QUIÑANOLA',
            'checked' => '',
        ],
        [
            'keywords' => ['bilar', 'hatchery'],
            'noted' => 'ANTONIO ATAN',
            'reviewed' => 'ANTONIO ATAN',
            'checked' => '',
        ],
        [
            'keywords' => ['lapsaon', 'breeder'],
            'noted' => 'EMMANUEL DORIA',
            'reviewed' => 'EMMANUEL DORIA',
            'checked' => '',
        ],
        [
            'keywords' => ['rizal', 'breeder'],
            'noted' => 'JENNIFER BALBIDO',
            'reviewed' => 'JENNIFER BALBIDO',
            'checked' => '',
        ],
        [
            'keywords' => ['feedmill'],
            'noted' => '',
            'reviewed' => '',
            'checked' => '',
        ],
        [
            'keywords' => ['growout'],
            'noted' => '',
            'reviewed' => '',
            'checked' => '',
        ],
        [
            'keywords' => ['cortes', 'fertilizer'],
            'noted' => 'ALEXIO AVENIDO JR.',
            'reviewed' => 'ALEXIO AVENIDO JR.',
            'checked' => '',
        ],
        [
            'keywords' => ['ubay', 'fertilizer'],
            'noted' => 'ALEXIO AVENIDO JR.',
            'reviewed' => 'ALEXIO AVENIDO JR.',
            'checked' => '',
        ],
        [
            'keywords' => ['piggery', 'untaga'],
            'noted' => '',
            'reviewed' => '',
            'checked' => '',
        ],
        [
            'keywords' => ['demo', 'farm'],
            'noted' => '',
            'reviewed' => '',
            'checked' => '',
        ],
        [
            'keywords' => ['dressing', 'plant'],
            'noted' => 'JERAMIE ARTIAGA/MARILOU ORFANO',
            'reviewed' => 'JERAMIE ARTIAGA/MARILOU ORFANO',
            'checked' => '',
        ],
        [
            'keywords' => ['farmers', 'market'],
            'noted' => 'JERAMIE ARTIAGA/MARILOU ORFANO',
            'reviewed' => 'JERAMIE ARTIAGA/MARILOU ORFANO',
            'checked' => '',
        ],
        [
            'keywords' => ['meat', 'processing'],
            'noted' => 'JERAMIE ARTIAGA/MARILOU ORFANO',
            'reviewed' => 'JERAMIE ARTIAGA/MARILOU ORFANO',
            'checked' => '',
        ],
        [
            'keywords' => ['rendering'],
            'noted' => 'JERAMIE ARTIAGA/MARILOU ORFANO',
            'reviewed' => 'JERAMIE ARTIAGA/MARILOU ORFANO',
            'checked' => '',
        ],
    ];

    private const HIDE_PREPARED_CHECKED_RULES = [
        ['ubay', 'fertilizer'],
        ['cortes', 'fertilizer'],
        ['dressing', 'plant'],
        ['meat', 'processing'],
    ];

    /**
     * Get the 'Noted By' signatory for a given tenant identifier.
     *
     * @param string $tenantIdentifier The tenant identifier (e.g., database name or app name)
     * @return string|null The name of the signatory, or null if not found.
     */
    public static function getNotedBy(?string $tenantIdentifier): ?string
    {
        return self::resolveSignatory($tenantIdentifier, 'noted');
    }

    public static function getReviewedBy(?string $tenantIdentifier): ?string
    {
        return self::resolveSignatory($tenantIdentifier, 'reviewed');
    }

    public static function getCheckedBy(?string $tenantIdentifier): ?string
    {
        return self::resolveSignatory($tenantIdentifier, 'checked');
    }

    public static function shouldHidePreparedChecked(?string $tenantIdentifier): bool
    {
        if (! $tenantIdentifier) {
            return false;
        }

        $identifier = strtolower($tenantIdentifier);

        foreach (self::HIDE_PREPARED_CHECKED_RULES as $keywords) {
            if (self::matchesKeywords($identifier, $keywords)) {
                return true;
            }
        }

        return false;
    }

    private static function resolveSignatory(?string $tenantIdentifier, string $role): ?string
    {
        if (! $tenantIdentifier) {
            return null;
        }

        $identifier = strtolower($tenantIdentifier);

        foreach (self::SIGNATORY_RULES as $rule) {
            if (self::matchesKeywords($identifier, $rule['keywords'])) {
                return $rule[$role] ?? null;
            }
        }

        return null;
    }

    private static function matchesKeywords(string $identifier, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (! str_contains($identifier, $keyword)) {
                return false;
            }
        }

        return true;
    }
}
