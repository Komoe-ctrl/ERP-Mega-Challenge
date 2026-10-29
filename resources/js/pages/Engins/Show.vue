<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import KpiCard from '@/components/erp/KpiCard.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { dateFr, daysBetween, fcfa, number, TODAY } from '@/lib/format';
import { equipments } from '@/mocks/v4';

const props = defineProps<{ id: number }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Engins et équipements', href: '/engins' }],
    },
});

const { can } = usePermissions();
const engine = computed(
    () => equipments.find((e) => e.id === props.id) ?? equipments[0],
);
const daysLeft = computed(() =>
    daysBetween(TODAY, engine.value.nextMaintenance),
);
</script>

<template>
    <Head :title="engine.name" />

    <div class="space-y-5 p-4">
        <PageHeader
            :title="engine.name"
            :description="`${engine.code} · ${engine.type}`"
        >
            <StatusBadge kind="equipment" :status="engine.status" />
            <Button
                v-if="can('engins.update') && engine.status !== 'reparation'"
                variant="outline"
                @click="toast.success('Affectation à un chantier (maquette).')"
                >Affecter à un chantier</Button
            >
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

        <Button variant="ghost" as-child
            ><Link href="/engins">Retour à la liste</Link></Button
        >
    </div>
</template>
