<?php

namespace App\Support;

use App\Models\Company;

class CompanyContext
{
    public static function set(?Company $company): void
    {
        app()->instance('currentCompany', $company);
        app()->instance('companyScope', $company ? 'bound' : 'empty');
    }

    /** Vue de supervision : aucune entreprise n'est filtrée. */
    public static function setAll(): void
    {
        app()->instance('currentCompany', null);
        app()->instance('companyScope', 'all');
    }

    public static function isAll(): bool
    {
        return app()->bound('companyScope') && app('companyScope') === 'all';
    }

    public static function id(): ?int
    {
        $company = app()->bound('currentCompany') ? app('currentCompany') : null;

        return $company?->id;
    }

    public static function isEmpty(): bool
    {
        return app()->bound('companyScope') && app('companyScope') === 'empty';
    }
}
