<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import EmptyState from '@/components/erp/EmptyState.vue';
import KpiCard from '@/components/erp/KpiCard.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import SubNav from '@/components/erp/SubNav.vue';
import { Button } from '@/components/ui/button';
import { dateFr, fcfa } from '@/lib/format';
import { rhNav } from '@/lib/navs';
import { advances, categoryLabels, employees, leaves } from '@/mocks/v4';

const props = defineProps<{ id: number }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Ressources humaines', href: '/rh' }] },
});

const employee = computed(
    () => employees.find((e) => e.id === props.id) ?? employees[0],
);
const employeeLeaves = computed(() =>
    leaves.filter((l) => l.employee === employee.value.name),
);
const employeeAdvances = computed(() =>
    advances.filter((a) => a.employee === employee.value.name),
);
const leftToDeduct = computed(() =>
    employeeAdvances.value.reduce((s, a) => s + a.amount - a.deducted, 0),
);

// RG13.1 : l'historique des affectations suit les chantiers où l'employé a travaillé.
const history = computed(() => [
    {
        chantier: employee.value.chantier,
        from: '2026-06-01',
        to: null as string | null,
    },
    ...(employee.value.chantier === 'Siège'
        ? []
        : [{ chantier: 'Siège', from: '2025-01-06', to: '2026-05-31' }]),
]);
</script>

<template>
    <Head :title="employee.name" />

    <div class="space-y-5 p-4">
        <PageHeader
            :title="employee.name"
            :description="`${employee.matricule} · ${employee.job} · ${categoryLabels[employee.category]}`"
        >
            <StatusBadge
                :label="employee.onLeave ? 'En congé' : 'En poste'"
                :tone="employee.onLeave ? 'warning' : 'success'"
            />
            <Button variant="outline" as-child
                ><Link :href="`/rh/${employee.id}/bulletin`"
                    >Bulletin de paie</Link
                ></Button
            >
        </PageHeader>
        <SubNav :links="rhNav" />

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <KpiCard label="Contrat" :value="employee.contract" />
            <KpiCard
                label="Salaire de base"
                :value="employee.baseSalary ? fcfa(employee.baseSalary) : '—'"
            />
            <KpiCard
                label="Coût horaire"
                :value="fcfa(employee.hourlyCost)"
                hint="Charges comprises"
            />
            <KpiCard
                label="Avances restant à déduire"
                :value="fcfa(leftToDeduct)"
                :tone="leftToDeduct ? 'warning' : 'success'"
            />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-xl border bg-card p-4 shadow-xs">
                <h2 class="mb-3 font-display font-semibold">
                    Historique des affectations
                </h2>
                <ul class="divide-y text-sm">
                    <li
                        v-for="h in history"
                        :key="h.from"
                        class="flex justify-between gap-2 py-2.5"
                    >
                        <span class="font-medium">{{ h.chantier }}</span>
                        <span class="text-muted-foreground"
                            >depuis le {{ dateFr(h.from)
                            }}{{
                                h.to ? ` jusqu’au ${dateFr(h.to)}` : ''
                            }}</span
                        >
                    </li>
                </ul>
            </section>
            <section class="rounded-xl border bg-card p-4 shadow-xs">
                <h2 class="mb-3 font-display font-semibold">Congés</h2>
                <ul v-if="employeeLeaves.length" class="divide-y text-sm">
                    <li
                        v-for="l in employeeLeaves"
                        :key="l.id"
                        class="flex flex-wrap items-center justify-between gap-2 py-2.5"
                    >
                        <span
                            >{{ l.type
                            }}<span class="block text-xs text-muted-foreground"
                                >{{ dateFr(l.from) }} → {{ dateFr(l.to) }}</span
                            ></span
                        >
                        <StatusBadge kind="leave" :status="l.status" />
                    </li>
                </ul>
                <EmptyState v-else compact title="Aucun congé" />
            </section>
        </div>
    </div>
</template>
