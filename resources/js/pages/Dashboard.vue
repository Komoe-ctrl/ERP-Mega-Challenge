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
import { dashboard } from '@/routes';
import { invoices, orders, stockItems } from '@/mocks/v2';
import type { RequestStatus, SiteStatus, TaskHealth } from '@/types/erp';

type Level = 'ok' | 'warning' | 'critical';
type TaskAlert = {
    id: number;
    name: string;
    chantierId: number;
    chantierName: string;
    health: TaskHealth;
};

const props = defineProps<{
    variant: 'terrain' | 'achats' | 'direction';
    today: string;
    terrain: {
        chantiers: {
            id: number;
            code: string;
            name: string;
            status: SiteStatus;
            location: string;
            endDate: string;
        }[];
        primaryChantierId: number | null;
        requests: {
            id: number;
            number: string;
            status: RequestStatus;
            total: number;
        }[];
        alerts: TaskAlert[];
    } | null;
    achats: {
        toProcess: {
            id: number;
            number: string;
            chantierName: string;
            total: number;
        }[];
    } | null;
    direction: {
        projects: { inProgress: number; total: number } | null;
        chantiers: { active: number; suspended: number } | null;
        pending: {
            count: number;
            amount: number;
            queue: {
                id: number;
                number: string;
                chantierName: string;
                requester: string;
                needsDirection: boolean;
            }[];
        } | null;
        budget: {
            global: {
                revised: number;
                remaining: number;
                consumption: number;
                level: Level;
            };
            chart: { code: string; revised: number; consumed: number }[];
            alerts: {
                chantierId: number;
                name: string;
                consumption: number;
                level: Level;
            }[];
        } | null;
        taskAlerts: TaskAlert[];
        notifications: {
            id: string;
            title: string;
            body: string;
            href: string;
        }[];
    } | null;
}>();

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

const variantLabel = {
    terrain: 'Vue terrain',
    achats: 'Vue achats',
    direction: 'Vue direction',
} as const;

// Achats : commandes, factures et stocks seront branchés avec la V2 (maquettes en attendant).
const openOrders = orders.filter((o) => ['emis', 'partiel'].includes(o.status));
const toControl = invoices.filter((i) =>
    ['a_controler', 'ecart'].includes(i.status),
);
const lowStock = stockItems.filter((s) => s.quantity < s.minimum);

const chartLabels = computed(
    () => props.direction?.budget?.chart.map((b) => b.code) ?? [],
);
const chartSets = computed(() => [
    {
        label: 'Budget révisé (M FCFA)',
        data:
            props.direction?.budget?.chart.map((b) =>
                Math.round(b.revised / 1_000_000),
            ) ?? [],
    },
    {
        label: 'Engagé + réel (M FCFA)',
        data:
            props.direction?.budget?.chart.map((b) =>
                Math.round(b.consumed / 1_000_000),
            ) ?? [],
    },
]);
const alertCount = computed(
    () =>
        (props.direction?.taskAlerts.length ?? 0) +
        (props.direction?.budget?.alerts.length ?? 0),
);
</script>

