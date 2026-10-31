<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Situation de travaux (M11) : facture intermédiaire calculée sur l'avancement (RG11.1).
        Schema::create('situations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_id')->constrained();
            $table->unsignedInteger('number');
            $table->date('situation_date');
            $table->decimal('progress_cumul', 5, 2); // avancement cumulé en %
            $table->decimal('previous_progress', 5, 2)->default(0);
            $table->fcfa('market_amount'); // montant actualisé du marché au moment du calcul
            $table->decimal('retention_rate', 5, 2)->default(0);
            $table->decimal('advance_refund_rate', 5, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(18);
            $table->string('status', 20)->default('brouillon'); // brouillon | validee | payee
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('validated_at')->nullable();
            $table->timestamps();

            $table->unique(['contract_id', 'number']);
        });

        // Avancement cumulé par ouvrage du devis du marché (RG11.1).
        Schema::create('situation_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('situation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_work_id')->constrained()->cascadeOnDelete();
            $table->decimal('progress', 5, 2);
            $table->timestamps();

            $table->unique(['situation_id', 'quote_work_id']);
        });

        // Paiements reçus du client sur une situation validée.
        Schema::create('situation_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('situation_id')->constrained()->cascadeOnDelete();
            $table->date('paid_at');
            $table->fcfa('amount');
            $table->string('reference', 100)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('situation_payments');
        Schema::dropIfExists('situation_lines');
        Schema::dropIfExists('situations');
    }
};
