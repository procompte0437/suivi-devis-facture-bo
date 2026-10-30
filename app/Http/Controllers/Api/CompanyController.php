<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Support\CompanyContext;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        return response()->json($request->user()->companies()->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $data = $this->fields($request);
        $company = Company::create($data);
        $company->users()->attach($request->user()->id, ['role' => 'owner']);

        return response()->json($company, 201);
    }

    public function update(Request $request, Company $company)
    {
        $this->owns($request, $company);
        $company->update($this->fields($request));

        return response()->json($company);
    }

    public function destroy(Request $request, Company $company)
    {
        $this->owns($request, $company);
        if ($company->users()->count() && \App\Models\Client::withoutGlobalScope('company')->where('company_id', $company->id)->exists()) {
            return response()->json(['message' => 'Cette entreprise a déjà des clients. Retirez-les avant de la fermer.'], 422);
        }

        $company->delete();

        return response()->json(['message' => 'Entreprise retirée.']);
    }

    private function owns(Request $request, Company $company): void
    {
        $allowed = $request->user()->companies()->where('companies.id', $company->id)->exists();
        abort_unless($allowed, 404);
        CompanyContext::set($company);
    }

    private function fields(Request $request): array
    {
        return $this->validateData($request, [
            'name' => ['required', 'string', 'max:160'],
            'tagline' => ['nullable', 'string', 'max:180'],
            'legal_form' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:180'],
            'city' => ['nullable', 'string', 'max:80'],
            'postal_code' => ['nullable', 'string', 'max:16'],
            'country' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'tax_id' => ['nullable', 'string', 'max:80'],
            'capital' => ['nullable', 'string', 'max:80'],
            'iban' => ['nullable', 'string', 'max:80'],
            'payment_note' => ['nullable', 'string', 'max:2000'],
            'footer' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
