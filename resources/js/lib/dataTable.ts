import {
    columnFilteringFeature,
    createFilteredRowModel,
    createPaginatedRowModel,
    createSortedRowModel,
    filterFn_equalsString,
    filterFn_includesString,
    globalFilteringFeature,
    rowPaginationFeature,
    rowSortingFeature,
    sortFn_alphanumeric,
    sortFn_basic,
    sortFn_text,
    tableFeatures,
} from '@tanstack/vue-table';
import type { ColumnDef, RowData } from '@tanstack/vue-table';

/**
 * Fonctions activées pour les tableaux de l'ERP : tri, filtres par colonne,
 * recherche globale et pagination, traités côté navigateur (données déjà
 * chargées). Pour une liste paginée côté serveur, utiliser la prop
 * `paginate=false` du composant DataTable.
 */
export const dataTableFeatures = tableFeatures({
    columnFilteringFeature,
    globalFilteringFeature,
    filteredRowModel: createFilteredRowModel(),
    filterFns: {
        includesString: filterFn_includesString,
        equalsString: filterFn_equalsString,
    },
    rowSortingFeature,
    sortedRowModel: createSortedRowModel(),
    sortFns: {
        alphanumeric: sortFn_alphanumeric,
        basic: sortFn_basic,
        text: sortFn_text,
    },
    rowPaginationFeature,
    paginatedRowModel: createPaginatedRowModel(),
});

export type DataTableFeatures = typeof dataTableFeatures;

export type DataTableColumn<T extends RowData> = ColumnDef<
    DataTableFeatures,
    T
>;

/** Filtre déroulant sur une colonne (ex. statut). */
export type DataTableFilter = {
    columnId: string;
    label: string;
    options: { value: string; label: string }[];
};
