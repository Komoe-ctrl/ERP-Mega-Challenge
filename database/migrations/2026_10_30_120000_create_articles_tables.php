<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Référentiel des prix (M8.3). Un article n'est jamais supprimé : il est désactivé (RG8.14).
        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->string('label');
            $table->string('category', 50)->index();
            $table->string('unit', 20);
            $table->fcfa('price');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Chaque changement de prix est historisé (RG8.13).
        Schema::create('article_price_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->fcfa('price');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason');
            $table->timestamp('created_at')->useCurrent();
        });

        // Un demandeur signale un article manquant ; le Service Achats BTP reçoit la demande (RG8.15).
        Schema::create('missing_article_reports', function (Blueprint $table): void {
            $table->id();
            $table->string('label');
            $table->foreignId('requester_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('chantier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('a_traiter')->index();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('missing_article_reports');
        Schema::dropIfExists('article_price_histories');
        Schema::dropIfExists('articles');
    }
};
