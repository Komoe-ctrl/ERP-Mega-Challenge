<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agencies', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Sites physiques d'une agence : siège, dépôt central, magasin de chantier...
        Schema::create('sites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->restrictOnDelete();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('type', 30)->default('bureau');
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Paramètres clé/valeur typés (RG1.3). La valeur est stockée en texte
        // et convertie selon la colonne `type`.
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group', 50)->index();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string');
            $table->string('label');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Exercices comptables : un exercice clôturé passe en lecture seule (RG1.4).
        Schema::create('fiscal_years', function (Blueprint $table): void {
            $table->id();
            $table->string('label', 50)->unique();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 20)->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_years');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('sites');
        Schema::dropIfExists('services');
        Schema::dropIfExists('agencies');
    }
};
