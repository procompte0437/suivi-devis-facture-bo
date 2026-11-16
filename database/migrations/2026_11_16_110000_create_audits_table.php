<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->text('description');
            $table->nullableMorphs('subject');
            $table->string('http_method', 12)->nullable();
            $table->string('path')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('browser')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        $rows = DB::table('activities')->orderBy('id')->get();
        foreach ($rows as $row) {
            DB::table('audits')->insert([
                'user_id' => $row->user_id,
                'action' => $row->action,
                'description' => $row->description,
                'subject_type' => $row->subject_type,
                'subject_id' => $row->subject_id,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audits');
    }
};
