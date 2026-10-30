<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Données de base de l'ERP. Tous les seeders sont rejouables : ils ne
 * recréent ni n'écrasent ce qu'un administrateur a déjà ajusté.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SettingsSeeder::class,
            OrganizationSeeder::class,
            FiscalYearSeeder::class,
            PiecesSeeder::class,
            AdminUserSeeder::class,
        ]);

        if (config('erp.seed_demo_users') === true) {
            $this->call(DemoUsersSeeder::class);
            $this->call(ProjectsSeeder::class);
            $this->call(ChantiersSeeder::class);
            $this->call(ArticlesSeeder::class);
            $this->call(BudgetSeeder::class);
            $this->call(PurchaseRequestsSeeder::class);
            $this->call(SuppliersSeeder::class);
            $this->call(PiecesSeeder::class);
        }
    }
}
