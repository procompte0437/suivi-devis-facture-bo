<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PlatformCity;
use App\Support\Access;
use App\Support\AuditLogger;
use App\Support\CompanyContext;
use App\Support\ReferentielCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        if (Access::isPlatformAdmin($request->user())) {
            return response()->json(
                Company::query()
                    ->withCount([
                        'users',
                        'projects' => fn ($query) => $query->withoutGlobalScope('company'),
                    ])
                    ->orderBy('name')
                    ->get()
            );
        }

        return response()->json($request->user()->companies()->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $data = $this->fields($request);
        if (! empty($data['city']) && ! PlatformCity::query()->where('label', $data['city'])->exists()) {
            throw ValidationException::withMessages([
                'city' => 'Choisissez une ville de la liste.',
            ]);
        }
        $company = Company::create($data);
        $company->users()->attach($request->user()->id, ['role' => 'owner']);
        ReferentielCatalog::seed($company->id);
        AuditLogger::record($request->user(), 'entreprise.creee', 'Entreprise '.$company->name.' créée.', $company);

        return response()->json($company, 201);
    }

    public function update(Request $request, Company $company)
    {
        $this->owns($request, $company);
        $company->update($this->fields($request));
        AuditLogger::record($request->user(), 'entreprise.maj', 'Entreprise '.$company->name.' mise à jour.', $company);

        return response()->json($company);
    }

    public function logo(Request $request, Company $company)
    {
        $this->owns($request, $company);
        $request->validate([
            'logo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'logo.required' => 'Choisissez une image.',
            'logo.image' => 'Le fichier doit être une image.',
            'logo.mimes' => 'Le logo doit être un PNG, un JPG ou un WebP.',
            'logo.max' => 'Le logo ne doit pas dépasser 2 Mo.',
        ]);

        if ($company->logo_path) {
            Storage::disk('public')->delete($company->logo_path);
        }

        $path = $request->file('logo')->store('logos', 'public');
        $company->update(['logo_path' => $path]);
        AuditLogger::record($request->user(), 'entreprise.logo', 'Logo de '.$company->name.' enregistré.', $company);

        return response()->json($company->fresh());
    }

    public function clearLogo(Request $request, Company $company)
    {
        $this->owns($request, $company);
        if ($company->logo_path) {
            Storage::disk('public')->delete($company->logo_path);
            $company->update(['logo_path' => null]);
            AuditLogger::record($request->user(), 'entreprise.logo', 'Logo de '.$company->name.' retiré.', $company);
        }

        return response()->json($company->fresh());
    }

    public function destroy(Request $request, Company $company)
    {
        $this->owns($request, $company);
        if ($company->users()->count() && \App\Models\Client::withoutGlobalScope('company')->where('company_id', $company->id)->exists()) {
            return response()->json(['message' => 'Cette entreprise a déjà des clients. Retirez-les avant de la fermer.'], 422);
        }

        $name = $company->name;
        $company->delete();
        AuditLogger::record($request->user(), 'entreprise.retiree', 'Entreprise '.$name.' retirée.');

        return response()->json(['message' => 'Entreprise retirée.']);
    }

    private function owns(Request $request, Company $company): void
    {
        $allowed = Access::isPlatformAdmin($request->user())
            || $request->user()->companies()->where('companies.id', $company->id)->exists();
        abort_unless($allowed, 404);
        CompanyContext::set($company);
    }

    private function fields(Request $request): array
    {
        return $this->validateData($request, [
            'name' => ['required', 'string', 'max:160'],
            'tagline' => ['nullable', 'string', 'max:180'],
            'legal_form' => ['nullable', 'string', 'max:160'],
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
