<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Images du site public remplacées depuis l'administration (une ligne par emplacement).
        Schema::create('site_images', function (Blueprint $table) {
            $table->id();
            $table->string('slot', 60)->unique();
            $table->string('path')->nullable();
            $table->string('alt', 200)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_images');
    }
};
