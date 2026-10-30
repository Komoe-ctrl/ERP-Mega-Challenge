<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Planning d'un chantier (RG7.3) : tâches et jalons.
        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chantier_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->unsignedSmallInteger('weight')->default(1);
            $table->boolean('is_milestone')->default(false);
            $table->date('last_update_on');
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->index(['chantier_id', 'start_date']);
        });

        // Historique des mises à jour d'avancement (RG7.4) : jamais modifié.
        Schema::create('task_updates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('progress_before');
            $table->unsignedTinyInteger('progress_after');
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        // Rapport journalier du chef de chantier (RG7.9) : un par chantier et par jour.
        Schema::create('daily_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chantier_id')->constrained()->cascadeOnDelete();
            $table->date('report_date');
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('works');
            $table->unsignedInteger('headcount')->default(0);
            $table->text('materials_received')->nullable();
            $table->text('materials_used')->nullable();
            $table->text('incidents')->nullable();
            $table->timestamps();
            $table->unique(['chantier_id', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_reports');
        Schema::dropIfExists('task_updates');
        Schema::dropIfExists('tasks');
    }
};
