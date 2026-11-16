<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Support\Access;
use App\Support\AuditLogger;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $this->validateData($request, [
            'account_type' => ['required', 'in:personne,entreprise'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:40'],
            'city' => ['required_if:account_type,entreprise', 'nullable', 'string', 'max:80', Rule::exists('platform_cities', 'label')],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'role' => 'dirigeant',
            'account_type' => $data['account_type'],
            'job_title' => $data['account_type'] === 'entreprise' ? 'Dirigeant' : 'Indépendant',
            'is_active' => true,
        ]);

        if ($data['account_type'] === 'entreprise') {
            $company = Company::create([
                'name' => $data['name'],
                'city' => $data['city'] ?? null,
                'country' => 'Gabon',
                'email' => $user->email,
                'phone' => $user->phone,
            ]);
            $company->users()->attach($user->id, ['role' => 'owner']);
            \App\Support\ReferentielCatalog::seed($company->id);
            CompanyContext::set($company);
        } else {
            CompanyContext::set(null);
        }

        $token = $user->createToken('atelier')->plainTextToken;
        AuditLogger::record($user, 'auth.inscription', 'Ouverture du compte de '.$user->name.' ('.$user->email.').', $user);

        return response()->json($this->payload($user, $token), 201);
    }

    public function login(Request $request)
    {
        $data = $this->validateData($request, [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', mb_strtolower($data['email']))->first();

        if (! $user || ! $user->is_active || ! Hash::check($data['password'], $user->password)) {
            AuditLogger::record($user, 'auth.echec', 'Connexion refusée pour '.$data['email'].'.');
            throw ValidationException::withMessages([
                'email' => 'Identifiants invalides ou compte inactif.',
            ]);
        }

        $user->tokens()->where('name', 'atelier')->delete();
        $token = $user->createToken('atelier')->plainTextToken;
        AuditLogger::record($user, 'auth.entree', $user->name.' s’est connecté.', $user);

        return response()->json($this->payload($user, $token));
    }

    public function me(Request $request)
    {
        return response()->json($this->payload($request->user()));
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            AuditLogger::record($user, 'auth.sortie', $user->name.' s’est déconnecté.', $user);
        }
        $user?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Session fermée.']);
    }

    private function payload(User $user, ?string $token = null): array
    {
        $platform = Access::isPlatformAdmin($user);
        $companies = $platform
            ? Company::query()
                ->withCount([
                    'users',
                    'projects' => fn ($query) => $query->withoutGlobalScope('company'),
                ])
                ->orderBy('name')
                ->get()
            : $user->companies()->orderBy('name')->get();
        $current = CompanyContext::isAll()
            ? null
            : ($companies->firstWhere('id', CompanyContext::id()) ?? ($platform ? null : $companies->first()));

        $body = [
            'user' => $user->load('client:id,company,contact_name'),
            'abilities' => Access::abilities($user),
            'settings' => \App\Models\Setting::current(),
            'companies' => $companies,
            'company' => $current,
            'scope' => CompanyContext::isAll() ? 'all' : 'company',
            'unassigned' => $platform
                ? User::query()->whereDoesntHave('companies')->orderBy('name')->get(['id', 'name', 'email', 'role', 'job_title', 'is_active'])
                : [],
        ];

        if ($token) {
            $body['token'] = $token;
        }

        return $body;
    }
}
