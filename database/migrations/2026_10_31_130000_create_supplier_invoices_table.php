<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Factures fournisseurs (étape 10, RG8.10) : contrôle à trois voies avant paiement.
        Schema::create('supplier_invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 50);
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('reception_id')->nullable()->constrained()->nullOnDelete();
            $table->date('invoice_date');
            $table->date('due_date');
            $table->fcfa('amount');
            $table->string('status', 20)->default('a_controler')->index();
            // Résultat du contrôle à trois voies : bon de commande, réception, prix.
            $table->boolean('match_order')->default(false);
            $table->boolean('match_reception')->default(false);
            $table->boolean('match_price')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('controlled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('controlled_at')->nullable();
            $table->date('paid_on')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['supplier_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoices');
    }
};
