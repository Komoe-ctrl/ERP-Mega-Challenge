<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Poste occupé (affiché dans l'en-tête avec le service) : « Administrateur »,
        // « Technicien 1 »… Le compte reste nominatif, le poste n'est qu'un libellé.
        Schema::table('users', function (Blueprint $table): void {
            $table->string('job_title', 120)->nullable()->after('service_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('job_title');
        });
    }
};
