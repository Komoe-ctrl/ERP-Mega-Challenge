<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pointage d'arrivée du personnel : une ligne par utilisateur et par jour.
        Schema::create('attendances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->dateTime('arrived_at');
            $table->timestamps();

            $table->unique(['user_id', 'work_date']);
        });

        // Postes d'un utilisateur (titre + service). Un compte peut en occuper plusieurs :
        // l'en-tête propose alors un sélecteur de poste. Sans ligne, le poste est celui du compte
        // (`users.job_title` et `users.service_id`).
        Schema::create('user_postes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'title']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_postes');
        Schema::dropIfExists('attendances');
    }
};
