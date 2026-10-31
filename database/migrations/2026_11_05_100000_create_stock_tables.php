<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RG9.1 : chaque magasin (dépôt central ou magasin de chantier) a son propre stock.
        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('type', 20); // central | chantier
            $table->foreignId('chantier_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Stock d'un article dans un magasin : quantité, coût moyen pondéré (RG9.5), seuil minimal (RG9.4).
        Schema::create('stock_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_id')->constrained();
            $table->quantity('quantity')->default(0);
            $table->fcfa('average_cost')->default(0);
            $table->quantity('minimum')->default(0);
            $table->timestamps();

            $table->unique(['warehouse_id', 'article_id']);
        });

        // Mouvements tracés : entrée, sortie, transfert, retour, perte, ajustement, inventaire.
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 20);
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('destination_warehouse_id')->nullable()->constrained('warehouses');
            $table->foreignId('article_id')->constrained();
            $table->quantity('quantity'); // toujours positif
            $table->string('direction', 3); // in | out : effet sur le magasin d'origine
            $table->fcfa('unit_cost')->default(0);
            $table->foreignId('chantier_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('source'); // réception, inventaire…
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('valide'); // valide | en_attente | rejete
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('validated_at')->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'article_id']);
            $table->index(['type', 'status']);
        });

        // Inventaire : écart entre stock théorique et stock compté, ajusté après validation (CA9).
        Schema::create('inventories', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('warehouse_id')->constrained();
            $table->string('status', 20)->default('ouvert'); // ouvert | valide
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('validated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_id')->constrained();
            $table->quantity('theoretical');
            $table->quantity('counted')->nullable();
            $table->timestamps();

            $table->unique(['inventory_id', 'article_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_lines');
        Schema::dropIfExists('inventories');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_levels');
        Schema::dropIfExists('warehouses');
    }
};
