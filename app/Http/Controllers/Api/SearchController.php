<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quote;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json([
                'clients' => [],
                'quotes' => [],
                'invoices' => [],
                'projects' => [],
            ]);
        }

        $like = '%'.$term.'%';

        return response()->json([
            'clients' => Client::query()
                ->where(function ($query) use ($like) {
                    $query->where('company', 'ilike', $like)
                        ->orWhere('contact_name', 'ilike', $like)
                        ->orWhere('code', 'ilike', $like);
                })
                ->orderBy('company')
                ->limit(5)
                ->get(['id', 'code', 'company', 'city']),
            'quotes' => Quote::query()
                ->with('client:id,company')
                ->where(function ($query) use ($like) {
                    $query->where('number', 'ilike', $like)->orWhere('title', 'ilike', $like);
                })
                ->latest()
                ->limit(5)
                ->get(['id', 'number', 'title', 'client_id', 'total', 'status']),
            'invoices' => Invoice::query()
                ->with('client:id,company')
                ->where(function ($query) use ($like) {
                    $query->where('number', 'ilike', $like)->orWhere('title', 'ilike', $like);
                })
                ->latest()
                ->limit(5)
                ->get(['id', 'number', 'title', 'client_id', 'total', 'status', 'amount_paid']),
            'projects' => Project::query()
                ->with('client:id,company')
                ->where(function ($query) use ($like) {
                    $query->where('name', 'ilike', $like)->orWhere('code', 'ilike', $like);
                })
                ->orderBy('name')
                ->limit(5)
                ->get(['id', 'code', 'name', 'client_id', 'status', 'progress']),
        ]);
    }
}
