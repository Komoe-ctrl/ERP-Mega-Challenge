import { usePage } from '@inertiajs/vue3';

/**
 * Teste une permission `<module>.<action>` dans la liste reçue du serveur.
 * Ne sert qu'à l'affichage : le serveur revérifie les droits à chaque requête.
 */
export function hasPermission(permissions: string[], name: string): boolean {
    return permissions.includes(name);
}

export function usePermissions() {
    const page = usePage();

    return {
        can: (name: string): boolean =>
            hasPermission(page.props.auth.permissions, name),
    };
}
