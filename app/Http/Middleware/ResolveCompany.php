<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $companies = $user?->companies() ?? Company::query()->whereRaw('1 = 0');
        $requested = $request->header('X-Company-Id');
        $company = null;

        if ($requested) {
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
