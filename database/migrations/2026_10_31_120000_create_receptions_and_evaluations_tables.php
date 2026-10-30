<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Réception d'une livraison (RG8.9) : quantités réellement livrées, reliquat ou retour.
        Schema::create('receptions', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->date('received_on');
            $table->string('gap', 20)->default('conforme');
            $table->text('note')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('reception_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reception_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->constrained()->restrictOnDelete();
            $table->quantity('delivered_quantity');
            // Quantité refusée et renvoyée au fournisseur : elle ne compte pas dans le coût réel.
            $table->quantity('returned_quantity')->default(0);
            $table->quantity('accepted_quantity');
            $table->timestamps();
        });

        // Évaluation du fournisseur après chaque réception (RG3.3).
        Schema::create('supplier_evaluations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('reception_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('delay_score');
            $table->unsignedTinyInteger('quality_score');
            $table->unsignedTinyInteger('price_score');
            $table->text('comment')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_evaluations');
        Schema::dropIfExists('reception_lines');
        Schema::dropIfExists('receptions');
    }
};
