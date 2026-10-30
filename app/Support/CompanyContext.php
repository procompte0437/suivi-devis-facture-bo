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
