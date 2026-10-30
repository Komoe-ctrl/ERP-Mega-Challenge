<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Clock,
    Bell,
    ClipboardCheck,
    ClipboardList,
    FolderKanban,
    HardHat,
    PackageSearch,
    PenLine,
    Receipt,
    Truck,
    Users,
    Wallet,
} from '@lucide/vue';
import { computed } from 'vue';
import BarChart from '@/components/charts/BarChart.vue';
import KpiCard from '@/components/erp/KpiCard.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { useLegacySession } from '@/composables/useLegacySession';
import { usePermissions } from '@/composables/usePermissions';
import { toast } from 'vue-sonner';
import { dateFr, fcfa, fcfaShort, number, percent } from '@/lib/format';
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
import { invoices, orders, stockItems } from '@/mocks/v2';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Tableau de bord', href: dashboard() }],
    },
});

const page = usePage();
const { can } = usePermissions();
const { hasArrivedToday, clockIn, poste } = useLegacySession();

function pointer() {
    clockIn();
    toast.success('Arrivée enregistrée.');
}
const user = computed(() => page.props.auth.user);
const roles = computed(() => page.props.auth.roles);

/**
 * Tableau de bord par profil : terrain (mes chantiers, mes demandes), achats
 * (demandes à traiter, commandes en cours) ou direction (consolidé multi-chantiers).
 */
type Variant = 'terrain' | 'achats' | 'direction';
const variant = computed<Variant>(() => {
    if (
        roles.value.some((r) =>
            ['chef_chantier', 'conducteur_travaux', 'magasinier'].includes(r),
        )
    ) {
        return 'terrain';
    }

    if (
        roles.value.some((r) =>
            ['service_achats_btp', 'comptabilite'].includes(r),
        )
    ) {
        return 'achats';
    }

    return 'direction';
});
const variantLabel = {
    terrain: 'Vue terrain',
    achats: 'Vue achats',
    direction: 'Vue direction',
} as const;

// --- Données communes
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

// --- Terrain : « mes » chantiers = les chantiers en cours (démo), mes demandes = récentes
const myChantiers = activeChantiers.slice(0, 2);
const myRequests = requests
    .filter((r) => myChantiers.some((c) => c.id === r.chantierId))
    .slice(0, 5);
const myAlerts = taskAlerts.filter((t) =>
    myChantiers.some((c) => c.id === t.task.chantierId),
);

// --- Achats
const toProcess = requests.filter(
    (r) =>
        r.status === 'validee' &&
        !orders.some((o) => o.requestNumbers.includes(r.number)),
);
const openOrders = orders.filter((o) => ['emis', 'partiel'].includes(o.status));
const toControl = invoices.filter((i) =>
    ['a_controler', 'ecart'].includes(i.status),
);
const lowStock = stockItems.filter((s) => s.quantity < s.minimum);
</script>

