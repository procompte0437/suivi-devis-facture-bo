<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReferentielItem;
use App\Models\StaffMember;
use App\Support\AuditLogger;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
        AuditLogger::record($request->user(), 'personnel.cree', 'Personne '.$member->name.' ajoutée.', $member);

        return response()->json($member, 201);
    }

    public function update(Request $request, StaffMember $staff)
    {
        $staff->update($this->fields($request));
        AuditLogger::record($request->user(), 'personnel.maj', 'Fiche de '.$staff->name.' mise à jour.', $staff);

        return response()->json($staff);
    }

    public function destroy(StaffMember $staff)
    {
        $name = $staff->name;
        $staff->delete();
        AuditLogger::record(auth()->user(), 'personnel.retire', 'Personne '.$name.' retirée.');

        return response()->json(['message' => 'Personne retirée.']);
    }

    private function fields(Request $request): array
    {
        $data = $this->validateData($request, [
            'name' => ['required', 'string', 'max:120'],
            'role_title' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        $connu = ReferentielItem::query()
            ->where('kind', 'role')
            ->where('label', $data['role_title'])
            ->exists();
        if (! $connu) {
            throw ValidationException::withMessages([
                'role_title' => 'Choisissez un rôle défini par l’administrateur dans le référentiel.',
            ]);
        }

        return $data;
    }
}
