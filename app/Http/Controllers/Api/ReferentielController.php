<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ReferentielItem;
use App\Models\StaffMember;
use App\Support\Access;
use App\Support\ActivityLogger;
use App\Support\CompanyContext;
use App\Support\ReferentielCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReferentielController extends Controller
{
    public function index()
    {
        $grouped = ReferentielItem::query()->orderBy('position')->orderBy('label')->get()->groupBy('kind');

        return response()->json(collect(ReferentielCatalog::KINDS)->mapWithKeys(
            fn (string $kind) => [$kind => ($grouped->get($kind) ?? new Collection)->values()]
        ));
    }

    public function store(Request $request)
    {
        $companyId = $this->companyId();
        $data = $this->fields($request, $companyId);
        $this->garderRoles($request, $data['kind']);
        $data['company_id'] = $companyId;
        $data['position'] = (int) ReferentielItem::query()->where('kind', $data['kind'])->max('position') + 1;
        if ($data['kind'] === 'statut') {
            $data['code'] = ReferentielCatalog::codeFor($companyId, $data['label']);
        }

        $item = ReferentielItem::create($data);
        ActivityLogger::log($request->user(), 'referentiel.cree', $item, 'Référentiel : '.$item->label.' ajouté.');

        return response()->json($item, 201);
    }

    public function update(Request $request, ReferentielItem $item)
    {
        $companyId = $this->companyId();
        $this->garderRoles($request, $item->kind);
        $data = $this->fields($request, $companyId, $item);
        $ancienLibelle = $item->label;
        $item->update($data);

        if ($item->kind === 'societe') {
            Client::query()->where('societe_id', $item->id)->update(['company' => $item->label]);
        }
        if ($item->kind === 'role' && $ancienLibelle !== $item->label) {
            StaffMember::query()->where('role_title', $ancienLibelle)->update(['role_title' => $item->label]);
        }

        ActivityLogger::log($request->user(), 'referentiel.maj', $item, 'Référentiel : '.$item->label.' mis à jour.');

        return response()->json($item);
    }

    public function destroy(Request $request, ReferentielItem $item)
    {
        $this->garderRoles($request, $item->kind);
        if ($item->kind === 'societe' && Client::query()->where('societe_id', $item->id)->exists()) {
            return response()->json([
                'message' => 'Cette société a des clients. Modifiez ces fiches avant de la retirer.',
            ], 422);
        }
        if ($item->kind === 'statut' && Client::query()->where('status', $item->code)->exists()) {
            return response()->json([
                'message' => 'Ce statut est utilisé par des clients. Changez-le sur leurs fiches avant de le retirer.',
            ], 422);
        }
        if ($item->kind === 'ville' && Client::query()->where('city', $item->label)->exists()) {
            return response()->json([
                'message' => 'Cette ville est utilisée par des clients. Changez-la sur leurs fiches avant de la retirer.',
            ], 422);
        }
        if ($item->kind === 'role' && StaffMember::query()->where('role_title', $item->label)->exists()) {
            return response()->json([
                'message' => 'Ce rôle est porté par des personnes. Changez leur fiche avant de le retirer.',
            ], 422);
        }

        $label = $item->label;
        $item->delete();
        ActivityLogger::log($request->user(), 'referentiel.retire', null, 'Référentiel : '.$label.' retiré.');

        return response()->json(['message' => 'Élément retiré.']);
    }

    private function companyId(): int
    {
        $id = CompanyContext::id();
        if (! $id) {
            throw ValidationException::withMessages([
                'company' => 'Choisissez une entreprise dans le menu avant d’enregistrer.',
            ]);
        }

        return $id;
    }

    private function fields(Request $request, int $companyId, ?ReferentielItem $item = null): array
    {
        $kind = $item?->kind ?? $request->input('kind');
        $data = $this->validateData($request, [
            'kind' => [$item ? 'sometimes' : 'required', Rule::in(ReferentielCatalog::KINDS)],
            'label' => [
                'required',
                'string',
                'max:160',
                Rule::unique('referentiel_items', 'label')
                    ->where(fn ($query) => $query->where('company_id', $companyId)->where('kind', $kind))
                    ->ignore($item?->id),
            ],
            'meta' => ['nullable', 'array'],
            'meta.contact_name' => ['nullable', 'string', 'max:160'],
            'meta.email' => ['nullable', 'email', 'max:160'],
            'meta.phone' => ['nullable', 'string', 'max:40'],
            'meta.address' => ['nullable', 'string', 'max:180'],
            'meta.city' => ['nullable', 'string', 'max:80'],
            'meta.tax_id' => ['nullable', 'string', 'max:80'],
            'meta.explication' => ['nullable', 'string', 'max:500'],
        ], $this->messagesExplicites($kind, (string) $request->input('label', '')));

        if ($item) {
            unset($data['kind']);
        }

        $kind = $item?->kind ?? $data['kind'] ?? null;
        if ($kind === 'role') {
            $explication = trim((string) ($data['meta']['explication'] ?? ''));
            if ($explication === '') {
                throw ValidationException::withMessages([
                    'meta.explication' => 'Expliquez ce que fait ce rôle.',
                ]);
            }
            $data['meta'] = ['explication' => $explication];
        } elseif ($kind !== 'societe') {
            $data['meta'] = null;
        }

        return $data;
    }

    /** Messages en français qui disent quel champ bloque et quoi faire. */
    private function messagesExplicites(?string $kind, string $label): array
    {
        $liste = match ($kind) {
            'societe' => 'les sociétés',
            'ville' => 'les villes',
            'statut' => 'les statuts',
            'forme' => 'les formes juridiques',
            'pays' => 'les pays',
            'role' => 'les rôles du personnel',
            default => 'cette liste',
        };
        $nom = trim($label) !== '' ? '« '.trim($label).' »' : 'Ce nom';
        $deja = in_array($kind, ['societe', 'role'], true)
            ? 'Ouvrez la ligne existante avec le crayon, ou choisissez un autre nom.'
            : 'Choisissez un autre nom.';

        return [
            'label.required' => "Indiquez un nom avant d’enregistrer dans {$liste}.",
            'label.unique' => "{$nom} est déjà dans {$liste}. {$deja}",
            'label.max' => 'Le nom est trop long : 160 caractères maximum.',
            'meta.email.email' => 'L’adresse e-mail n’est pas valide. Écrivez-la en entier, par exemple contact@societe.ga.',
            'meta.email.max' => 'L’adresse e-mail est trop longue : 160 caractères maximum.',
            'meta.phone.max' => 'Le téléphone est trop long : 40 caractères maximum.',
            'meta.contact_name.max' => 'Le nom du contact est trop long : 160 caractères maximum.',
            'meta.city.max' => 'La ville est trop longue : 80 caractères maximum.',
            'meta.address.max' => 'L’adresse est trop longue : 180 caractères maximum.',
            'meta.tax_id.max' => 'L’identifiant fiscal est trop long : 80 caractères maximum.',
            'meta.explication.max' => 'L’explication du rôle est trop longue : 500 caractères maximum.',
            'kind.required' => 'Indiquez la liste du référentiel (société, ville, statut, forme, pays ou rôle).',
            'kind.in' => 'Cette liste du référentiel n’existe pas.',
        ];
    }

    /** Les rôles du personnel ne sont définis que par l’administrateur ou le dirigeant. */
    private function garderRoles(Request $request, ?string $kind): void
    {
        if ($kind !== 'role') {
            return;
        }

        abort_unless(
            Access::isManager($request->user()),
            403,
            'Seuls l’administrateur et le dirigeant définissent les rôles du personnel.'
        );
    }
}
