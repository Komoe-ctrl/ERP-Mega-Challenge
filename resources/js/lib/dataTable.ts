import { tableFeatures } from '@tanstack/vue-table';
import type { ColumnDef, RowData } from '@tanstack/vue-table';

/**
 * Fonctions activées pour les tableaux de l'ERP. Le tri, la pagination et les
 * filtres sont faits côté serveur (listes paginées, exigence de performance) :
 * le tableau ne gère donc que l'affichage.
 */
export const dataTableFeatures = tableFeatures({});

export type DataTableFeatures = typeof dataTableFeatures;

export type DataTableColumn<T extends RowData> = ColumnDef<
    DataTableFeatures,
    T
>;
