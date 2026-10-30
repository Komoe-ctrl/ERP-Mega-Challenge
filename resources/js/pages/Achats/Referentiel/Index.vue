<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { FileSpreadsheet, Plus } from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import ArticleController from '@/actions/App/Http/Controllers/Achats/ArticleController';
import MissingArticleReportController from '@/actions/App/Http/Controllers/Achats/MissingArticleReportController';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import SubNav from '@/components/erp/SubNav.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { amountCell, linkCell, toneBadgeCell } from '@/lib/cells';
import type { DataTableColumn } from '@/lib/dataTable';
import { dateFr, fcfa } from '@/lib/format';
import { achatsNav } from '@/lib/navs';
import type { Paginated } from '@/types';
import type { Article, MissingArticleReport } from '@/types/erp';

type Filters = { q: string; category: string; state: string };

const props = defineProps<{
    articles: Paginated<Article>;
    filters: Filters;
    categories: string[];
    canWrite: boolean;
    missingReports: MissingArticleReport[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Achats', href: '/achats/demandes' },
            { title: 'Référentiel des prix', href: '/achats/referentiel' },
        ],
    },
});

const search = ref(props.filters.q);

function apply(changes: Partial<Filters>) {
    const next = { ...props.filters, ...changes };

    router.get(
        ArticleController.index.url(),
        Object.fromEntries(Object.entries(next).filter(([, v]) => v !== '')),
        { preserveState: true, replace: true },
    );
}

// RG8.16 : chargement initial par Excel ou CSV ; le serveur refuse tout si une ligne est invalide.
function importArticles(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (file) {
        router.post(
            ArticleController.import.url(),
            { file },
            {
                forceFormData: true,
                preserveScroll: true,
                onError: (errors) => toast.error(Object.values(errors)[0]),
            },
        );
    }

    input.value = '';
}

function resolve(id: number) {
    router.patch(
        MissingArticleReportController.resolve.url(id),
        {},
        { preserveScroll: true },
    );
}

const columns: DataTableColumn<Article>[] = [
    {
        accessorKey: 'label',
        header: 'Article',
        cell: ({ row }) =>
            linkCell(
                ArticleController.edit.url(row.original.id),
                row.original.label,
                row.original.reference,
            ),
    },
    { accessorKey: 'category', header: 'Catégorie' },
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
        cell: ({ row }) =>
            toneBadgeCell(
                row.original.active ? 'Actif' : 'Désactivé',
                row.original.active ? 'success' : 'neutral',
            ),
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
            <label
                v-if="canWrite"
                class="inline-flex h-9 cursor-pointer items-center gap-2 rounded-md border bg-background px-4 text-sm font-medium shadow-xs hover:bg-accent"
                title="Colonnes : référence, libellé, catégorie, unité, prix unitaire (FCFA). Première ligne = en-tête."
            >
                <FileSpreadsheet class="size-4" /> Importer Excel / CSV
                <input
                    type="file"
                    accept=".xlsx,.xls,.csv"
                    class="sr-only"
                    @change="importArticles"
                />
            </label>
            <Button v-if="canWrite" as-child>
                <Link :href="ArticleController.create.url()"
                    ><Plus /> Nouvel article</Link
                >
            </Button>
        </PageHeader>
        <SubNav :links="achatsNav" />

        <p
            v-if="!canWrite"
            class="rounded-lg bg-muted p-3 text-sm text-muted-foreground"
        >
            Consultation seule : seul le Service Achats BTP peut créer ou
            modifier un article ou un prix.
        </p>

        <div class="flex flex-wrap gap-3">
            <form
                class="flex-1 sm:max-w-xs"
                @submit.prevent="apply({ q: search })"
            >
                <Input
                    v-model="search"
                    type="search"
                    placeholder="Rechercher un article, une référence…"
                    aria-label="Rechercher un article"
                />
            </form>
            <select
                class="h-9 rounded-md border border-input bg-background px-2 text-sm"
                aria-label="Filtrer par catégorie"
                :value="filters.category"
                @change="
                    apply({
                        category: ($event.target as HTMLSelectElement).value,
                    })
                "
            >
                <option value="">Catégorie : toutes</option>
                <option v-for="c in categories" :key="c" :value="c">
                    {{ c }}
                </option>
            </select>
            <select
                class="h-9 rounded-md border border-input bg-background px-2 text-sm"
                aria-label="Filtrer par état"
                :value="filters.state"
                @change="
                    apply({ state: ($event.target as HTMLSelectElement).value })
                "
            >
                <option value="">État : tous</option>
                <option value="actif">Actifs</option>
                <option value="inactif">Désactivés</option>
            </select>
        </div>

        <DataTable
            :columns="columns"
            :data="articles.data"
            :searchable="false"
            :paginate="false"
            empty-message="Aucun article ne correspond à ces filtres."
        />
        <PaginationBar :page="articles" />

        <section
            v-if="canWrite"
            class="rounded-xl border bg-card p-4 shadow-xs"
        >
            <h2 class="mb-3 font-display font-semibold">
                Demandes d’ajout d’article ({{ missingReports.length }})
            </h2>
            <ul v-if="missingReports.length" class="divide-y text-sm">
                <li
                    v-for="m in missingReports"
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
                    <Button size="sm" variant="outline" @click="resolve(m.id)"
                        >Marquer comme traitée</Button
                    >
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">
                Aucune demande en attente.
            </p>
        </section>
    </div>
</template>
