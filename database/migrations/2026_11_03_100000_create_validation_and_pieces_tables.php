<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Circuit de validation unique, paramétré dans Administration : une étape = un rôle (jamais un compte).
        Schema::create('validation_steps', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('position')->unique();
            $table->string('label', 150);
            $table->string('role_name', 100);
            $table->string('button_label', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Rubriques budgétaires et budget par exercice.
        Schema::create('rubriques', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('label', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('rubrique_budgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rubrique_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->fcfa('amount');
            $table->timestamps();

            $table->unique(['rubrique_id', 'fiscal_year_id']);
        });

        // Imputation analytique : Client / Projet → Produit / Lot / Section → Composante.
        Schema::create('project_lots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->timestamps();
        });

        Schema::create('lot_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_lot_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->timestamps();
        });

        // Pièce commune : note de frais, recette, pièce de caisse.
        Schema::create('pieces', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 20);
            $table->string('number', 40);
            $table->char('period', 7); // YYYY-MM
            $table->foreignId('fiscal_year_id')->constrained();
            $table->string('title', 200);
            $table->foreignId('requester_id')->constrained('users');
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_lot_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lot_component_id')->nullable()->constrained()->nullOnDelete();
            $table->string('bc_number', 40)->nullable();
            $table->text('observation')->nullable();
            $table->string('status', 20)->default('saisie');
            $table->unsignedSmallInteger('current_step')->nullable();
            $table->fcfa('total')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['kind', 'number']);
            $table->index(['kind', 'status']);
        });

        Schema::create('piece_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('piece_id')->constrained()->cascadeOnDelete();
            $table->string('designation', 250);
            $table->quantity('quantity');
            $table->fcfa('unit_price');
            $table->fcfa('amount');
            $table->foreignId('rubrique_id')->constrained();
            $table->timestamps();
        });

        // Historique du circuit, lu par « Suivi workflow » : qui, quand, quelle étape, quel commentaire.
        Schema::create('validation_events', function (Blueprint $table): void {
            $table->id();
            $table->morphs('subject');
            $table->unsignedSmallInteger('step_position')->nullable();
            $table->string('action', 20);
            $table->string('step_label', 150)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role_name', 100)->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('validation_events');
        Schema::dropIfExists('piece_lines');
        Schema::dropIfExists('pieces');
        Schema::dropIfExists('lot_components');
        Schema::dropIfExists('project_lots');
        Schema::dropIfExists('rubrique_budgets');
        Schema::dropIfExists('rubriques');
        Schema::dropIfExists('validation_steps');
    }
};
