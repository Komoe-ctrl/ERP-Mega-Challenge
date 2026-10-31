<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fiche client complète (M2) : coordonnées et contacts multiples.
        Schema::table('clients', function (Blueprint $table): void {
            $table->string('email')->nullable()->after('phone');
            $table->string('address')->nullable()->after('city');
        });

        Schema::create('client_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('role', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_contacts');
        Schema::table('clients', function (Blueprint $table): void {
            $table->dropColumn(['email', 'address']);
        });
    }
};
