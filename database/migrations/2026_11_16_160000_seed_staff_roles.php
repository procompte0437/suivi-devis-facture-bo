<?php

use App\Models\Company;
use App\Support\ReferentielCatalog;
use Illuminate\Database\Migrations\Migration;

/**
 * Ajoute les rôles du personnel aux entreprises déjà ouvertes.
 * Chaque rôle porte une explication de ce qu’il fait.
 */
return new class extends Migration
{
    public function up(): void
    {
        Company::query()->orderBy('id')->pluck('id')->each(function ($id) {
            ReferentielCatalog::seedRoles((int) $id);
        });
    }

    public function down(): void
    {
        // Les rôles saisis ensuite par l’administrateur restent en place.
    }
};
