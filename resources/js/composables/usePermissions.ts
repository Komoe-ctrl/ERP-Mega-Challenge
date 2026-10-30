import { usePage } from '@inertiajs/vue3';

/**
 * Teste une permission `<module>.<action>` dans la liste reçue du serveur.
 * Ne sert qu'à l'affichage : le serveur revérifie les droits à chaque requête.
 */
export function hasPermission(permissions: string[], name: string): boolean {
    return permissions.includes(name);
}

/** Le compte voit-il au moins un module métier (hors Administration) ? */
export function hasBusinessAccess(permissions: string[]): boolean {
    return permissions.some(
        (p) => p.endsWith('.view') && !p.startsWith('administration.'),
    );
}

/**
 * Permission d'une entrée de menu : vide = toujours visible, `metier` = visible
 * pour un compte qui a au moins un module métier (pas pour l'Administrateur).
 */
export function canSee(permissions: string[], needed: string): boolean {
    if (needed === '') {
        return true;
    }

    if (needed === 'metier') {
        return hasBusinessAccess(permissions);
    }

    return hasPermission(permissions, needed);
}

export function usePermissions() {
    const page = usePage();

    return {
        can: (name: string): boolean =>
            hasPermission(page.props.auth.permissions, name),
        see: (needed: string): boolean =>
            canSee(page.props.auth.permissions, needed),
    };
}
