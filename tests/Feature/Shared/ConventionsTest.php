<?php

declare(strict_types=1);

use App\Domain\Shared\Support\BudgetLock;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

describe('verrou de budget (BudgetLock)', function () {
    it('exécute le callback dans une transaction avec SELECT ... FOR UPDATE', function () {
        User::factory()->count(2)->create();
        DB::enableQueryLog();
        $level = null;

        $locked = BudgetLock::run(User::query(), function (Collection $rows) use (&$level) {
            $level = DB::transactionLevel();

            return $rows;
        });

        $queries = collect(DB::getQueryLog())->pluck('query')->implode(' | ');
        expect($locked)->toHaveCount(2)
            ->and($level)->toBeGreaterThan(0)
            ->and(strtolower($queries))->toContain('for update');
    });

    it('annule tout si le callback échoue', function () {
        $user = User::factory()->create(['name' => 'Avant']);

        expect(fn () => BudgetLock::run(User::query(), function () use ($user) {
            $user->update(['name' => 'Pendant']);

            throw new RuntimeException('budget dépassé');
        }))->toThrow(RuntimeException::class, 'budget dépassé');

        expect($user->fresh()->name)->toBe('Avant');
    });
});

describe('conventions de données', function () {
    it('stocke les montants FCFA en BIGINT et les quantités en DECIMAL', function () {
        Schema::create('tmp_conventions', function (Blueprint $table) {
            $table->id();
            $table->fcfa('montant');
            $table->quantity('quantite');
        });

        $types = collect(Schema::getColumns('tmp_conventions'))->keyBy('name');
        Schema::drop('tmp_conventions');

        expect($types['montant']['type_name'])->toBe('bigint')
            ->and($types['quantite']['type_name'])->toBe('decimal')
            ->and($types['quantite']['type'])->toBe('decimal(14,3)');
    });

    it('utilise le fuseau Africa/Abidjan et la locale fr', function () {
        expect(config('app.timezone'))->toBe('Africa/Abidjan')
            ->and(app()->getLocale())->toBe('fr')
            ->and(__('validation.required', ['attribute' => 'nom']))->toContain('obligatoire');
    });

    it('utilise MySQL en utf8mb4', function () {
        expect(DB::connection()->getDriverName())->toBe('mysql')
            ->and(config('database.connections.mysql.charset'))->toBe('utf8mb4');
    });
});