<template>
    <Head title="Tableau de bord" />

    <div class="space-y-6 p-4">
        <PageHeader
            :title="`Bonjour, ${user.name}`"
            description="Vue d’ensemble de l’activité au 29/10/2026."
        >
            <StatusBadge :label="variantLabel[variant]" tone="brand" />
            <Button v-if="hasArrivedToday" variant="outline" disabled>
                <Clock /> Arrivée pointée
            </Button>
            <Button v-else @click="pointer"
                ><Clock /> Pointer mon arrivée</Button
            >
        </PageHeader>

        <div
            v-if="!hasArrivedToday"
            class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-brand bg-brand/10 p-3 text-sm"
            role="status"
        >
            <p>
                Bonjour, {{ poste.title }} : pensez à pointer votre arrivée pour
                aujourd’hui.
            </p>
            <Button size="sm" @click="pointer">Pointer mon arrivée</Button>
        </div>

        <!-- ================= TERRAIN ================= -->
        <template v-if="variant === 'terrain'">
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Button
                    v-if="can('chantiers.update')"
                    size="lg"
                    class="h-16 flex-col gap-1 bg-brand text-brand-foreground hover:bg-brand/90"
                    as-child
                >
                    <Link :href="`/chantiers/${myChantiers[0].id}/avancement`"
                        ><PenLine class="size-6" /> Avancement</Link
                    >
                </Button>
                <Button
                    v-if="can('achats.create')"
                    size="lg"
                    variant="secondary"
                    class="h-16 flex-col gap-1"
                    as-child
                >
                    <Link href="/achats/demandes/creer"
                        ><ClipboardList class="size-6" /> Nouvelle demande</Link
                    >
                </Button>
                <Button
                    v-if="can('chantiers.update')"
                    size="lg"
                    variant="secondary"
                    class="h-16 flex-col gap-1"
                    as-child
                >
                    <Link :href="`/chantiers/${myChantiers[0].id}/rapport`"
                        ><ClipboardList class="size-6" /> Rapport du jour</Link
                    >
                </Button>
                <Button
                    v-if="can('main_oeuvre.view')"
                    size="lg"
                    variant="secondary"
                    class="h-16 flex-col gap-1"
                    as-child
                >
                    <Link href="/main-oeuvre"
                        ><Users class="size-6" /> Pointage</Link
                    >
                </Button>
            </div>

            <section class="space-y-3">
                <h2 class="font-display font-semibold">Mes chantiers</h2>
                <div class="grid gap-3 md:grid-cols-2">
                    <Link
                        v-for="c in myChantiers"
                        :key="c.id"
                        :href="`/chantiers/${c.id}`"
                        class="rounded-xl border bg-card p-4 shadow-xs hover:bg-accent"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <p class="font-medium">
                                {{ c.code }} — {{ c.name }}
                            </p>
                            <StatusBadge kind="site" :status="c.status" />
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ c.location }} · fin prévue
                            {{ dateFr(c.endDate) }}
                        </p>
                    </Link>
                </div>
            </section>

            <div class="grid gap-4 lg:grid-cols-2">
                <section class="rounded-xl border bg-card p-4 shadow-xs">
                    <h2 class="mb-3 font-display font-semibold">
                        Mes demandes d’achat
                    </h2>
                    <ul class="divide-y text-sm">
                        <li
                            v-for="r in myRequests"
                            :key="r.id"
                            class="flex flex-wrap items-center justify-between gap-2 py-2.5"
                        >
                            <Link
                                :href="`/achats/demandes/${r.id}`"
                                class="hover:underline"
                            >
                                <span class="font-medium">{{ r.number }}</span>
                                <span
                                    class="block text-xs text-muted-foreground"
                                    >{{ fcfa(requestTotal(r)) }}</span
                                >
                            </Link>
                            <StatusBadge kind="request" :status="r.status" />
                        </li>
                    </ul>
                </section>
                <section class="rounded-xl border bg-card p-4 shadow-xs">
                    <h2
                        class="mb-3 flex items-center gap-2 font-display font-semibold"
                    >
                        <AlertTriangle class="size-4 text-warning" /> Tâches en
                        alerte
                    </h2>
                    <ul class="divide-y text-sm">
                        <li
                            v-for="t in myAlerts"
                            :key="t.task.id"
                            class="flex flex-wrap items-center justify-between gap-2 py-2.5"
                        >
                            <Link
                                :href="`/chantiers/${t.task.chantierId}`"
                                class="font-medium hover:underline"
                                >{{ t.task.name }}</Link
                            >
                            <StatusBadge kind="task" :status="t.health" />
                        </li>
                        <li
                            v-if="!myAlerts.length"
                            class="py-2.5 text-muted-foreground"
                        >
                            Aucune alerte.
                        </li>
                    </ul>
                </section>
            </div>
        </template>

        <!-- ================= ACHATS ================= -->
        <template v-else-if="variant === 'achats'">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard
                    label="Demandes à traiter"
                    :value="String(toProcess.length)"
                    hint="Validées, sans bon de commande"
                    :icon="ClipboardCheck"
                    tone="warning"
                    href="/achats/demandes"
                />
                <KpiCard
                    label="Commandes en cours"
                    :value="String(openOrders.length)"
                    :hint="
                        fcfaShort(openOrders.reduce((s, o) => s + o.amount, 0))
                    "
                    :icon="Truck"
                    tone="info"
                    href="/achats/commandes"
                />
                <KpiCard
                    label="Factures à contrôler"
                    :value="String(toControl.length)"
                    :icon="Receipt"
                    :tone="toControl.length ? 'danger' : 'success'"
                    href="/achats/factures"
                />
                <KpiCard
                    label="Stocks sous le seuil"
                    :value="String(lowStock.length)"
                    :icon="PackageSearch"
                    :tone="lowStock.length ? 'danger' : 'success'"
                    href="/stocks"
                />
            </div>
            <div class="grid gap-4 lg:grid-cols-2">
                <section class="rounded-xl border bg-card p-4 shadow-xs">
                    <h2 class="mb-3 font-display font-semibold">
                        Demandes à traiter
                    </h2>
                    <ul class="divide-y text-sm">
                        <li
                            v-for="r in toProcess"
                            :key="r.id"
                            class="flex flex-wrap items-center justify-between gap-2 py-2.5"
                        >
                            <Link
                                :href="`/achats/demandes/${r.id}`"
                                class="hover:underline"
                                ><span class="font-medium">{{ r.number }}</span>
                                — {{ r.chantierName }}</Link
                            >
                            <span class="tabular-nums">{{
                                fcfa(requestTotal(r))
                            }}</span>
                        </li>
                        <li
                            v-if="!toProcess.length"
                            class="py-2.5 text-muted-foreground"
                        >
                            Rien à traiter.
                        </li>
                    </ul>
                </section>
                <section class="rounded-xl border bg-card p-4 shadow-xs">
                    <h2 class="mb-3 font-display font-semibold">
                        Commandes en cours
                    </h2>
                    <ul class="divide-y text-sm">
                        <li
                            v-for="o in openOrders"
                            :key="o.id"
                            class="flex flex-wrap items-center justify-between gap-2 py-2.5"
                        >
                            <Link
                                :href="`/achats/commandes/${o.id}`"
                                class="hover:underline"
                                ><span class="font-medium">{{ o.number }}</span>
                                — {{ o.supplierName }}</Link
                            >
                            <StatusBadge kind="order" :status="o.status" />
                        </li>
                    </ul>
                </section>
            </div>
        </template>

        <!-- ================= DIRECTION ================= -->
        <template v-else>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard
                    label="Projets en cours"
                    :value="
                        String(
                            projects.filter((p) => p.status === 'en_cours')
                                .length,
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
                            >Tout voir</Link
                        >
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
                                >{{ n.title }}</Link
                            >
                            <p class="text-xs text-muted-foreground">
                                {{ n.body }}
                            </p>
                        </li>
                    </ul>
                </section>
            </div>

            <section class="rounded-xl border bg-card p-4 shadow-xs">
                <h2
                    class="mb-3 flex items-center gap-2 font-display font-semibold"
                >
                    <AlertTriangle class="size-4 text-warning" /> Alertes
                    <span
                        class="rounded-full bg-muted px-2 text-xs tabular-nums"
                        >{{ taskAlerts.length + budgetAlerts.length }}</span
                    >
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
                            >{{ b.chantier.name }} —
                            {{ number(b.totals.consumption, 1) }} % du budget
                            révisé</Link
                        >
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
                                }}</span
                            >
                        </Link>
                        <StatusBadge kind="task" :status="t.health" />
                    </li>
                </ul>
            </section>

            <section
                v-if="can('achats.validate')"
                class="rounded-xl border bg-card p-4 shadow-xs"
            >
                <h2 class="mb-3 font-display font-semibold">
                    File « à valider »
                </h2>
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
        </template>
    </div>
</template>
