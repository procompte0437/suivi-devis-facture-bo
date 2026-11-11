<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Gestion des comptes (UC02). La liste se limite aux comptes de l'entreprise active,
 * pour qu'une entreprise ne voie pas les utilisateurs d'une autre.
 */
class AdminUserController extends Controller
{
    private const ROLES = 'admin,commercial,comptable,chef_projet,client';

    public function index()
    {
        return response()->json(
            $this->members()->with('client:id,company')->orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request, [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:'.self::ROLES],
            'client_id' => ['nullable', 'integer'],
            'phone' => ['nullable', 'string', 'max:40'],
            'job_title' => ['nullable', 'string', 'max:80'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user = User::create([
            ...$data,
            'client_id' => $this->clientFor($data),
            'email' => mb_strtolower($data['email']),
            'is_active' => $data['is_active'] ?? true,
        ]);

        if ($companyId = CompanyContext::id()) {
            $user->companies()->syncWithoutDetaching([$companyId => ['role' => $user->role === 'client' ? 'client' : 'member']]);
        }

        ActivityLogger::log($request->user(), 'equipe.cree', $user, 'Compte créé pour '.$user->name.'.');

        return response()->json($user->load('client:id,company'), 201);
    }

    public function update(Request $request, User $user)
    {
        $this->ensureMember($user);

        $data = $this->validateData($request, [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', 'in:'.self::ROLES.',dirigeant'],
            'client_id' => ['nullable', 'integer'],
            'phone' => ['nullable', 'string', 'max:40'],
            'job_title' => ['nullable', 'string', 'max:80'],
            'is_active' => ['required', 'boolean'],
        ]);

        $wasManager = in_array($user->role, ['admin', 'dirigeant'], true);
        $staysManager = in_array($data['role'], ['admin', 'dirigeant'], true) && $data['is_active'];
        if ($wasManager && ! $staysManager && $this->otherManagers($user) < 1) {
            return response()->json(['message' => 'Il doit rester au moins un administrateur actif.'], 422);
        }

        $user->fill([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'role' => $data['role'],
            'client_id' => $this->clientFor($data),
            'phone' => $data['phone'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'is_active' => $data['is_active'],
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();

        if (! $user->is_active) {
            $user->tokens()->delete();
        }

        ActivityLogger::log($request->user(), 'equipe.maj', $user, 'Compte de '.$user->name.' mis à jour.');

        return response()->json($user->load('client:id,company'));
    }

    public function destroy(Request $request, User $user)
    {
        $this->ensureMember($user);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Vous ne pouvez pas retirer votre propre compte.'], 422);
        }

        if (in_array($user->role, ['admin', 'dirigeant'], true) && $this->otherManagers($user) < 1) {
            return response()->json(['message' => 'Il doit rester au moins un administrateur actif.'], 422);
        }

        $name = $user->name;

        // Un compte rattaché à d'autres entreprises est seulement détaché de celle-ci.
        if ($user->companies()->count() > 1 && CompanyContext::id()) {
            $user->companies()->detach(CompanyContext::id());
        } else {
            $user->tokens()->delete();
            $user->delete();
        }

        ActivityLogger::log($request->user(), 'equipe.retire', null, 'Compte de '.$name.' retiré.');

        return response()->json(['message' => 'Compte retiré.']);
    }

    private function members()
    {
        $companyId = CompanyContext::id();

        return User::query()->when(
            $companyId,
            fn ($query) => $query->whereHas('companies', fn ($company) => $company->where('companies.id', $companyId)),
            fn ($query) => $query->where('id', auth()->id()),
        );
    }

    private function ensureMember(User $user): void
    {
        abort_unless($this->members()->whereKey($user->id)->exists(), 404);
    }

    private function otherManagers(User $user): int
    {
        return $this->members()
            ->whereIn('role', ['admin', 'dirigeant'])
            ->where('is_active', true)
            ->whereKeyNot($user->id)
            ->count();
    }

    /** Un compte client doit pointer vers une fiche client de l'entreprise active. */
    private function clientFor(array $data): ?int
    {
        if ($data['role'] !== 'client') {
            return null;
        }

        $client = empty($data['client_id']) ? null : Client::query()->find($data['client_id']);
        if (! $client) {
            throw ValidationException::withMessages(['client_id' => 'Choisissez la fiche client rattachée à ce compte.']);
        }

        return $client->id;
    }
}
