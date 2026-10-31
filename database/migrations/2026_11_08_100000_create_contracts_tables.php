<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Marché (M6) : naît d'un devis validé (RG6.1) ; ses conditions de paiement servent aux situations (RG6.3).
        Schema::create('contracts', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('quote_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->constrained();
            $table->foreignId('project_id')->constrained();
            $table->date('signed_at');
            $table->fcfa('initial_amount');
            $table->unsignedInteger('deadline_days');
            $table->decimal('advance_rate', 5, 2)->default(0);
            $table->decimal('retention_rate', 5, 2)->default(0);
            $table->unsignedInteger('payment_days')->default(30);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Avenant : modifie le montant et le délai une fois validé (RG6.2).
        Schema::create('contract_amendments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->string('number', 20);
            $table->date('amendment_date');
            $table->string('label', 200);
            $table->bigInteger('amount'); // positif (plus-value) ou négatif (moins-value)
            $table->integer('days')->default(0);
            $table->boolean('is_validated')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('validated_at')->nullable();
            $table->timestamps();

            $table->unique(['contract_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_amendments');
        Schema::dropIfExists('contracts');
    }
};
