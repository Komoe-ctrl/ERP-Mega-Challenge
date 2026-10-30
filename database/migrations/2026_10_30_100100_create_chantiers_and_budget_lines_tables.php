<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Chantier d'un projet (M7). Le modèle de données couvre tous les modules dès la V1.
        Schema::create('chantiers', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('location');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 30)->default('preparation')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        // Affectations explicites (RG1.1, RG7.2) : un utilisateur ne voit que ses chantiers.
        Schema::create('chantier_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chantier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 30);
            $table->timestamps();
            $table->unique(['chantier_id', 'user_id', 'role']);
        });

        // Postes du déboursé sec (M10) : budget initial par poste et par nature.
        Schema::create('budget_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chantier_id')->constrained()->cascadeOnDelete();
            $table->string('post');
            $table->string('nature', 20);
            $table->fcfa('initial_amount');
            $table->fcfa('revised_amount');
            $table->fcfa('committed_amount')->default(0);
            $table->fcfa('actual_amount')->default(0);
            $table->timestamps();
            $table->unique(['chantier_id', 'post', 'nature']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('chantier_user');
        Schema::dropIfExists('chantiers');
    }
};
