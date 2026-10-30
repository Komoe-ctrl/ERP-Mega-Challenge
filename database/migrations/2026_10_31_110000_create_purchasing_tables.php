<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Compteurs de numérotation des documents (RG1.3) : un compteur par type et par année.
        Schema::create('number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 30);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last')->default(0);
            $table->unique(['type', 'year']);
        });

        // Consultation de fournisseurs sur une demande validée (M8.2, RG8.11).
        Schema::create('consultations', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('purchase_request_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('chantier_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('ouverte')->index();
            $table->date('deadline');
            $table->text('note')->nullable();
            $table->unsignedTinyInteger('min_suppliers')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('consultation_quotes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('consultation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->fcfa('total_amount');
            $table->unsignedSmallInteger('delay_days');
            $table->boolean('is_selected')->default(false);
            $table->timestamps();
            $table->unique(['consultation_id', 'supplier_id']);
        });

        // Bons de commande (RG8.8) : peuvent regrouper plusieurs demandes d'un même chantier.
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('chantier_id')->constrained()->restrictOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('emis')->index();
            $table->date('ordered_on');
            $table->fcfa('amount');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('purchase_order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            // Une ligne de demande peut être servie par plusieurs commandes (commandes partielles, RG8.8).
            $table->foreignId('purchase_request_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('article_id')->constrained()->restrictOnDelete();
            $table->quantity('quantity');
            $table->fcfa('unit_price');
            $table->quantity('received_quantity')->default(0);
            $table->timestamps();
        });

        // Solde progressif d'un engagement à la réception (RG10.1).
        Schema::table('budget_commitments', function (Blueprint $table): void {
            $table->fcfa('settled_amount')->default(0)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('budget_commitments', function (Blueprint $table): void {
            $table->dropColumn('settled_amount');
        });
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('consultation_quotes');
        Schema::dropIfExists('consultations');
        Schema::dropIfExists('number_sequences');
    }
};
