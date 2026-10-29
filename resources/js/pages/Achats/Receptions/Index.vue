<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { toast } from 'vue-sonner';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import SubNav from '@/components/erp/SubNav.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { dateFr, number } from '@/lib/format';
import { achatsNav } from '@/lib/navs';
import { receptions } from '@/mocks/v2';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Achats', href: '/achats/demandes' },
            { title: 'Réceptions', href: '/achats/receptions' },
        ],
    },
});

const { can } = usePermissions();
</script>

<template>
    <Head title="Réceptions" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Réceptions"
            description="Quantités réellement livrées ; un écart avec la commande génère un reliquat ou un retour (RG8.9)."
        >
            <Button
                v-if="can('stocks.create') || can('achats.create')"
                @click="toast.success('Nouvelle réception (maquette).')"
                ><Plus /> Enregistrer une réception</Button
            >
        </PageHeader>
        <SubNav :links="achatsNav" />

        <div class="grid gap-3 lg:grid-cols-2">
            <article
                v-for="r in receptions"
                :key="r.id"
                class="space-y-3 rounded-xl border bg-card p-4 shadow-xs"
            >
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-display font-semibold">{{ r.number }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ r.supplierName }} · {{ r.orderNumber }} ·
                            {{ dateFr(r.date) }}
                        </p>
                    </div>
                    <StatusBadge kind="reception" :status="r.gap" />
                </div>
                <table class="w-full text-sm">
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr>
                            <th class="py-1 font-medium">Article</th>
                            <th class="py-1 text-right font-medium">
                                Commandé
                            </th>
                            <th class="py-1 text-right font-medium">Reçu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="l in r.lines"
                            :key="l.label"
                            class="border-t"
                        >
                            <td class="py-1.5">{{ l.label }}</td>
                            <td class="py-1.5 text-right tabular-nums">
                                {{ number(l.ordered) }}
                            </td>
                            <td
                                class="py-1.5 text-right tabular-nums"
                                :class="
                                    l.received < l.ordered
                                        ? 'font-medium text-warning'
                                        : ''
                                "
                            >
                                {{ number(l.received) }} {{ l.unit }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </article>
        </div>
    </div>
</template>
