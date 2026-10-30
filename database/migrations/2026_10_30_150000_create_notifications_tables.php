<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Centre de notifications de l'application (canal « database » de Laravel).
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // Canaux paramétrables par utilisateur : centre de notifications et e-mail.
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('notify_app')->default(true)->after('is_active');
            $table->boolean('notify_email')->default(true)->after('notify_app');
        });

        // Journal des alertes envoyées : une même alerte n'est pas renvoyée plus d'une fois par jour.
        Schema::create('sent_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('dedup_key', 120);
            $table->date('sent_on');
            $table->unique(['user_id', 'dedup_key', 'sent_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sent_alerts');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['notify_app', 'notify_email']);
        });
        Schema::dropIfExists('notifications');
    }
};
