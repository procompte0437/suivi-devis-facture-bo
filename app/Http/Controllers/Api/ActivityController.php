<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\User;
use App\Support\CompanyContext;

class ActivityController extends Controller
{
    public function index()
    {
        $query = Activity::query()
            ->with('user:id,name,role')
            ->where('action', 'projet.avancement')
            ->latest()
            ->limit(80);

        if (! CompanyContext::isAll() && ($companyId = CompanyContext::id())) {
            $ids = User::query()->whereHas('companies', fn ($company) => $company->where('companies.id', $companyId))->pluck('id');
            $query->whereIn('user_id', $ids);
        }

        return response()->json($query->get());
    }
}