<template>
    <Head title="Tableau de bord" />

    <div class="space-y-6 p-4">
        <PageHeader
            :title="`Bonjour, ${user.name}`"
            :description="`Vue d’ensemble de l’activité au ${dateFr(today)}.`"
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
        <template v-if="variant === 'terrain' && terrain">
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Button
                    v-if="can('chantiers.update') && terrain.primaryChantierId"
                    size="lg"
                    class="h-16 flex-col gap-1 bg-brand text-brand-foreground hover:bg-brand/90"
                    as-child
                >
                    <Link
                        :href="`/chantiers/${terrain.primaryChantierId}/avancement`"
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
                    v-if="can('chantiers.update') && terrain.primaryChantierId"
                    size="lg"
                    variant="secondary"
                    class="h-16 flex-col gap-1"
                    as-child
                >
                    <Link
                        :href="`/chantiers/${terrain.primaryChantierId}/rapport`"
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
                        v-for="c in terrain.chantiers"
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
                <p
                    v-if="!terrain.chantiers.length"
                    class="text-sm text-muted-foreground"
                >
                    Aucun chantier ne vous est affecté.
                </p>
            </section>

            <div class="grid gap-4 lg:grid-cols-2">
                <section class="rounded-xl border bg-card p-4 shadow-xs">
                    <h2 class="mb-3 font-display font-semibold">
                        Mes demandes d’achat
                    </h2>
                    <ul class="divide-y text-sm">
                        <li
                            v-for="r in terrain.requests"
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
                                    >{{ fcfa(r.total) }}</span
                                >
                            </Link>
                            <StatusBadge kind="request" :status="r.status" />
                        </li>
                        <li
                            v-if="!terrain.requests.length"
                            class="py-2.5 text-muted-foreground"
                        >
                            Aucune demande.
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
                            v-for="t in terrain.alerts"
                            :key="t.id"
                            class="flex flex-wrap items-center justify-between gap-2 py-2.5"
                        >
                            <Link
                                :href="`/chantiers/${t.chantierId}`"
                                class="font-medium hover:underline"
                                >{{ t.name }}</Link
                            >
                            <StatusBadge kind="task" :status="t.health" />
                        </li>
                        <li
                            v-if="!terrain.alerts.length"
                            class="py-2.5 text-muted-foreground"
                        >
                            Aucune alerte.
                        </li>
                    </ul>
                </section>
            </div>
        </template>

        <!-- ================= ACHATS ================= -->
        <template v-else-if="variant === 'achats' && achats">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard
                    label="Demandes à traiter"
                    :value="String(achats.toProcess.length)"
                    hint="Validées, à prendre en charge"
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
                            v-for="r in achats.toProcess"
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
                                fcfa(r.total)
                            }}</span>
                        </li>
                        <li
                            v-if="!achats.toProcess.length"
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
        <template v-else-if="direction">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard
                    v-if="direction.projects"
                    label="Projets en cours"
                    :value="String(direction.projects.inProgress)"
                    :hint="`${direction.projects.total} projets au total`"
                    :icon="FolderKanban"
                    tone="info"
                    href="/projets"
                />
                <KpiCard
                    v-if="direction.chantiers"
                    label="Chantiers actifs"
                    :value="String(direction.chantiers.active)"
                    :hint="`${direction.chantiers.suspended} suspendu(s)`"
                    :icon="HardHat"
                    tone="success"
                    href="/chantiers"
                />
                <KpiCard
                    v-if="direction.pending"
                    label="Demandes à valider"
                    :value="String(direction.pending.count)"
                    :hint="`${fcfaShort(direction.pending.amount)} à engager`"
                    :icon="ClipboardCheck"
                    tone="warning"
                    href="/achats/a-valider"
                />
                <KpiCard
                    v-if="direction.budget"
                    label="Budget consommé"
                    :value="percent(direction.budget.global.consumption, 1)"
                    :hint="`${fcfaShort(direction.budget.global.remaining)} restant à engager`"
                    :icon="Wallet"
                    :tone="
                        direction.budget.global.level === 'ok'
                            ? 'success'
                            : 'warning'
                    "
                    href="/budget"
                />
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <section
                    v-if="direction.budget"
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
                            v-for="n in direction.notifications"
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
                        <li
                            v-if="!direction.notifications.length"
                            class="text-sm text-muted-foreground"
                        >
                            Vous êtes à jour.
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
                        >{{ alertCount }}</span
                    >
                </h2>
                <ul class="divide-y">
                    <li
                        v-for="b in direction.budget?.alerts ?? []"
                        :key="`b${b.chantierId}`"
                        class="flex flex-wrap items-center justify-between gap-2 py-2.5 text-sm"
                    >
                        <Link
                            :href="`/budget/${b.chantierId}`"
                            class="font-medium hover:underline"
                            >{{ b.name }} — {{ number(b.consumption, 1) }} % du
                            budget révisé</Link
                        >
                        <StatusBadge kind="budget" :status="b.level" />
                    </li>
                    <li
                        v-for="t in direction.taskAlerts"
                        :key="`t${t.id}`"
                        class="flex flex-wrap items-center justify-between gap-2 py-2.5 text-sm"
                    >
                        <Link
                            :href="`/chantiers/${t.chantierId}`"
                            class="hover:underline"
                        >
                            <span class="font-medium">{{ t.name }}</span>
                            <span class="text-muted-foreground">
                                — {{ t.chantierName }}</span
                            >
                        </Link>
                        <StatusBadge kind="task" :status="t.health" />
                    </li>
                    <li
                        v-if="!alertCount"
                        class="py-2.5 text-sm text-muted-foreground"
                    >
                        Aucune alerte.
                    </li>
                </ul>
            </section>

            <section
                v-if="direction.pending && can('achats.validate')"
                class="rounded-xl border bg-card p-4 shadow-xs"
            >
                <h2 class="mb-3 font-display font-semibold">
                    File « à valider »
                </h2>
                <ul class="divide-y">
                    <li
                        v-for="r in direction.pending.queue"
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
                    <li
                        v-if="!direction.pending.queue.length"
                        class="py-2.5 text-sm text-muted-foreground"
                    >
                        Aucune demande en attente.
                    </li>
                </ul>
            </section>
        </template>
    </div>
</template>
