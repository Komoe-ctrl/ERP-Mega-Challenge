<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Bell,
    ClipboardCheck,
    FolderKanban,
    HardHat,
    Wallet,
} from '@lucide/vue';
import { computed } from 'vue';
import BarChart from '@/components/charts/BarChart.vue';
import KpiCard from '@/components/erp/KpiCard.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { usePermissions } from '@/composables/usePermissions';
import { fcfaShort, number, percent } from '@/lib/format';
import { taskHealth } from '@/lib/planning';
import { dashboard } from '@/routes';
import {
    budgetLevel,
    budgetOf,
    budgetTotals,
    requestTotal,
    requests,
} from '@/mocks/achats';
import { chantiers, projects, tasks } from '@/mocks/chantiers';
import { notifications } from '@/mocks/transverse';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Tableau de bord', href: dashboard() }],
    },
});

const page = usePage();
const { can } = usePermissions();
const user = computed(() => page.props.auth.user);

const activeChantiers = chantiers.filter((c) => c.status === 'en_cours');
const toValidate = requests.filter((r) => r.status === 'en_attente');

const budgetByChantier = chantiers
    .filter((c) => budgetOf(c.id).length)
    .map((c) => ({ chantier: c, totals: budgetTotals(budgetOf(c.id)) }));

const globalBudget = budgetTotals(
    budgetByChantier.flatMap((b) => budgetOf(b.chantier.id)),
);

const taskAlerts = tasks
    .map((t) => ({ task: t, health: taskHealth(t) }))
    .filter((t) => ['late', 'risk', 'stale'].includes(t.health));

const budgetAlerts = budgetByChantier.filter(
    (b) => budgetLevel(b.totals.consumption) !== 'ok',
);

const chartLabels = budgetByChantier.map((b) => b.chantier.code);
const chartSets = [
    {
        label: 'Budget révisé (M FCFA)',
        data: budgetByChantier.map((b) =>
            Math.round(b.totals.revised / 1_000_000),
        ),
    },
    {
        label: 'Engagé + réel (M FCFA)',
        data: budgetByChantier.map((b) =>
            Math.round((b.totals.committed + b.totals.actual) / 1_000_000),
        ),
    },
];
</script>

<template>
    <Head title="Tableau de bord" />

    <div class="space-y-6 p-4">
        <PageHeader
            :title="`Bonjour, ${user.name}`"
            description="Vue d’ensemble de l’activité au 29/10/2026."
        />

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <KpiCard
                label="Projets en cours"
                :value="
                    String(
                        projects.filter((p) => p.status === 'en_cours').length,
                    )
                "
                :hint="`${projects.length} projets au total`"
                :icon="FolderKanban"
                tone="info"
                href="/projets"
            />
            <KpiCard
                label="Chantiers actifs"
                :value="String(activeChantiers.length)"
                :hint="`${chantiers.filter((c) => c.status === 'suspendu').length} suspendu`"
                :icon="HardHat"
                tone="success"
                href="/chantiers"
            />
            <KpiCard
                label="Demandes à valider"
                :value="String(toValidate.length)"
                :hint="`${fcfaShort(toValidate.reduce((s, r) => s + requestTotal(r), 0))} à engager`"
                :icon="ClipboardCheck"
                tone="warning"
                href="/achats/a-valider"
            />
            <KpiCard
                label="Budget consommé"
                :value="percent(globalBudget.consumption, 1)"
                :hint="`${fcfaShort(globalBudget.remaining)} restant à engager`"
                :icon="Wallet"
                :tone="
                    budgetLevel(globalBudget.consumption) === 'ok'
                        ? 'success'
                        : 'warning'
                "
                href="/budget"
            />
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <section
                class="rounded-xl border bg-card p-4 shadow-xs lg:col-span-2"
            >
                <h2 class="mb-3 font-display font-semibold">
                    Budget par chantier
                </h2>
                <BarChart :labels="chartLabels" :datasets="chartSets" />
            </section>

            <section class="rounded-xl border bg-card p-4 shadow-xs">
                <div class="mb-3 flex items-center justify-between">
                    <h2
                        class="flex items-center gap-2 font-display font-semibold"
                    >
                        <Bell class="size-4" /> Notifications
                    </h2>
                    <Link
                        href="/notifications"
                        class="text-xs text-primary underline-offset-4 hover:underline"
                    >
                        Tout voir
                    </Link>
                </div>
                <ul class="space-y-3">
                    <li
                        v-for="n in notifications.slice(0, 4)"
                        :key="n.id"
                        class="text-sm"
                    >
                        <Link
                            :href="n.href"
                            class="font-medium hover:underline"
                        >
                            {{ n.title }}
                        </Link>
                        <p class="text-xs text-muted-foreground">
                            {{ n.body }}
                        </p>
                    </li>
                </ul>
            </section>
        </div>

        <section class="rounded-xl border bg-card p-4 shadow-xs">
            <h2 class="mb-3 flex items-center gap-2 font-display font-semibold">
                <AlertTriangle class="size-4 text-warning" /> Alertes
                <span class="rounded-full bg-muted px-2 text-xs tabular-nums">
                    {{ taskAlerts.length + budgetAlerts.length }}
                </span>
            </h2>
            <ul class="divide-y">
                <li
                    v-for="b in budgetAlerts"
                    :key="`b${b.chantier.id}`"
                    class="flex flex-wrap items-center justify-between gap-2 py-2.5 text-sm"
                >
                    <Link
                        :href="`/budget/${b.chantier.id}`"
                        class="font-medium hover:underline"
                    >
                        {{ b.chantier.name }} —
                        {{ number(b.totals.consumption, 1) }} % du budget révisé
                    </Link>
                    <StatusBadge
                        kind="budget"
                        :status="budgetLevel(b.totals.consumption)"
                    />
                </li>
                <li
                    v-for="t in taskAlerts"
                    :key="`t${t.task.id}`"
                    class="flex flex-wrap items-center justify-between gap-2 py-2.5 text-sm"
                >
                    <Link
                        :href="`/chantiers/${t.task.chantierId}`"
                        class="hover:underline"
                    >
                        <span class="font-medium">{{ t.task.name }}</span>
                        <span class="text-muted-foreground">
                            —
                            {{
                                chantiers.find(
                                    (c) => c.id === t.task.chantierId,
                                )?.name
                            }}
                        </span>
                    </Link>
                    <StatusBadge kind="task" :status="t.health" />
                </li>
            </ul>
        </section>

        <section
            v-if="can('achats.validate')"
            class="rounded-xl border bg-card p-4 shadow-xs"
        >
            <h2 class="mb-3 font-display font-semibold">File « à valider »</h2>
            <ul class="divide-y">
                <li
                    v-for="r in toValidate"
                    :key="r.id"
                    class="flex flex-wrap items-center justify-between gap-2 py-2.5 text-sm"
                >
                    <Link
                        :href="`/achats/demandes/${r.id}`"
                        class="hover:underline"
                    >
                        <span class="font-medium">{{ r.number }}</span> —
                        {{ r.chantierName }}
                        <span class="text-muted-foreground"
                            >({{ r.requester }})</span
                        >
                    </Link>
                    <StatusBadge
                        v-if="r.needsDirection"
                        label="Validation Direction"
                        tone="brand"
                    />
                </li>
            </ul>
        </section>
    </div>
</template>
