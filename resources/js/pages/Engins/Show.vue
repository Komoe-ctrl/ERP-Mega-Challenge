<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import FormDialog from '@/components/erp/FormDialog.vue';
import KpiCard from '@/components/erp/KpiCard.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { dateFr, daysBetween, fcfa, number, TODAY } from '@/lib/format';
import { chantiers } from '@/mocks/chantiers';
import { equipments } from '@/mocks/v4';
import type { Equipment } from '@/types/erp';
import type { FieldDef, FieldValues } from '@/types/forms';

const props = defineProps<{ id: number }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Engins et équipements', href: '/engins' }],
    },
});

const { can } = usePermissions();
const engine = reactive<Equipment>(
    structuredClone(equipments.find((e) => e.id === props.id) ?? equipments[0]),
);
const daysLeft = computed(() => daysBetween(TODAY, engine.nextMaintenance));
const canEdit = computed(() => can('engins.update'));

const assignOpen = ref(false);
const usageOpen = ref(false);
const maintenanceOpen = ref(false);
const repairOpen = ref(false);
const history = ref<{ date: string; text: string }[]>([
    { date: '2026-09-02', text: `Affectation à « ${engine.chantier} »` },
]);

const assignFields: FieldDef[] = [
    {
        name: 'chantier',
        label: 'Chantier',
        type: 'select',
        required: true,
        wide: true,
        options: chantiers
            .filter((c) => c.status === 'en_cours')
            .map((c) => ({ value: c.name, label: c.name })),
        validate: (v) =>
            v === engine.chantier
                ? 'L’engin est déjà affecté à ce chantier.'
                : null,
    },
    { name: 'from', label: 'À partir du', type: 'date', required: true },
];
const usageFields: FieldDef[] = [
    {
        name: 'hours',
        label: 'Heures d’utilisation',
        type: 'number',
        required: true,
        min: 0.5,
        step: 0.5,
        unit: 'h',
    },
    {
        name: 'fuel',
        label: 'Carburant consommé',
        type: 'number',
        required: true,
        min: 0,
        step: 1,
        unit: 'L',
    },
];
const maintenanceFields: FieldDef[] = [
    {
        name: 'date',
        label: 'Prochaine échéance',
        type: 'date',
        required: true,
        validate: (v) =>
            v && String(v) < TODAY ? 'La date doit être dans le futur.' : null,
    },
    {
        name: 'hours',
        label: 'ou à (heures de fonctionnement)',
        type: 'number',
        min: 1,
        step: 1,
        unit: 'h',
        hint: 'Maintenance planifiée par date ou par heures (RG14.3).',
    },
];
const repairFields: FieldDef[] = [
    {
        name: 'cost',
        label: 'Coût estimé',
        type: 'number',
        required: true,
        min: 0,
        step: 1,
        unit: 'FCFA',
    },
    {
        name: 'reason',
        label: 'Nature de la panne',
        type: 'textarea',
        required: true,
        minLength: 5,
    },
];

function assign(v: FieldValues) {
    engine.chantier = String(v.chantier);
    engine.status = 'affecte';
    history.value.unshift({
        date: String(v.from),
        text: `Affectation à « ${String(v.chantier)} » (affectation précédente clôturée)`,
    });
    toast.success(
        'Engin affecté : un engin ne peut l’être qu’à un seul chantier à la fois (RG14.1).',
    );
}
function addUsage(v: FieldValues) {
    engine.hours += Number(v.hours);
    engine.fuelLiters += Number(v.fuel);
    history.value.unshift({
        date: TODAY,
        text: `Utilisation : ${number(Number(v.hours), 1)} h, ${number(Number(v.fuel))} L — coût imputé au chantier (RG14.2)`,
    });
    toast.success(
        'Utilisation enregistrée et imputée au coût réel du chantier (maquette).',
    );
}
function planMaintenance(v: FieldValues) {
    engine.nextMaintenance = String(v.date);
    history.value.unshift({
        date: TODAY,
        text: `Maintenance préventive planifiée le ${dateFr(String(v.date))}`,
    });
    toast.success(
        'Maintenance planifiée : une alerte partira avant l’échéance.',
    );
}
function declareRepair(v: FieldValues) {
    engine.status = 'reparation';
    engine.repairCost += Number(v.cost);
    history.value.unshift({
        date: TODAY,
        text: `Mise en réparation : ${String(v.reason)}`,
    });
    toast.success('Engin indisponible pendant la réparation (RG14.4).');
}
function endRepair() {
    engine.status = 'disponible';
    engine.chantier = '—';
    history.value.unshift({
        date: TODAY,
        text: 'Fin de réparation : engin à nouveau disponible',
    });
    toast.success('Engin remis en service.');
}
</script>

