import { describe, expect, it } from 'vitest';
import { hasPermission } from '@/composables/usePermissions';

describe('hasPermission', () => {
    const permissions = ['administration.view', 'achats.validate'];

    it('accepte une permission accordée', () => {
        expect(hasPermission(permissions, 'achats.validate')).toBe(true);
    });

    it('refuse une permission absente', () => {
        expect(hasPermission(permissions, 'finance.view')).toBe(false);
    });

    it('ne confond pas deux actions du même module', () => {
        expect(hasPermission(permissions, 'administration.update')).toBe(false);
    });
});
