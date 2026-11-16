<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Audit;
use App\Support\Access;
use App\Support\CompanyContext;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $query = Audit::query()->with('user:id,name,role', 'company:id,name')->latest()->limit(200);

        if (! Access::isPlatformAdmin($request->user()) && ($companyId = CompanyContext::id())) {
            $query->where(function ($builder) use ($companyId) {
                $builder->where('company_id', $companyId)
                    ->orWhereHas('user.companies', fn ($company) => $company->where('companies.id', $companyId));
            });
        }

        return response()->json($query->get());
    }
}
