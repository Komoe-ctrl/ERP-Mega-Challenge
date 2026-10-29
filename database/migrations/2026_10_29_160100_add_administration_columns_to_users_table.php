<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RG1.1 : chaque utilisateur a une agence et un service.
        // Un compte est désactivé, jamais supprimé : `is_active` + soft delete.
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('agency_id')->nullable()->after('email')->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->after('agency_id')->constrained()->nullOnDelete();
            $table->string('phone', 30)->nullable()->after('service_id');
            $table->boolean('is_active')->default(true)->after('phone');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('agency_id');
            $table->dropConstrainedForeignId('service_id');
            $table->dropColumn(['phone', 'is_active']);
            $table->dropSoftDeletes();
        });
    }
};
