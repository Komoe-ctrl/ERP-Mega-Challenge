<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Modules actifs
    |--------------------------------------------------------------------------
    |
    | Liste (séparée par des virgules dans le .env) des modules activés. Un
    | module inactif n'apparaît pas dans le menu, même si l'utilisateur en a
    | le droit. À compléter au fil des versions : voir
    | App\Domain\Shared\Enums\Module.
    |
    */
    'modules_enabled' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ERP_MODULES_ENABLED', 'administration,clients,fournisseurs,projets,devis,marches,chantiers,achats,stocks,budget,situations,main_oeuvre,rh,engins,finance')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Double authentification obligatoire
    |--------------------------------------------------------------------------
    |
    | Direction, Finance et Administrateur doivent activer la double
    | authentification (exigences non fonctionnelles, section 11).
    |
    */
    'require_two_factor' => (bool) env('ERP_REQUIRE_TWO_FACTOR', true),

    /*
    |--------------------------------------------------------------------------
    | Compte administrateur de démonstration
    |--------------------------------------------------------------------------
    |
    | Lu uniquement par le seeder. Les valeurs viennent du .env, jamais du code.
    |
    */
    // Compte du script de navigation e2e (local uniquement, voir erp:e2e-user).
    'e2e' => [
        'email' => env('E2E_EMAIL'),
        'password' => env('E2E_PASSWORD'),
        'totp_secret' => env('E2E_TOTP_SECRET'),
    ],

    'demo_admin' => [
        'name' => env('ADMIN_NAME', 'Administrateur'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Utilisateurs de démonstration
    |--------------------------------------------------------------------------
    |
    | Un compte par profil (mot de passe : celui de ADMIN_PASSWORD), créés par
    | le seeder uniquement si ERP_SEED_DEMO_USERS=true. À laisser à false en
    | production.
    |
    */
    'seed_demo_users' => (bool) env('ERP_SEED_DEMO_USERS', false),

];
