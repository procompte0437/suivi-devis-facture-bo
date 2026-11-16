<?php

use App\Models\PlatformCity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_cities', function (Blueprint $table) {
            $table->id();
            $table->string('label')->unique();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        foreach (['Libreville', 'Port-Gentil', 'Franceville', 'Oyem', 'Mouila', 'Lambaréné'] as $position => $label) {
            PlatformCity::query()->create([
                'label' => $label,
                'position' => $position,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_cities');
    }
};
