<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Compte client : un utilisateur de rôle "client" est rattaché à une fiche client.
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
        });

        // Traçabilité de la décision sur un devis (UC06).
        Schema::table('quotes', function (Blueprint $table) {
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_note')->nullable();
        });

        // Historique des mises à jour d'avancement (UC11).
        Schema::create('project_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('progress');
            $table->string('status');
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_updates');
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('decided_by');
            $table->dropColumn('decision_note');
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('client_id'));
    }
};
