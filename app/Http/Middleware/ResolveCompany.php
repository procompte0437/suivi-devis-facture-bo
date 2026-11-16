<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\Access;
use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $requested = $request->header('X-Company-Id');

        // L'administrateur de la plateforme peut tout voir, ou ouvrir n'importe quelle entreprise.
        if ($user && Access::isPlatformAdmin($user) && $user->role !== 'client') {
            if (! $requested || $requested === 'all') {
                CompanyContext::setAll();
                $request->attributes->set('company', null);

                return $next($request);
            }

            $company = Company::query()->find($requested);
            if ($company) {
                CompanyContext::set($company);
            } else {
                CompanyContext::setAll();
            }
            $request->attributes->set('company', $company);

            return $next($request);
        }

        $companies = $user?->companies() ?? Company::query()->whereRaw('1 = 0');
        $company = null;

        if ($requested && $requested !== 'all') {
            $company = (clone $companies)->where('companies.id', $requested)->first();
        }

        $company ??= $user?->companies()->orderBy('companies.id')->first();

        // Un compte client suit toujours l'entreprise de sa fiche client.
        if ($user?->role === 'client' && $user->client_id) {
            $companyId = \App\Models\Client::withoutGlobalScope('company')->whereKey($user->client_id)->value('company_id');
            $company = $companyId ? Company::find($companyId) : null;
        }

        if ($company) {
            CompanyContext::set($company);
        } else {
            CompanyContext::set(null);
        }

        $request->attributes->set('company', $company);

        return $next($request);
    }
}
