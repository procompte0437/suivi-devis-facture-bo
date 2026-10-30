<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index()
    {
        return response()->json(User::query()->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request, [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:admin,commercial,comptable'],
            'phone' => ['nullable', 'string', 'max:40'],
            'job_title' => ['nullable', 'string', 'max:80'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user = User::create([
            ...$data,
            'email' => mb_strtolower($data['email']),
            'is_active' => $data['is_active'] ?? true,
        ]);

        ActivityLogger::log($request->user(), 'equipe.cree', $user, $user->name.' rejoint l’atelier.');

        return response()->json($user, 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validateData($request, [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', 'in:admin,commercial,comptable'],
            'phone' => ['nullable', 'string', 'max:40'],
            'job_title' => ['nullable', 'string', 'max:80'],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($user->role === 'admin' && ($data['role'] !== 'admin' || ! $data['is_active'])) {
            $others = User::query()
                ->where('role', 'admin')
                ->where('is_active', true)
                ->where('id', '!=', $user->id)
                ->count();

            if ($others < 1) {
                return response()->json(['message' => 'Il doit rester au moins un administrateur actif.'], 422);
            }
        }

        $user->fill([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'role' => $data['role'],
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

        ActivityLogger::log($request->user(), 'equipe.maj', $user, 'Profil de '.$user->name.' mis à jour.');

        return response()->json($user);
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Vous ne pouvez pas retirer votre propre compte.'], 422);
        }

        if ($user->role === 'admin') {
            $others = User::query()->where('role', 'admin')->where('id', '!=', $user->id)->where('is_active', true)->count();
            if ($others < 1) {
                return response()->json(['message' => 'Il doit rester au moins un administrateur actif.'], 422);
            }
        }

        $name = $user->name;
        $user->tokens()->delete();
        $user->delete();
        ActivityLogger::log($request->user(), 'equipe.retire', null, $name.' a quitté l’atelier.');

        return response()->json(['message' => 'Compte retiré.']);
    }
}
