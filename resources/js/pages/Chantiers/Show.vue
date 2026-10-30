<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
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
import FormDialog from '@/components/erp/FormDialog.vue';
import GanttChart from '@/components/erp/GanttChart.vue';
import KpiCard from '@/components/erp/KpiCard.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import ProgressBar from '@/components/erp/ProgressBar.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import TabsNav from '@/components/erp/TabsNav.vue';
import EmptyState from '@/components/erp/EmptyState.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { dateFr, fcfa, number, percent } from '@/lib/format';
import {
    globalProgress,
    taskHealth,
    theoreticalProgress,
} from '@/lib/planning';
import { consumptionTone } from '@/lib/status';
import {
    budgetLevel,
    budgetOf,
    budgetTotals,
    requestTotal,
    requests,
} from '@/mocks/achats';
import { chantiers, dailyReports, tasksOf } from '@/mocks/chantiers';
import { orders } from '@/mocks/v2';
import { toast } from 'vue-sonner';
import type { Task } from '@/types/erp';
import type { FieldDef, FieldValues } from '@/types/forms';

const props = defineProps<{ id: number }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Chantiers', href: '/chantiers' }] },
});

const { can } = usePermissions();
const chantier = computed(
    () => chantiers.find((c) => c.id === props.id) ?? chantiers[0],
);
// Tâches ajoutées pendant la session (maquette) en plus des tâches du planning.
const extraTasks = ref<Task[]>([]);
const extraTeam = ref<{ name: string; role: string }[]>([]);
const tasks = computed(() => [
    ...tasksOf(chantier.value.id),
    ...extraTasks.value.filter((t) => t.chantierId === chantier.value.id),
]);
const readOnly = computed(() => chantier.value.status === 'cloture');
const taskOpen = ref(false);
const importOpen = ref(false);
const memberOpen = ref(false);

