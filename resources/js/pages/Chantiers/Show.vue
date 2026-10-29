<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Camera, ClipboardList, PenLine } from '@lucide/vue';
import { computed, ref } from 'vue';
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

const props = defineProps<{ id: number }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Chantiers', href: '/chantiers' }] },
});

const { can } = usePermissions();
const chantier = computed(
    () => chantiers.find((c) => c.id === props.id) ?? chantiers[0],
);
const tasks = computed(() => tasksOf(chantier.value.id));
const lines = computed(() => budgetOf(chantier.value.id));
const totals = computed(() => budgetTotals(lines.value));
const chantierRequests = computed(() =>
    requests.filter((r) => r.chantierId === chantier.value.id),
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
        label: 'Demandes',
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
                    <h2 class="mb-3 font-display font-semibold">Équipe</h2>
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
                            v-for="m in chantier.team"
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
    </div>
</template>
