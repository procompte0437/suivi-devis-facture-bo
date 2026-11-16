<?php

namespace App\Support;

use App\Models\Client;
use App\Models\PlatformCity;
use App\Models\ReferentielItem;
use App\Models\StaffMember;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ReferentielCatalog
{
    public const KINDS = ['societe', 'ville', 'statut', 'forme', 'pays', 'role'];

    public static function defaults(): array
    {
        return [
            'ville' => self::villeDefaults(),
            'statut' => [
                ['label' => 'Prospect', 'code' => 'prospect'],
                ['label' => 'Actif', 'code' => 'actif'],
                ['label' => 'En veille', 'code' => 'veille'],
            ],
            'forme' => [
                ['label' => 'SARL'],
                ['label' => 'SA'],
                ['label' => 'SAS'],
                ['label' => 'EI'],
                ['label' => 'GIE'],
                ['label' => 'Association'],
            ],
            'pays' => [
                ['label' => 'Gabon'],
            ],
            // Métiers du personnel. L'explication dit ce que la personne fait sur les dossiers.
            'role' => self::rolesParDefaut(),
        ];
    }

    /** Rôles proposés à une entreprise, avec ce que chacun fait. */
    public static function rolesParDefaut(): array
    {
        return [
            ['label' => 'Direction', 'meta' => ['explication' => 'Pilote l’entreprise : arbitre les chantiers, valide les devis et suit l’équipe.']],
            ['label' => 'Commercial', 'meta' => ['explication' => 'Rencontre les clients, prépare les devis et relance les propositions envoyées.']],
            ['label' => 'Comptabilité', 'meta' => ['explication' => 'Émet les factures, enregistre les règlements et surveille les échéances.']],
            ['label' => 'Chef de projet', 'meta' => ['explication' => 'Découpe le chantier en tâches, les confie au personnel et tient l’avancement.']],
            ['label' => 'Artisan', 'meta' => ['explication' => 'Réalise les tâches qui lui sont assignées sur les projets.']],
        ];
    }

    /** Villes proposées à une nouvelle entreprise : celles de l’inscription, tenues par le superadmin. */
    private static function villeDefaults(): array
    {
        $fallback = ['Libreville', 'Port-Gentil', 'Franceville', 'Oyem', 'Mouila', 'Lambaréné'];
        $labels = $fallback;
        if (Schema::hasTable('platform_cities')) {
            $configured = PlatformCity::query()->orderBy('position')->orderBy('label')->pluck('label')->all();
            if ($configured) {
                $labels = $configured;
            }
        }

        return array_map(fn (string $label) => ['label' => $label], $labels);
    }

    public static function seed(int $companyId): void
    {
        foreach (self::defaults() as $kind => $rows) {
            $exists = ReferentielItem::withoutGlobalScope('company')
                ->where('company_id', $companyId)
                ->where('kind', $kind)
                ->exists();
            if ($exists) {
                continue;
            }
            foreach ($rows as $position => $row) {
                ReferentielItem::withoutGlobalScope('company')->create([
                    'company_id' => $companyId,
                    'kind' => $kind,
                    'label' => $row['label'],
                    'code' => $row['code'] ?? null,
                    'meta' => $row['meta'] ?? null,
                    'position' => $position,
                ]);
            }
        }

        self::importClients($companyId);
        self::seedRoles($companyId);
    }

    /**
     * Pose les rôles du personnel pour une entreprise déjà ouverte.
     * Un intitulé déjà porté par quelqu’un est conservé, avec une explication à compléter.
     */
    public static function seedRoles(int $companyId): void
    {
        $dejaLa = ReferentielItem::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('kind', 'role')
            ->exists();

        if (! $dejaLa) {
            foreach (self::rolesParDefaut() as $row) {
                self::ensure($companyId, 'role', $row['label'], $row['meta']);
            }
        }

        $titres = StaffMember::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->pluck('role_title');

        foreach ($titres as $titre) {
            $titre = trim((string) $titre);
            if ($titre === '') {
                continue;
            }
            self::ensure($companyId, 'role', $titre, [
                'explication' => 'Rôle déjà utilisé dans l’équipe. L’administrateur peut préciser ce qu’il recouvre.',
            ]);
        }
    }

    public static function importClients(int $companyId): void
    {
        $clients = Client::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->get();

        foreach ($clients as $client) {
            $city = trim((string) $client->city);
            if ($city !== '') {
                self::ensure($companyId, 'ville', $city);
            }
            $country = trim((string) $client->country);
            if ($country !== '') {
                self::ensure($companyId, 'pays', $country);
            }
            if ($client->societe_id || $client->kind === 'individuel') {
                continue;
            }
            $label = trim((string) $client->company);
            if ($label === '') {
                continue;
            }
            $item = self::ensure($companyId, 'societe', $label, [
                'contact_name' => $client->contact_name,
                'email' => $client->email,
                'phone' => $client->phone,
                'address' => $client->address,
                'city' => $client->city,
                'tax_id' => $client->tax_id,
            ]);
            $client->kind = 'entreprise';
            $client->societe_id = $item->id;
            $client->save();
        }
    }

    public static function ensure(int $companyId, string $kind, string $label, ?array $meta = null): ReferentielItem
    {
        $query = ReferentielItem::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('kind', $kind)
            ->where('label', $label);

        $existing = $query->first();
        if ($existing) {
            return $existing;
        }

        $position = (int) ReferentielItem::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('kind', $kind)
            ->max('position') + 1;

        return ReferentielItem::withoutGlobalScope('company')->create([
            'company_id' => $companyId,
            'kind' => $kind,
            'label' => $label,
            'code' => $kind === 'statut' ? self::codeFor($companyId, $label) : null,
            'meta' => $meta,
            'position' => $position,
        ]);
    }

    public static function codeFor(int $companyId, string $label, ?int $ignoreId = null): string
    {
        $base = Str::slug($label, '_');
        $base = $base !== '' ? substr($base, 0, 32) : 'statut';
        $code = $base;
        $suffix = 2;
        while (
            ReferentielItem::withoutGlobalScope('company')
                ->where('company_id', $companyId)
                ->where('kind', 'statut')
                ->where('code', $code)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $code = $base.'_'.$suffix;
            $suffix++;
        }

        return $code;
    }
}
