<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fournisseurs (M3). Jamais supprimés : désactivés, pour conserver commandes et factures.
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 20)->unique();
            $table->string('name')->unique();
            $table->string('contact');
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->string('city');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Un fournisseur appartient à une ou plusieurs catégories (RG3.1).
        Schema::create('supplier_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('category', 50);
            $table->unique(['supplier_id', 'category']);
        });

        // Tarifs fournisseur par article (RG3.2) : ils alimentent le comparatif des offres ;
        // le référentiel des prix reste le prix de référence interne.
        Schema::create('supplier_tariffs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_id')->constrained()->restrictOnDelete();
            $table->fcfa('price');
            $table->timestamps();
            $table->unique(['supplier_id', 'article_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_tariffs');
        Schema::dropIfExists('supplier_categories');
        Schema::dropIfExists('suppliers');
    }
};