<template>
    <Head :title="engine.name" />

    <div class="space-y-5 p-4">
        <PageHeader
            :title="engine.name"
            :description="`${engine.code} · ${engine.type}`"
        >
            <StatusBadge kind="equipment" :status="engine.status" />
            <template v-if="canEdit">
                <Button
                    v-if="engine.status !== 'reparation'"
                    variant="outline"
                    @click="assignOpen = true"
                    >Affecter à un chantier</Button
                >
                <Button
                    v-if="engine.status === 'affecte'"
                    variant="outline"
                    @click="usageOpen = true"
                    >Saisir l’utilisation</Button
                >
                <Button variant="outline" @click="maintenanceOpen = true"
                    >Planifier une maintenance</Button
                >
                <Button
                    v-if="engine.status !== 'reparation'"
                    variant="outline"
                    class="text-destructive"
                    @click="repairOpen = true"
                    >Déclarer une réparation</Button
                >
                <Button v-else @click="endRepair">Fin de réparation</Button>
            </template>
        </PageHeader>

        <p
            v-if="engine.status === 'reparation'"
            class="rounded-lg bg-danger-soft p-3 text-sm text-danger"
        >
            En réparation : l’engin est indisponible et ne peut pas être
            affecté.
        </p>
        <p
            v-else-if="daysLeft <= 14"
            class="rounded-lg bg-warning-soft p-3 text-sm text-warning"
        >
            Maintenance préventive prévue le
            {{ dateFr(engine.nextMaintenance) }} (dans {{ daysLeft }} jours).
        </p>

        <!-- CA14 : chantier actuel, heures, consommation et coût cumulé des réparations -->
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <KpiCard label="Chantier actuel" :value="engine.chantier" />
            <KpiCard
                label="Heures de fonctionnement"
                :value="`${number(engine.hours)} h`"
            />
            <KpiCard
                label="Carburant consommé"
                :value="`${number(engine.fuelLiters)} L`"
            />
            <KpiCard
                label="Coût cumulé des réparations"
                :value="fcfa(engine.repairCost)"
                tone="warning"
            />
        </div>

        <section class="rounded-xl border bg-card p-4 shadow-xs">
            <h2 class="mb-3 font-display font-semibold">Journal de l’engin</h2>
            <ul class="divide-y text-sm">
                <li
                    v-for="(h, i) in history"
                    :key="i"
                    class="flex justify-between gap-3 py-2.5"
                >
                    <span>{{ h.text }}</span>
                    <span class="shrink-0 text-xs text-muted-foreground">{{
                        dateFr(h.date)
                    }}</span>
                </li>
            </ul>
        </section>

        <Button variant="ghost" as-child
            ><Link href="/engins">Retour à la liste</Link></Button
        >

        <FormDialog
            v-model:open="assignOpen"
            title="Affecter à un chantier"
            :initial="{ from: TODAY }"
            :fields="assignFields"
            @submit="assign"
        />
        <FormDialog
            v-model:open="usageOpen"
            title="Utilisation de l’engin"
            description="Coût imputé au chantier = heures × coût horaire + carburant (RG14.2)."
            :fields="usageFields"
            @submit="addUsage"
        />
        <FormDialog
            v-model:open="maintenanceOpen"
            title="Planifier une maintenance préventive"
            :fields="maintenanceFields"
            @submit="planMaintenance"
        />
        <FormDialog
            v-model:open="repairOpen"
            title="Déclarer une réparation"
            :fields="repairFields"
            submit-label="Mettre en réparation"
            @submit="declareRepair"
        />
    </div>
</template>