const taskFields: FieldDef[] = [
    { name: 'name', label: 'Tâche', required: true, minLength: 3, wide: true },
    { name: 'start', label: 'Début', type: 'date', required: true },
    {
        name: 'end',
        label: 'Fin prévue',
        type: 'date',
        required: true,
        validate: (value, values) =>
            values.start && value && String(value) < String(values.start)
                ? 'La fin doit être postérieure au début.'
                : null,
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
    { name: 'milestone', label: 'Jalon (sans durée)', type: 'checkbox' },
];
const importFields: FieldDef[] = [
    {
        name: 'file',
        label: 'Fichier Excel (.xlsx)',
        type: 'file',
        required: true,
        hint: 'Colonnes attendues : Tâche, Début, Fin prévue, Pondération. 20 Mo maximum.',
        wide: true,
    },
];
const memberFields: FieldDef[] = [
    { name: 'name', label: 'Nom', required: true, minLength: 3 },
    {
        name: 'role',
        label: 'Rôle',
        type: 'select',
        required: true,
        options: [
            'Chef d’équipe',
            'Topographe',
            'Magasinier de chantier',
            'Technicien',
        ].map((r) => ({ value: r, label: r })),
    },
];

function addTask(v: FieldValues) {
    extraTasks.value.push({
        id: Date.now(),
        chantierId: chantier.value.id,
        name: String(v.name),
        start: String(v.start),
        end: String(v.milestone ? v.start : v.end),
        progress: 0,
        weight: Number(v.weight),
        milestone: Boolean(v.milestone),
        lastUpdate: '2026-10-29',
    });
    toast.success('Tâche ajoutée au planning (maquette).');
}
function importPlanning(v: FieldValues) {
    toast.success(
        `Planning importé depuis « ${String(v.file)} » : 6 tâches créées (maquette).`,
    );
}
function addMember(v: FieldValues) {
    extraTeam.value.push({ name: String(v.name), role: String(v.role) });
    toast.success('Membre affecté au chantier (maquette).');
}
const lines = computed(() => budgetOf(chantier.value.id));
const totals = computed(() => budgetTotals(lines.value));
const chantierRequests = computed(() =>
    requests.filter((r) => r.chantierId === chantier.value.id),
);
const chantierOrders = computed(() =>
    orders.filter((o) =>
        o.requestNumbers.some((n) =>
            chantierRequests.value.some((r) => r.number === n),
        ),
    ),
);
const reports = computed(() =>
    dailyReports.filter((r) => r.chantierId === chantier.value.id),
);
const progress = computed(() => globalProgress(tasks.value));
const problemTasks = computed(() =>
    tasks.value.filter((t) =>
        ['late', 'risk', 'stale'].includes(taskHealth(t)),
    ),
);

const tab = ref('apercu');
const tabs = computed(() => [
    { key: 'apercu', label: 'Vue d’ensemble' },
    { key: 'planning', label: 'Planning', count: tasks.value.length },
    { key: 'avancement', label: 'Avancement' },
    {
        key: 'demandes',
        label: 'Demandes et commandes',
        count: chantierRequests.value.length,
    },
    { key: 'budget', label: 'Budget' },
    { key: 'photos', label: 'Photos' },
    { key: 'rapports', label: 'Rapports', count: reports.value.length },
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
            <Button
                v-if="can('chantiers.update') && !readOnly"
                variant="outline"
                as-child
            >
                <Link :href="`/chantiers/${chantier.id}/modifier`"
                    ><Pencil /> Modifier</Link
                >
            </Button>
            <Button v-if="can('chantiers.update')" variant="outline" as-child>
                <Link :href="`/chantiers/${chantier.id}/avancement`"
                    ><PenLine /> Saisir l’avancement</Link
                >
            </Button>
            <Button v-if="can('chantiers.update')" variant="outline" as-child>
                <Link :href="`/chantiers/${chantier.id}/rapport`"
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
                    :value="percent(totals.consumption, 1)"
                    :hint="`Reste à engager : ${fcfa(totals.remaining)}`"
                    :tone="
                        budgetLevel(totals.consumption) === 'ok'
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
                                    {{ theoreticalProgress(t) }} % · fin
                                    {{ dateFr(t.end) }}
                                </span>
                            </span>
                            <StatusBadge kind="task" :status="taskHealth(t)" />
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
                            v-if="can('chantiers.update') && !readOnly"
                            size="sm"
                            variant="outline"
                            @click="memberOpen = true"
                            ><UserPlus /> Affecter</Button
                        >
                    </div>
                    <ul class="divide-y text-sm">
                        <li class="flex justify-between py-2">
                            <span class="font-medium">{{
                                chantier.manager
                            }}</span>
                            <span class="text-muted-foreground"
                                >Conducteur de travaux</span
                            >
                        </li>
                        <li
                            v-for="m in [...chantier.team, ...extraTeam]"
                            :key="m.name"
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
                    v-if="chantier.lastPhoto.length"
                    class="grid grid-cols-2 gap-3 sm:grid-cols-4"
                >
                    <figure
                        v-for="(p, i) in chantier.lastPhoto"
                        :key="p"
                        class="overflow-hidden rounded-lg border"
                    >
                        <div
                            class="flex aspect-video items-center justify-center bg-muted text-xs text-muted-foreground"
                            :class="i % 2 ? 'bg-secondary' : ''"
                        >
                            Photo
                        </div>
                        <figcaption class="p-2 text-xs">{{ p }}</figcaption>
                    </figure>
                </div>
                <EmptyState v-else compact title="Aucune photo" />
            </section>
        </div>

        <!-- Planning : Gantt maison + tableau -->
        <div v-else-if="tab === 'planning'" class="space-y-4">
            <div
                v-if="can('chantiers.update') && !readOnly"
                class="flex flex-wrap gap-2"
            >
                <Button variant="outline" @click="taskOpen = true"
                    ><Plus /> Ajouter une tâche</Button
                >
                <Button variant="outline" @click="importOpen = true"
                    ><FileSpreadsheet /> Importer depuis Excel</Button
                >
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
                    <StatusBadge kind="task" :status="taskHealth(t)" />
                </div>
                <ProgressBar
                    :value="t.progress"
                    :marker="theoreticalProgress(t)"
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
                v-if="lines.length"
                class="rounded-xl border bg-card p-4 shadow-xs"
            >
                <ProgressBar
                    :value="totals.consumption"
                    :tone="consumptionTone(totals.consumption)"
                    :label="`Consommation du budget révisé : ${number(totals.consumption, 1)} %`"
                    :show-value="false"
                />
                <p class="mt-2 text-sm text-muted-foreground">
                    Révisé {{ fcfa(totals.revised) }} · engagé
                    {{ fcfa(totals.committed) }} · coût réel
                    {{ fcfa(totals.actual) }}
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
                v-if="chantier.lastPhoto.length"
                class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"
            >
                <figure
                    v-for="p in chantier.lastPhoto"
                    :key="p"
                    class="overflow-hidden rounded-lg border bg-card"
                >
                    <div
                        class="flex aspect-square items-center justify-center bg-muted text-xs text-muted-foreground"
                    >
                        Photo
                    </div>
                    <figcaption class="p-2 text-xs">{{ p }}</figcaption>
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
            v-model:open="importOpen"
            title="Importer le planning depuis Excel"
            description="RG7.3 : le planning peut être saisi dans l’application ou importé."
            :fields="importFields"
            submit-label="Importer"
            @submit="importPlanning"
        />
        <FormDialog
            v-model:open="memberOpen"
            title="Affecter un membre"
            :fields="memberFields"
            @submit="addMember"
        />
    </div>
</template>
