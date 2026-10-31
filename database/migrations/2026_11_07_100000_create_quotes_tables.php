<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bibliothèque d'ouvrages (RG5.1) : un ouvrage regroupe des articles du référentiel et de la main-d'œuvre.
        Schema::create('ouvrages', function (Blueprint $table): void {
            $table->id();
            $table->string('label', 200);
            $table->string('unit', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ouvrage_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ouvrage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_id')->nullable()->constrained();
            $table->string('label', 200)->nullable(); // main-d'œuvre ou fourniture hors référentiel
            $table->quantity('quantity');
            $table->fcfa('unit_price')->nullable(); // pour les lignes sans article
            $table->timestamps();
        });

        // Devis (M5). Le numéro est partagé par toutes les versions (RG5.3).
        Schema::create('quotes', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 40);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('client_id')->constrained();
            $table->foreignId('project_id')->constrained();
            $table->date('quote_date');
            $table->string('status', 20)->default('brouillon');
            $table->decimal('discount_rate', 5, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(18);
            $table->fcfa('direct_cost')->default(0); // déboursé sec (RG5.2)
            $table->text('refusal_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['number', 'version']);
        });

        Schema::create('quote_works', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ouvrage_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('label', 200);
            $table->string('unit', 20);
            $table->quantity('quantity');
            $table->fcfa('unit_price');
            $table->text('components')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_works');
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('ouvrage_components');
        Schema::dropIfExists('ouvrages');
    }
};
