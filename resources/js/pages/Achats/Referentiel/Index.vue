<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed } from 'vue';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import SubNav from '@/components/erp/SubNav.vue';
import { achatsNav } from '@/lib/navs';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { amountCell, linkCell, toneBadgeCell } from '@/lib/cells';
import type { DataTableColumn, DataTableFilter } from '@/lib/dataTable';
import { dateFr, fcfa } from '@/lib/format';
import { articles, categories, missingArticleReports } from '@/mocks/achats';
import type { Article } from '@/types/erp';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Achats', href: '/achats/demandes' },
            { title: 'Référentiel des prix', href: '/achats/referentiel' },
        ],
    },
});

const page = usePage();
// RG8.13 : seul le Service Achats BTP crée, modifie ou désactive un article.
const isPurchasing = computed(() =>
    page.props.auth.roles.includes('service_achats_btp'),
);

const columns: DataTableColumn<Article>[] = [
    {
        accessorKey: 'label',
        header: 'Article',
        cell: ({ row }) =>
            linkCell(
                `/achats/referentiel/${row.original.id}`,
                row.original.label,
                row.original.reference,
            ),
    },
    { accessorKey: 'category', header: 'Catégorie', filterFn: 'equalsString' },
    { accessorKey: 'unit', header: 'Unité' },
    {
        accessorKey: 'price',
        header: 'Prix unitaire',
        cell: ({ row }) => amountCell(fcfa(row.original.price)),
    },
    {
        accessorKey: 'updatedAt',
        header: 'Mis à jour',
        cell: ({ row }) => dateFr(row.original.updatedAt),
    },
    {
        id: 'active',
        header: 'État',
        accessorFn: (a) => (a.active ? 'actif' : 'inactif'),
        filterFn: 'equalsString',
        cell: ({ row }) =>
            toneBadgeCell(
                row.original.active ? 'Actif' : 'Désactivé',
                row.original.active ? 'success' : 'neutral',
            ),
    },
];

const filters: DataTableFilter[] = [
    {
        columnId: 'category',
        label: 'Catégorie',
        options: categories.map((c) => ({ value: c, label: c })),
    },
    {
        columnId: 'active',
        label: 'État',
        options: [
            { value: 'actif', label: 'Actifs' },
            { value: 'inactif', label: 'Désactivés' },
        ],
    },
];
</script>

<template>
    <Head title="Référentiel des prix" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Référentiel des prix"
            description="Prix de référence interne : le demandeur ne saisit jamais de prix."
        >
            <Button v-if="isPurchasing" as-child>
                <Link href="/achats/referentiel/creer"
                    ><Plus /> <SubNav :links="achatsNav" /> Nouvel article</Link
                >
            </Button>
        </PageHeader>

        <p
            v-if="!isPurchasing"
            class="rounded-lg bg-muted p-3 text-sm text-muted-foreground"
        >
            Consultation seule : seul le Service Achats BTP peut créer ou
            modifier un article ou un prix.
        </p>

        <DataTable
            :columns="columns"
            :data="articles"
            :filters="filters"
            search-placeholder="Rechercher un article, une référence…"
        />

        <section
            v-if="isPurchasing"
            class="rounded-xl border bg-card p-4 shadow-xs"
        >
            <h2 class="mb-3 font-display font-semibold">
                Demandes d’ajout d’article ({{ missingArticleReports.length }})
            </h2>
            <ul class="divide-y text-sm">
                <li
                    v-for="m in missingArticleReports"
                    :key="m.id"
                    class="flex flex-wrap items-center justify-between gap-2 py-2.5"
                >
                    <span>
                        <span class="font-medium">{{ m.label }}</span>
                        <span class="block text-xs text-muted-foreground"
                            >{{ m.requester }} · {{ m.chantier }} ·
                            {{ dateFr(m.date) }}</span
                        >
                    </span>
                    <StatusBadge label="À traiter" tone="warning" />
                </li>
            </ul>
        </section>
    </div>
</template>
