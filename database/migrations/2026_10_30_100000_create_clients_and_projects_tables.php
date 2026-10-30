<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Socle de la fiche client (M2, enrichie en V3) : un projet y est rattaché dès la V1.
        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 20)->unique();
            $table->string('name');
            $table->string('type', 30);
            $table->string('contact')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('city')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 20)->unique();
            $table->string('name');
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('location');
            $table->foreignId('manager_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('agency_id')->constrained()->restrictOnDelete();
            $table->fcfa('budget');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 30)->default('etude')->index();
            $table->text('abandon_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
        Schema::dropIfExists('clients');
    }
};
