<?php

use App\Models\Company;
use App\Support\ReferentielCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referentiel_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 24);
            $table->string('label');
            $table->string('code', 40)->nullable();
            $table->json('meta')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['company_id', 'kind', 'label']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->string('kind', 20)->default('entreprise');
            $table->foreignId('societe_id')->nullable()->constrained('referentiel_items')->nullOnDelete();
        });

        Company::query()->orderBy('id')->pluck('id')->each(function ($id) {
            ReferentielCatalog::seed((int) $id);
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('societe_id');
            $table->dropColumn('kind');
        });
        Schema::dropIfExists('referentiel_items');
    }
};
