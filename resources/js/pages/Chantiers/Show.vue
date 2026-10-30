<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Camera,
    ClipboardList,
    FileSpreadsheet,
    Pencil,
    PenLine,
    Plus,
    UserPlus,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import ChantierController from '@/actions/App/Http/Controllers/Chantiers/ChantierController';
import DailyReportController from '@/actions/App/Http/Controllers/Chantiers/DailyReportController';
import PlanningController from '@/actions/App/Http/Controllers/Chantiers/PlanningController';
import ProgressController from '@/actions/App/Http/Controllers/Chantiers/ProgressController';
import EmptyState from '@/components/erp/EmptyState.vue';
import FormDialog from '@/components/erp/FormDialog.vue';
import GanttChart from '@/components/erp/GanttChart.vue';
import KpiCard from '@/components/erp/KpiCard.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import ProgressBar from '@/components/erp/ProgressBar.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import TabsNav from '@/components/erp/TabsNav.vue';
import { Button } from '@/components/ui/button';
import { dateFr, fcfa, number, percent } from '@/lib/format';
import { consumptionTone } from '@/lib/status';
import { requestTotal, requests } from '@/mocks/achats';
import { orders } from '@/mocks/v2';
import type { Chantier, DailyReport, Task } from '@/types/erp';
import type { FieldDef, FieldValues } from '@/types/forms';

type Photo = { id: number; url: string; caption: string; date: string };
type Budget = {
    lines: number;
    revised: number;
    committed: number;
    actual: number;
    remaining: number;
    consumption: number;
};

const props = defineProps<{
    chantier: Chantier;
    tasks: Task[];
    progress: number;
    budget: Budget;
    photos: Photo[];
    reports: (DailyReport & { photos: number })[];
    canEdit: boolean;
    readOnly: boolean;
    members: { value: string; label: string }[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Chantiers', href: '/chantiers' }] },
});

const taskOpen = ref(false);
const memberOpen = ref(false);

const taskFields: FieldDef[] = [
    { name: 'name', label: 'Tâche', required: true, minLength: 3, wide: true },
    { name: 'start_date', label: 'Début', type: 'date', required: true },
    {
        name: 'end_date',
        label: 'Fin prévue',
        type: 'date',
        validate: (value, values) =>
            values.start_date &&
            value &&
            String(value) < String(values.start_date)
                ? 'La fin doit être postérieure au début.'
                : null,
        hint: 'Facultative pour un jalon.',
    },
    {
        name: 'weight',
        label: 'Pondération',
        type: 'number',
        required: true,
        min: 0,
        max: 100,
        step: 1,
        hint: 'Poids de la tâche dans l’avancement global (RG7.8).',
    },
    { name: 'is_milestone', label: 'Jalon (sans durée)', type: 'checkbox' },
];
const memberFields: FieldDef[] = [
    {
        name: 'user_id',
        label: 'Membre',
        type: 'select',
        required: true,
        options: props.members,
    },
    {
        name: 'role',
        label: 'Rôle sur le chantier',
        type: 'select',
        required: true,
        options: [
            { value: 'conducteur', label: 'Conducteur de travaux' },
            { value: 'chef_chantier', label: 'Chef de chantier' },
            { value: 'magasinier', label: 'Magasinier de chantier' },
        ],
    },
];

const onError = (errors: Record<string, string>) =>
    toast.error(Object.values(errors)[0]);

// Toutes les règles sont appliquées par le serveur ; ses refus reviennent en notification.
function addTask(v: FieldValues) {
    router.post(PlanningController.store.url(props.chantier.id), v as never, {
        preserveScroll: true,
        onError,
    });
}
function importPlanning(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (file) {
        router.post(
            PlanningController.import.url(props.chantier.id),
            { file },
            { preserveScroll: true, forceFormData: true, onError },
        );
    }

    input.value = '';
}
function addMember(v: FieldValues) {
    router.post(ChantierController.assign.url(props.chantier.id), v as never, {
        preserveScroll: true,
        onError,
    });
}

