<?php

declare(strict_types=1);

namespace App\Domain\Shared\Enums;

/**
 * Les 15 modules de l'ERP (cahier des charges, section 3).
 *
 * La valeur est le slug utilisé dans les permissions (`<module>.<action>`),
 * dans les dossiers de code et dans les URL.
 */
enum Module: string
{
    case Administration = 'administration';
    case Clients = 'clients';
    case Fournisseurs = 'fournisseurs';
    case Projets = 'projets';
    case Devis = 'devis';
    case Marches = 'marches';
    case Chantiers = 'chantiers';
    case Achats = 'achats';
    case Stocks = 'stocks';
    case Budget = 'budget';
    case Situations = 'situations';
    case MainOeuvre = 'main_oeuvre';
    case Rh = 'rh';
    case Engins = 'engins';
    case Finance = 'finance';

    public function label(): string
    {
        return match ($this) {
            self::Administration => 'Administration',
            self::Clients => 'Clients',
            self::Fournisseurs => 'Fournisseurs',
            self::Projets => 'Projets',
            self::Devis => 'Devis',
            self::Marches => 'Marchés et contrats',
            self::Chantiers => 'Chantiers',
            self::Achats => 'Service Achats BTP',
            self::Stocks => 'Stocks BTP',
            self::Budget => 'Budget chantier',
            self::Situations => 'Situations de travaux',
            self::MainOeuvre => "Main-d'œuvre",
            self::Rh => 'Ressources humaines',
            self::Engins => 'Engins et équipements',
            self::Finance => 'Finance et comptabilité',
        };
    }

    /** Version de livraison (1 à 4) du module. */
    public function version(): int
    {
        return match ($this) {
            self::Administration, self::Projets, self::Chantiers, self::Achats, self::Budget => 1,
            self::Fournisseurs, self::Stocks => 2,
            self::Clients, self::Devis, self::Marches, self::Situations => 3,
            self::MainOeuvre, self::Rh, self::Engins, self::Finance => 4,
        };
    }

    /** Nom de l'icône Lucide utilisée dans le menu latéral. */
    public function icon(): string
    {
        return match ($this) {
            self::Administration => 'Settings',
            self::Clients => 'Users',
            self::Fournisseurs => 'Truck',
            self::Projets => 'FolderKanban',
            self::Devis => 'FileText',
            self::Marches => 'Handshake',
            self::Chantiers => 'HardHat',
            self::Achats => 'ShoppingCart',
            self::Stocks => 'Warehouse',
            self::Budget => 'Wallet',
            self::Situations => 'Receipt',
            self::MainOeuvre => 'Hammer',
            self::Rh => 'IdCard',
            self::Engins => 'Tractor',
            self::Finance => 'Landmark',
        };
    }

    /**
     * Route d'entrée du module, tant qu'elle existe (null = module à venir).
     */
    public function entryRoute(): ?string
    {
        return match ($this) {
            self::Administration => 'administration.users.index',
            default => null,
        };
    }

    /** Un module s'active indépendamment (config `erp.modules_enabled`). */
    public function isEnabled(): bool
    {
        /** @var list<string> $enabled */
        $enabled = config('erp.modules_enabled', []);

        return in_array($this->value, $enabled, true);
    }

    public function permission(PermissionAction $action): string
    {
        return $this->value.'.'.$action->value;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return array_map(fn (PermissionAction $a): string => $this->permission($a), PermissionAction::cases());
    }

    /**
     * @return list<string>
     */
    public static function allPermissions(): array
    {
        $all = [];
        foreach (self::cases() as $module) {
            array_push($all, ...$module->permissions());
        }

        return $all;
    }
}
