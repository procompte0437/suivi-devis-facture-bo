<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('account_type')->default('entreprise');
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('tagline')->nullable();
            $table->string('legal_form')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->default('Gabon');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('capital')->nullable();
            $table->string('iban')->nullable();
            $table->text('payment_note')->nullable();
            $table->text('footer')->nullable();
            $table->timestamps();
        });

        Schema::create('company_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('owner');
            $table->timestamps();
            $table->unique(['company_id', 'user_id']);
        });

        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('role_title')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        foreach (['clients', 'projects', 'quotes', 'invoices'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            });
        }

        Schema::table('quotes', function (Blueprint $table) {
            $table->string('template')->default('nomade');
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('template')->default('cedre');
        });

        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('title');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->decimal('planned', 14, 2)->default(0);
            $table->decimal('spent', 14, 2)->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->string('guest_token', 64)->index();
            $table->string('role', 16);
            $table->text('body');
            $table->timestamps();
        });

        if (Schema::hasTable('clients') && DB::table('clients')->exists() && DB::table('companies')->doesntExist()) {
            $settings = DB::table('settings')->orderBy('id')->first();
            $companyId = DB::table('companies')->insertGetId([
                'name' => $settings->company_name ?? 'DOUKOU',
                'tagline' => $settings->tagline ?? null,
                'address' => $settings->address ?? null,
                'city' => $settings->city ?? null,
                'country' => $settings->country ?? 'Gabon',
                'email' => $settings->email ?? null,
                'phone' => $settings->phone ?? null,
                'tax_id' => $settings->tax_id ?? null,
                'payment_note' => $settings->payment_note ?? null,
                'footer' => $settings->footer ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $now = now();
            foreach (DB::table('users')->pluck('id') as $userId) {
                DB::table('company_user')->insert([
                    'company_id' => $companyId,
                    'user_id' => $userId,
                    'role' => 'owner',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach (['clients', 'projects', 'quotes', 'invoices'] as $table) {
                DB::table($table)->whereNull('company_id')->update(['company_id' => $companyId]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('project_tasks');
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn('template');
        });
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn('template');
        });
        Schema::table('projects', fn (Blueprint $table) => $table->dropConstrainedForeignId('company_id'));
        Schema::table('clients', fn (Blueprint $table) => $table->dropConstrainedForeignId('company_id'));
        Schema::dropIfExists('staff');
        Schema::dropIfExists('company_user');
        Schema::dropIfExists('companies');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('account_type'));
    }
};