// Demandes et commandes : branchées avec le module Achats (M8).
const chantierRequests = computed(() =>
    requests.filter((r) => r.chantierId === props.chantier.id),
);
const chantierOrders = computed(() =>
    orders.filter((o) =>
        o.requestNumbers.some((n) =>
            chantierRequests.value.some((r) => r.number === n),
        ),
    ),
);

// L'état des tâches (retard, risque, non renseignée) est calculé par le serveur.
const problemTasks = computed(() =>
    props.tasks.filter((t) =>
        ['late', 'risk', 'stale'].includes(t.health ?? ''),
    ),
);
const lastPhotos = computed(() => props.photos.slice(0, 4));

const tab = ref('apercu');
const tabs = computed(() => [
    { key: 'apercu', label: 'Vue d’ensemble' },
    { key: 'planning', label: 'Planning', count: props.tasks.length },
    { key: 'avancement', label: 'Avancement' },
    {
        key: 'demandes',
        label: 'Demandes et commandes',
        count: chantierRequests.value.length,
    },
    { key: 'budget', label: 'Budget' },
    { key: 'photos', label: 'Photos', count: props.photos.length },
    { key: 'rapports', label: 'Rapports', count: props.reports.length },
]);
</script>

<template>
    <Head :title="chantier.name" />

    <div class="space-y-5 p-4">
        <PageHeader
            :title="chantier.name"
            :description="`${chantier.code} · ${chantier.projectName} · ${chantier.location}`"
        >
            <StatusBadge kind="site" :status="chantier.status" />
            <Button v-if="canEdit && !readOnly" variant="outline" as-child>
                <Link :href="ChantierController.edit.url(chantier.id)"
                    ><Pencil /> Modifier</Link
                >
            </Button>
            <Button v-if="canEdit && !readOnly" variant="outline" as-child>
                <Link :href="ProgressController.create.url(chantier.id)"
                    ><PenLine /> Saisir l’avancement</Link
                >
            </Button>
            <Button v-if="canEdit && !readOnly" variant="outline" as-child>
                <Link :href="DailyReportController.create.url(chantier.id)"
                    ><ClipboardList /> Rapport du jour</Link
                >
            </Button>
        </PageHeader>

        <TabsNav v-model="tab" :tabs="tabs" />

        <!-- Vue d'ensemble (CA7.2 : avancement, retards, budget, photos en un écran) -->
        <div v-if="tab === 'apercu'" class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <KpiCard
                    label="Avancement global"
                    :value="percent(progress)"
                    hint="Pondéré par tâche"
                    tone="info"
                />
                <KpiCard
                    label="Tâches en alerte"
                    :value="String(problemTasks.length)"
                    hint="Retard, risque ou non renseignée"
                    :tone="problemTasks.length ? 'warning' : 'success'"
                />
                <KpiCard
                    label="Budget engagé + réel"
                    :value="percent(budget.consumption, 1)"
                    :hint="`Reste à engager : ${fcfa(budget.remaining)}`"
                    :tone="
                        consumptionTone(budget.consumption) === 'success'
                            ? 'success'
                            : 'warning'
                    "
                />
                <KpiCard
                    label="Fin prévue"
                    :value="dateFr(chantier.endDate)"
                    :hint="`Début ${dateFr(chantier.startDate)}`"
                />
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <section class="rounded-xl border bg-card p-4 shadow-xs">
                    <h2 class="mb-3 font-display font-semibold">
                        Tâches en alerte
                    </h2>
                    <ul v-if="problemTasks.length" class="divide-y text-sm">
                        <li
                            v-for="t in problemTasks"
                            :key="t.id"
                            class="flex flex-wrap items-center justify-between gap-2 py-2.5"
                        >
                            <span>
                                <span class="font-medium">{{ t.name }}</span>
                                <span
                                    class="block text-xs text-muted-foreground"
                                >
                                    Réel {{ t.progress }} % · théorique
                                    {{ t.theoretical }} % · fin
                                    {{ dateFr(t.end) }}
                                </span>
                            </span>
                            <StatusBadge
                                kind="task"
                                :status="t.health ?? 'ok'"
                            />
                        </li>
                    </ul>
                    <EmptyState
                        v-else
                        compact
                        title="Aucune alerte de planning"
                    />
                </section>

                <section class="rounded-xl border bg-card p-4 shadow-xs">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h2 class="font-display font-semibold">Équipe</h2>
                        <Button
                            v-if="canEdit && !readOnly"
                            size="sm"
                            variant="outline"
                            @click="memberOpen = true"
                            ><UserPlus /> Affecter</Button
                        >
                    </div>
                    <ul class="divide-y text-sm">
                        <li
                            v-for="m in chantier.team"
                            :key="`${m.name}-${m.role}`"
                            class="flex justify-between py-2"
                        >
                            <span class="font-medium">{{ m.name }}</span>
                            <span class="text-muted-foreground">{{
                                m.role
                            }}</span>
                        </li>
                    </ul>
                </section>
            </div>

            <section class="rounded-xl border bg-card p-4 shadow-xs">
                <h2
                    class="mb-3 flex items-center gap-2 font-display font-semibold"
                >
                    <Camera class="size-4" /> Dernières photos
                </h2>
                <div
                    v-if="lastPhotos.length"
                    class="grid grid-cols-2 gap-3 sm:grid-cols-4"
                >
                    <figure
                        v-for="p in lastPhotos"
                        :key="p.id"
                        class="overflow-hidden rounded-lg border"
                    >
                        <img
                            :src="p.url"
                            :alt="p.caption"
                            loading="lazy"
                            class="aspect-video w-full object-cover"
                        />
                        <figcaption class="p-2 text-xs">
                            {{ p.caption }}
                        </figcaption>
                    </figure>
                </div>
                <EmptyState v-else compact title="Aucune photo" />
            </section>
        </div>

        <!-- Planning : Gantt maison + tableau -->
        <div v-else-if="tab === 'planning'" class="space-y-4">
            <div v-if="canEdit && !readOnly" class="flex flex-wrap gap-2">
                <Button variant="outline" @click="taskOpen = true"
                    ><Plus /> Ajouter une tâche</Button
                >
                <label
                    class="inline-flex h-9 cursor-pointer items-center gap-2 rounded-md border bg-background px-4 text-sm font-medium shadow-xs hover:bg-accent"
                    title="Colonnes : Tâche, Début, Fin prévue, Pondération, Jalon (oui/non). Première ligne = en-tête."
                >
                    <FileSpreadsheet class="size-4" /> Importer depuis Excel
                    <input
                        type="file"
                        accept=".xlsx,.xls,.csv"
                        class="sr-only"
                        @change="importPlanning"
                    />
                </label>
            </div>
            <GanttChart v-if="tasks.length" :tasks="tasks" />
            <EmptyState
                v-else
                title="Aucune tâche planifiée"
                description="Saisissez le planning ou importez-le depuis Excel."
            />
        </div>

        <!-- Avancement -->
        <div v-else-if="tab === 'avancement'" class="space-y-3">
            <div
                v-for="t in tasks.filter((x) => !x.milestone)"
                :key="t.id"
                class="rounded-xl border bg-card p-4 shadow-xs"
            >
                <div
                    class="mb-2 flex flex-wrap items-center justify-between gap-2"
                >
                    <div>
                        <p class="font-medium">{{ t.name }}</p>
                        <p class="text-xs text-muted-foreground">
                            Dernière mise à jour : {{ dateFr(t.lastUpdate) }}
                        </p>
                    </div>
                    <StatusBadge kind="task" :status="t.health ?? 'ok'" />
                </div>
                <ProgressBar
                    :value="t.progress"
                    :marker="t.theoretical"
                    tone="info"
                    label="Avancement réel (trait = théorique)"
                />
                <p v-if="t.comment" class="mt-2 text-sm text-muted-foreground">
                    « {{ t.comment }} »
                </p>
            </div>
        </div>

        <!-- Demandes -->
        <div v-else-if="tab === 'demandes'" class="space-y-2">
            <Link
                v-for="r in chantierRequests"
                :key="r.id"
                :href="`/achats/demandes/${r.id}`"
                class="flex flex-wrap items-center justify-between gap-2 rounded-xl border bg-card p-4 shadow-xs hover:bg-accent"
            >
                <span>
                    <span class="font-medium">{{ r.number }}</span>
                    <span class="block text-xs text-muted-foreground"
                        >{{ r.requester }} · {{ dateFr(r.createdAt) }}</span
                    >
                </span>
                <span class="flex items-center gap-3">
                    <span class="text-sm tabular-nums">{{
                        fcfa(requestTotal(r))
                    }}</span>
                    <StatusBadge kind="request" :status="r.status" />
                </span>
            </Link>
            <EmptyState
                v-if="!chantierRequests.length"
                title="Aucune demande d’achat"
            />
            <h3
                v-if="chantierOrders.length"
                class="pt-2 font-display font-semibold"
            >
                Bons de commande liés
            </h3>
            <Link
                v-for="o in chantierOrders"
                :key="o.id"
                :href="`/achats/commandes/${o.id}`"
                class="flex flex-wrap items-center justify-between gap-2 rounded-xl border bg-card p-4 shadow-xs hover:bg-accent"
            >
                <span>
                    <span class="font-medium">{{ o.number }}</span>
                    <span class="block text-xs text-muted-foreground"
                        >{{ o.supplierName }} · {{ dateFr(o.date) }}</span
                    >
                </span>
                <span class="flex items-center gap-3">
                    <span class="text-sm tabular-nums">{{
                        fcfa(o.amount)
                    }}</span>
                    <StatusBadge kind="order" :status="o.status" />
                </span>
            </Link>
        </div>

        <!-- Budget -->
        <div v-else-if="tab === 'budget'" class="space-y-3">
            <div
                v-if="budget.lines"
                class="rounded-xl border bg-card p-4 shadow-xs"
            >
                <ProgressBar
                    :value="budget.consumption"
                    :tone="consumptionTone(budget.consumption)"
                    :label="`Consommation du budget révisé : ${number(budget.consumption, 1)} %`"
                    :show-value="false"
                />
                <p class="mt-2 text-sm text-muted-foreground">
                    Révisé {{ fcfa(budget.revised) }} · engagé
                    {{ fcfa(budget.committed) }} · coût réel
                    {{ fcfa(budget.actual) }}
                </p>
                <Button class="mt-3" variant="outline" as-child>
                    <Link :href="`/budget/${chantier.id}`"
                        >Détail par poste</Link
                    >
                </Button>
            </div>
            <EmptyState v-else title="Budget non renseigné" />
        </div>

        <!-- Photos -->
        <div v-else-if="tab === 'photos'">
            <div
                v-if="photos.length"
                class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"
            >
                <figure
                    v-for="p in photos"
                    :key="p.id"
                    class="overflow-hidden rounded-lg border bg-card"
                >
                    <img
                        :src="p.url"
                        :alt="p.caption"
                        loading="lazy"
                        class="aspect-square w-full object-cover"
                    />
                    <figcaption class="p-2 text-xs">
                        {{ p.caption }} · {{ dateFr(p.date) }}
                    </figcaption>
                </figure>
            </div>
            <EmptyState
                v-else
                title="Aucune photo"
                description="Les photos sont jointes aux mises à jour d’avancement et aux rapports."
            />
        </div>

        <!-- Rapports -->
        <div v-else class="space-y-3">
            <article
                v-for="r in reports"
                :key="r.id"
                class="rounded-xl border bg-card p-4 shadow-xs"
            >
                <div
                    class="mb-1 flex flex-wrap items-center justify-between gap-2"
                >
                    <p class="font-medium">Rapport du {{ dateFr(r.date) }}</p>
                    <span class="text-xs text-muted-foreground"
                        >{{ r.author }} · {{ r.headcount }} personnes ·
                        {{ r.photos }} photos</span
                    >
                </div>
                <p class="text-sm">{{ r.works }}</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Incidents : {{ r.incidents }}
                </p>
            </article>
            <EmptyState
                v-if="!reports.length"
                title="Aucun rapport journalier"
            />
        </div>
        <FormDialog
            v-model:open="taskOpen"
            title="Ajouter une tâche"
            :fields="taskFields"
            @submit="addTask"
        />
        <FormDialog
            v-model:open="memberOpen"
            title="Affecter un membre"
            :fields="memberFields"
            @submit="addMember"
        />
    </div>
</template>
