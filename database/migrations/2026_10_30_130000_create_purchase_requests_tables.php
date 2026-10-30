<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Demande d'achat (M8.1) : rattachée à un seul chantier (RG8.1).
        Schema::create('purchase_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('chantier_id')->constrained()->restrictOnDelete();
            // Poste du déboursé sec visé, quand il est détaillé (RG8.1).
            $table->foreignId('budget_line_id')->nullable()->constrained('budget_lines')->nullOnDelete();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->text('justification')->nullable();
            $table->string('status', 20)->default('brouillon')->index();
            $table->boolean('needs_direction')->default(false);
            // Montant au moment de la dernière soumission ou de la validation (FCFA).
            $table->fcfa('total_amount')->default(0);
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_request_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_id')->constrained()->restrictOnDelete();
            $table->quantity('quantity');
            // Quantité ajustée par le valideur (RG8.5) ; l'écart est tracé dans l'historique.
            $table->quantity('adjusted_quantity')->nullable();
            $table->date('need_date');
            $table->string('urgency', 20)->default('normale');
            // Prix figés à la validation (RG8.6) : le prix ne vient jamais du demandeur (RG8.2).
            $table->fcfa('frozen_price')->nullable();
            $table->timestamps();
        });

        // Historique visible de la demande (création, soumission, décisions motivées).
        Schema::create('purchase_request_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // Engagements sur le budget d'un chantier (M10, RG10.1) : une demande validée crée
        // un engagement, soldé à la réception de la commande (V2) sans double comptage.
        Schema::create('budget_commitments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chantier_id')->constrained()->restrictOnDelete();
            $table->foreignId('budget_line_id')->constrained('budget_lines')->restrictOnDelete();
            $table->nullableMorphs('source');
            $table->fcfa('amount');
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_commitments');
        Schema::dropIfExists('purchase_request_events');
        Schema::dropIfExists('purchase_request_lines');
        Schema::dropIfExists('purchase_requests');
    }
};
