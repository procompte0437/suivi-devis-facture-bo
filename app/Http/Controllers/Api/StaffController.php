<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StaffMember;
use App\Support\CompanyContext;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function index()
    {
        return response()->json(StaffMember::query()->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $companyId = CompanyContext::id();
        abort_unless($companyId, 422, 'Créez d’abord une entreprise.');

        $member = StaffMember::create([
            ...$this->fields($request),
            'company_id' => $companyId,
        ]);

        return response()->json($member, 201);
    }

    public function update(Request $request, StaffMember $staff)
    {
        $staff->update($this->fields($request));

        return response()->json($staff);
    }

    public function destroy(StaffMember $staff)
    {
        $staff->delete();

        return response()->json(['message' => 'Personne retirée.']);
    }

    private function fields(Request $request): array
    {
        return $this->validateData($request, [
            'name' => ['required', 'string', 'max:120'],
            'role_title' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);
    }
}
