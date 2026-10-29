import { LayoutGrid } from '@lucide/vue';
import { describe, expect, it } from 'vitest';
import { moduleIcon } from '@/lib/moduleIcons';

// Noms d'icônes renvoyés par App\Domain\Shared\Enums\Module::icon().
const serverIcons = [
    'Settings',
    'Users',
    'Truck',
    'FolderKanban',
    'FileText',
    'Handshake',
    'HardHat',
    'ShoppingCart',
    'Warehouse',
    'Wallet',
    'Receipt',
    'Hammer',
    'IdCard',
    'Tractor',
    'Landmark',
];

describe('moduleIcon', () => {
    it.each(serverIcons)('connaît l’icône %s', (name) => {
        expect(moduleIcon(name)).not.toBe(LayoutGrid);
    });

    it('retombe sur une icône par défaut pour un nom inconnu', () => {
        expect(moduleIcon('Inconnue')).toBe(LayoutGrid);
    });
});
