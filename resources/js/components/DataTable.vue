<script setup lang="ts" generic="T extends RowData">
import { FlexRender, useTable } from '@tanstack/vue-table';
import type { RowData } from '@tanstack/vue-table';
import { computed } from 'vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dataTableFeatures } from '@/lib/dataTable';
import type { DataTableColumn } from '@/lib/dataTable';

/**
 * Tableau de données : pattern shadcn-vue + @tanstack/vue-table.
 * Défilement horizontal sur mobile (le conteneur de Table gère le débordement).
 */
const props = withDefaults(
    defineProps<{
        columns: DataTableColumn<T>[];
        data: T[];
        emptyMessage?: string;
    }>(),
    { emptyMessage: 'Aucun résultat.' },
);

const rows = computed(() => props.data);

const table = useTable({
    features: dataTableFeatures,
    columns: props.columns,
    data: rows,
});
</script>

<template>
    <div class="rounded-md border">
        <Table>
            <TableHeader>
                <TableRow
                    v-for="group in table.getHeaderGroups()"
                    :key="group.id"
                    class="hover:bg-transparent"
                >
                    <TableHead v-for="header in group.headers" :key="header.id">
                        <FlexRender
                            v-if="!header.isPlaceholder"
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
                    <TableCell
                        :colspan="columns.length"
                        class="h-24 text-center text-muted-foreground"
                    >
                        {{ emptyMessage }}
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
