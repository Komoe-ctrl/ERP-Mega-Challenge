<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Révisions du budget (RG10.2) : motif, auteur et validation historisés.
        // Une révision en attente ne change pas le budget révisé.
        Schema::create('budget_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chantier_id')->constrained()->restrictOnDelete();
            $table->foreignId('budget_line_id')->constrained('budget_lines')->restrictOnDelete();
            // Montant signé : une augmentation ou une diminution du budget révisé du poste.
            $table->fcfa('amount');
            $table->text('reason');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('en_attente')->index();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestamps();
        });

        // Dépenses diverses du chantier (RG10.4) : petits achats, transport, avec justificatif.
        Schema::create('budget_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chantier_id')->constrained()->restrictOnDelete();
            $table->foreignId('budget_line_id')->constrained('budget_lines')->restrictOnDelete();
            $table->string('label');
            $table->fcfa('amount');
            $table->date('spent_on');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_expenses');
        Schema::dropIfExists('budget_revisions');
    }
};
