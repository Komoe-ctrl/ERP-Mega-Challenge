import {
    Settings,
    Users,
    Truck,
    FolderKanban,
    FileText,
    Handshake,
    HardHat,
    ShoppingCart,
    Warehouse,
    Wallet,
    Receipt,
    Hammer,
    IdCard,
    Tractor,
    Landmark,
    LayoutGrid,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';

/** Icônes des modules, indexées par le nom envoyé par le serveur (enum Module::icon()). */
const icons: Record<string, LucideIcon> = {
    Settings,
    Users,
    Truck,
    FolderKanban,
    FileText,
    Handshake,
    HardHat,
    ShoppingCart,
    Warehouse,
    Wallet,
    Receipt,
    Hammer,
    IdCard,
    Tractor,
    Landmark,
};

export function moduleIcon(name: string): LucideIcon {
    return icons[name] ?? LayoutGrid;
}
