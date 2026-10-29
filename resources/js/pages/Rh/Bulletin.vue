<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Printer } from '@lucide/vue';
import { computed } from 'vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { fcfa, number } from '@/lib/format';
import { advances, employees } from '@/mocks/v4';

const props = defineProps<{ id: number }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Ressources humaines', href: '/rh' }] },
});

const employee = computed(
    () => employees.find((e) => e.id === props.id) ?? employees[0],
);

// CA13 : le bulletin reprend le salaire de base, les heures, les primes et la déduction des avances (RG13.3).
const hours = computed(() =>
    employee.value.contract === 'Journalier' ? 168 : 173,
);
const base = computed(
    () => employee.value.baseSalary || employee.value.hourlyCost * hours.value,
);
const bonuses = computed(() => [
    {
        label: 'Prime de chantier',
        amount: employee.value.chantier === 'Siège' ? 0 : 45_000,
    },
    { label: 'Prime de transport', amount: 30_000 },
]);
const bonusTotal = computed(() =>
    bonuses.value.reduce((s, b) => s + b.amount, 0),
);
const advanceDeduction = computed(() => {
    const left = advances
        .filter((a) => a.employee === employee.value.name)
        .reduce((s, a) => s + a.amount - a.deducted, 0);

    return Math.min(left, Math.round((base.value + bonusTotal.value) * 0.3)); // retenue plafonnée à 30 %
});
const gross = computed(() => base.value + bonusTotal.value);
const net = computed(() => gross.value - advanceDeduction.value);

function print() {
    window.print();
}
</script>

<template>
    <Head :title="`Bulletin — ${employee.name}`" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Bulletin de paie"
            :description="`${employee.name} · octobre 2026`"
        >
            <Button variant="outline" @click="print"
                ><Printer /> Imprimer</Button
            >
            <Button variant="outline" as-child
                ><Link :href="`/rh/${employee.id}`">Fiche employé</Link></Button
            >
        </PageHeader>

        <article
            class="print-area mx-auto w-full max-w-2xl space-y-4 rounded-xl border bg-card p-6 shadow-xs"
        >
            <header
                class="flex items-start justify-between gap-3 border-b-2 border-brand pb-3"
            >
                <img
                    src="/brand/logo-mega-challenge.svg"
                    alt="Mega Challenge"
                    class="h-12 w-auto"
                />
                <div class="text-right text-sm">
                    <p class="font-display font-semibold">BULLETIN DE PAIE</p>
                    <p class="text-muted-foreground">Période : octobre 2026</p>
                </div>
            </header>
            <p class="text-sm">
                <span class="font-medium">{{ employee.name }}</span> —
                {{ employee.matricule }} · {{ employee.job }} · contrat
                {{ employee.contract }}
            </p>

            <table class="w-full text-sm tabular-nums">
                <tbody>
                    <tr class="border-b">
                        <td class="py-2">Salaire de base</td>
                        <td class="py-2 text-right">{{ fcfa(base) }}</td>
                    </tr>
                    <tr class="border-b">
                        <td class="py-2">Heures travaillées</td>
                        <td class="py-2 text-right">
                            {{ number(hours) }} h (coût horaire
                            {{ fcfa(employee.hourlyCost) }})
                        </td>
                    </tr>
                    <tr v-for="b in bonuses" :key="b.label" class="border-b">
                        <td class="py-2">{{ b.label }}</td>
                        <td class="py-2 text-right">+{{ fcfa(b.amount) }}</td>
                    </tr>
                    <tr class="border-b font-medium">
                        <td class="py-2">Total brut</td>
                        <td class="py-2 text-right">{{ fcfa(gross) }}</td>
                    </tr>
                    <tr class="border-b">
                        <td class="py-2">Déduction des avances sur salaire</td>
                        <td class="py-2 text-right">
                            −{{ fcfa(advanceDeduction) }}
                        </td>
                    </tr>
                    <tr class="font-display text-base font-semibold">
                        <td class="py-3">Net à payer</td>
                        <td class="py-3 text-right">{{ fcfa(net) }}</td>
                    </tr>
                </tbody>
            </table>
            <p class="text-xs text-muted-foreground">
                Paie préparée à partir des présences et heures de la
                Main-d’œuvre, puis transmise à Finance pour paiement (RG13.4).
            </p>
        </article>
    </div>
</template>
