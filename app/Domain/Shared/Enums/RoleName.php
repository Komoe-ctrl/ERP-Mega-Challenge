<?php

declare(strict_types=1);

namespace App\Domain\Shared\Enums;

/**
 * Les 10 profils du cahier des charges (section 4), utilisés comme rôles.
 *
 * `matrix()` fournit la matrice de droits initiale semée en base ; elle peut
 * ensuite être ajustée sans développement depuis l'écran des rôles (RG1.2).
 */
enum RoleName: string
{
    case Administrateur = 'administrateur';
    case DirectionGenerale = 'direction_generale';
    case DirectionTechnique = 'direction_technique';
    case ServiceAchatsBtp = 'service_achats_btp';
    case ConducteurTravaux = 'conducteur_travaux';
    case ChefChantier = 'chef_chantier';
    case Magasinier = 'magasinier';
    case Comptabilite = 'comptabilite';
    case Finance = 'finance';
    case RessourcesHumaines = 'ressources_humaines';

    public function label(): string
    {
        return match ($this) {
            self::Administrateur => 'Administrateur',
            self::DirectionGenerale => 'Direction générale',
            self::DirectionTechnique => 'Direction technique',
            self::ServiceAchatsBtp => 'Service Achats BTP',
            self::ConducteurTravaux => 'Conducteur de travaux',
            self::ChefChantier => 'Chef de chantier',
            self::Magasinier => 'Magasinier',
            self::Comptabilite => 'Comptabilité',
            self::Finance => 'Finance',
            self::RessourcesHumaines => 'Ressources humaines',
        };
    }

    /** Poste par défaut d'un compte de ce profil (affiché dans l'en-tête avec le service). */
    public function defaultJobTitle(): string
    {
        return match ($this) {
            self::Administrateur => 'Administrateur',
            self::DirectionGenerale => 'Directeur général',
            self::DirectionTechnique => 'Directeur technique',
            self::ServiceAchatsBtp => 'Responsable achats BTP',
            self::ConducteurTravaux => 'Conducteur de travaux',
            self::ChefChantier => 'Chef de chantier',
            self::Magasinier => 'Magasinier',
            self::Comptabilite => 'Comptable',
            self::Finance => 'Responsable financier',
            self::RessourcesHumaines => 'Chargée des ressources humaines',
        };
    }

    /**
     * Page d'arrivée après la connexion et permission qui la protège :
     * le tableau de bord de ce profil.
     *
     * @return array{string, string|null}
     */
    public function home(): array
    {
        return match ($this) {
            self::Administrateur => ['/administration', 'administration.view'],
            self::DirectionGenerale, self::DirectionTechnique => ['/dashboard', null],
            self::ServiceAchatsBtp => ['/achats/a-valider', 'achats.view'],
            self::ConducteurTravaux, self::ChefChantier => ['/chantiers', 'chantiers.view'],
            self::Magasinier => ['/achats/receptions', 'achats.view'],
            self::Comptabilite => ['/achats/factures', 'achats.view'],
            self::Finance => ['/ecrans/situation-caisse', 'finance.view'],
            self::RessourcesHumaines => ['/rh', 'rh.view'],
        };
    }

    /** Profils pour lesquels la double authentification est obligatoire. */
    public function requiresTwoFactor(): bool
    {
        return in_array($this, [
            self::Administrateur,
            self::DirectionGenerale,
            self::DirectionTechnique,
            self::Finance,
        ], true);
    }

    /**
     * Matrice de droits initiale : module => actions.
     *
     * @return array<string, list<PermissionAction>>
     */
    public function matrix(): array
    {
        $read = [PermissionAction::View];
        $write = [PermissionAction::View, PermissionAction::Create, PermissionAction::Update];
        $full = [...$write, PermissionAction::Validate];

        return match ($this) {
            // L'Administrateur ne gère que l'Administration (comptes, rôles, paramètres,
            // journal) : il ne voit aucun module métier.
            self::Administrateur => [
                Module::Administration->value => [...$full, PermissionAction::Delete],
            ],
            // Validations de niveau direction. Lecture : tous les modules.
            self::DirectionGenerale => $this->allModules([PermissionAction::View, PermissionAction::Validate]),
            self::DirectionTechnique => [
                Module::Projets->value => $full,
                Module::Chantiers->value => $full,
                Module::Devis->value => $full,
                Module::Achats->value => [...$read, PermissionAction::Validate],
                Module::Stocks->value => $read,
                Module::Budget->value => $read,
                Module::Engins->value => $read,
            ],
            self::ServiceAchatsBtp => [
                Module::Achats->value => $full,
                Module::Fournisseurs->value => $full,
                Module::Chantiers->value => $read,
                Module::Budget->value => $read,
                Module::Stocks->value => $read,
            ],
            self::ConducteurTravaux => [
                Module::Chantiers->value => $write,
                Module::Achats->value => $full,
                Module::Budget->value => $read,
                Module::Stocks->value => $read,
            ],
            self::ChefChantier => [
                Module::Chantiers->value => [PermissionAction::View, PermissionAction::Update],
                Module::Achats->value => $write,
                Module::Budget->value => $read,
            ],
            self::Magasinier => [
                Module::Stocks->value => $write,
                Module::Achats->value => $write,
                Module::Chantiers->value => $read,
            ],
            self::Comptabilite => [
                Module::Finance->value => $write,
                Module::Achats->value => $read,
                Module::Clients->value => $read,
                Module::Situations->value => $read,
            ],
            self::Finance => [
                Module::Finance->value => $full,
                Module::Budget->value => $read,
                Module::Achats->value => $read,
                Module::Clients->value => $read,
                Module::Fournisseurs->value => $read,
                Module::Marches->value => $read,
                Module::Situations->value => $read,
            ],
            self::RessourcesHumaines => [
                Module::Rh->value => $write,
                Module::MainOeuvre->value => $write,
                Module::Chantiers->value => $read,
            ],
        };
    }

    /**
     * @return list<string> noms de permissions `<module>.<action>`
     */
    public function defaultPermissions(): array
    {
        $names = [];
        foreach ($this->matrix() as $module => $actions) {
            foreach ($actions as $action) {
                $names[] = $module.'.'.$action->value;
            }
        }

        return $names;
    }

    /**
     * @param  list<PermissionAction>  $actions
     * @return array<string, list<PermissionAction>>
     */
    private function allModules(array $actions): array
    {
        $matrix = [];
        foreach (Module::cases() as $module) {
            $matrix[$module->value] = $actions;
        }

        return $matrix;
    }
}
