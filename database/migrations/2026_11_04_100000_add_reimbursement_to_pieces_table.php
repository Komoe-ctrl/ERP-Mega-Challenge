<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Facture de remboursement d'une note de frais approuvée.
        Schema::table('pieces', function (Blueprint $table): void {
            $table->string('invoice_number', 40)->nullable()->after('total');
            $table->dateTime('reimbursed_at')->nullable()->after('invoice_number');
            $table->foreignId('reimbursed_by')->nullable()->after('reimbursed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pieces', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reimbursed_by');
            $table->dropColumn(['invoice_number', 'reimbursed_at']);
        });
    }
};
