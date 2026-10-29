<script setup lang="ts" generic="T extends RowData">
import { FlexRender, useTable } from '@tanstack/vue-table';
import type { RowData } from '@tanstack/vue-table';
import { ArrowDown, ArrowUp, ChevronsUpDown, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/erp/EmptyState.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dataTableFeatures } from '@/lib/dataTable';
import type { DataTableColumn, DataTableFilter } from '@/lib/dataTable';

/**
 * Tableau de données : pattern shadcn-vue + @tanstack/vue-table (v9).
 * Tri par clic sur l'en-tête, recherche globale, filtres déroulants,
 * pagination. Défilement horizontal sur mobile.
 */
const props = withDefaults(
    defineProps<{
        columns: DataTableColumn<T>[];
        data: T[];
        emptyMessage?: string;
        searchable?: boolean;
        searchPlaceholder?: string;
        filters?: DataTableFilter[];
        /** false = liste déjà paginée par le serveur (pas de pagination ici). */
        paginate?: boolean;
        pageSize?: number;
        clickable?: boolean;
    }>(),
    {
        emptyMessage: 'Aucun résultat.',
        searchable: true,
        searchPlaceholder: 'Rechercher…',
        filters: () => [],
        paginate: true,
        pageSize: 10,
        clickable: false,
    },
);

const emit = defineEmits<{ 'row-click': [row: T] }>();

const rows = computed(() => props.data);

const table = useTable({
    features: dataTableFeatures,
    columns: props.columns,
    data: rows,
    initialState: {
        pagination: {
            pageIndex: 0,
            pageSize: props.paginate ? props.pageSize : 100_000,
        },
    },
});

const search = ref('');
const filterValues = ref<Record<string, string>>({});

function onSearch(value: string | number) {
    search.value = String(value);
    table.setGlobalFilter(search.value || undefined);
}

function onFilter(columnId: string, value: string) {
    filterValues.value = { ...filterValues.value, [columnId]: value };
    table.getColumn(columnId)?.setFilterValue(value || undefined);
}

const pageInfo = computed(() => {
    const { pageIndex, pageSize } = table.atoms.pagination.get();
    const total = table.getFilteredRowModel().rows.length;
    const from = total === 0 ? 0 : pageIndex * pageSize + 1;
    const to = props.paginate
        ? Math.min(total, (pageIndex + 1) * pageSize)
        : total;

    return { from, to, total };
});

const selectClass =
    'h-9 rounded-md border border-input bg-card px-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/40';
</script>

<template>
    <div class="space-y-3">
        <div
            v-if="searchable || filters.length"
            class="flex flex-wrap items-center gap-2"
        >
            <div v-if="searchable" class="relative w-full sm:max-w-xs">
                <Search
                    class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    :model-value="search"
                    type="search"
                    :placeholder="searchPlaceholder"
                    class="bg-card pl-8"
                    @update:model-value="onSearch"
                />
            </div>
            <label
                v-for="filter in filters"
                :key="filter.columnId"
                class="flex items-center gap-1.5 text-sm text-muted-foreground"
            >
                {{ filter.label }}
                <select
                    :class="selectClass"
                    :value="filterValues[filter.columnId] ?? ''"
                    @change="
                        onFilter(
                            filter.columnId,
                            ($event.target as HTMLSelectElement).value,
                        )
                    "
                >
                    <option value="">Tous</option>
                    <option
                        v-for="option in filter.options"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </label>
        </div>

        <div class="overflow-hidden rounded-xl border bg-card shadow-xs">
            <Table>
                <TableHeader>
                    <TableRow
                        v-for="group in table.getHeaderGroups()"
                        :key="group.id"
                        class="bg-muted/50 hover:bg-muted/50"
                    >
                        <TableHead
                            v-for="header in group.headers"
                            :key="header.id"
                        >
                            <button
                                v-if="
                                    !header.isPlaceholder &&
                                    header.column.getCanSort()
                                "
                                type="button"
                                class="-ml-1 inline-flex items-center gap-1 rounded px-1 py-0.5 hover:text-foreground"
                                @click="header.column.toggleSorting()"
                            >
                                <FlexRender :header="header" />
                                <ArrowUp
                                    v-if="header.column.getIsSorted() === 'asc'"
                                    class="size-3.5"
                                />
                                <ArrowDown
                                    v-else-if="
                                        header.column.getIsSorted() === 'desc'
                                    "
                                    class="size-3.5"
                                />
                                <ChevronsUpDown
                                    v-else
                                    class="size-3.5 opacity-40"
                                />
                            </button>
                            <FlexRender
                                v-else-if="!header.isPlaceholder"
                                :header="header"
                            />
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="table.getRowModel().rows.length">
                        <TableRow
                            v-for="row in table.getRowModel().rows"
                            :key="row.id"
                            :class="clickable ? 'cursor-pointer' : ''"
                            @click="
                                clickable && emit('row-click', row.original)
                            "
                        >
                            <TableCell
                                v-for="cell in row.getAllCells()"
                                :key="cell.id"
                            >
                                <FlexRender :cell="cell" />
                            </TableCell>
                        </TableRow>
                    </template>
                    <TableRow v-else class="hover:bg-transparent">
                        <TableCell :colspan="columns.length">
                            <EmptyState :title="emptyMessage" compact />
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <div
            v-if="paginate"
            class="flex flex-col items-center justify-between gap-2 text-sm text-muted-foreground sm:flex-row"
        >
            <p>
                <template v-if="pageInfo.total > 0">
                    {{ pageInfo.from }}–{{ pageInfo.to }} sur
                    {{ pageInfo.total }}
                </template>
                <template v-else>0 résultat</template>
            </p>
            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!table.getCanPreviousPage()"
                    @click="table.previousPage()"
                >
                    Précédent
                </Button>
                <span v-if="table.getPageCount() > 1">
                    Page {{ table.atoms.pagination.get().pageIndex + 1 }} /
                    {{ table.getPageCount() }}
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!table.getCanNextPage()"
                    @click="table.nextPage()"
                >
                    Suivant
                </Button>
            </div>
        </div>
    </div>
</template>
