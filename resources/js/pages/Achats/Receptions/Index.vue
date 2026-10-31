<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import ReceptionController from '@/actions/App/Http/Controllers/Achats/ReceptionController';
import FormDialog from '@/components/erp/FormDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import { Button } from '@/components/ui/button';
import { dateFr, number } from '@/lib/format';
import type { Paginated } from '@/types';
import type { Reception } from '@/types/erp';
import type { FieldDef, FieldValues } from '@/types/forms';

const props = defineProps<{
    receptions: Paginated<Reception & { evaluated: boolean }>;
    receivable: { id: number; number: string; supplierName: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Achats', href: '/achats/demandes' },
            { title: 'Bons de livraison', href: '/achats/receptions' },
        ],
    },
});

const createOpen = ref(false);

const fields = computed<FieldDef[]>(() => [
    {
        name: 'order',
        label: 'Bon de commande à réceptionner',
        type: 'select',
        required: true,
        wide: true,
        options: props.receivable.map((o) => ({
            value: String(o.id),
            label: `${o.number} — ${o.supplierName}`,
        })),
        hint: props.receivable.length
            ? 'Vous saisirez ensuite les quantités réellement livrées.'
            : 'Aucun bon de commande en attente de livraison.',
    },
]);

function start(v: FieldValues) {
    router.visit(ReceptionController.create.url(Number(v.order)));
}
</script>

<template>
    <Head title="Bons de livraison" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Bons de livraison (réceptions)"
            description="Quantités réellement livrées ; un écart avec la commande génère un reliquat ou un retour (RG8.9)."
        >
            <Button v-if="receivable.length" @click="createOpen = true"
                ><Plus /> Enregistrer une réception</Button
            >
        </PageHeader>

        <div class="grid gap-3 lg:grid-cols-2">
            <article
                v-for="r in receptions.data"
                :key="r.id"
                class="space-y-3 rounded-xl border bg-card p-4 shadow-xs"
            >
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-display font-semibold">{{ r.number }}</p>
                        <p class="text-sm text-muted-foreground">
                            <Link
                                v-if="r.supplierId"
                                :href="`/fournisseurs/${r.supplierId}`"
                                class="hover:underline"
                                >{{ r.supplierName }}</Link
                            >
                            <template v-else>{{ r.supplierName }}</template>
                            ·
                            <Link
                                :href="`/achats/commandes/${r.orderId}`"
                                class="hover:underline"
                                >{{ r.orderNumber }}</Link
                            >
                            · {{ dateFr(r.date) }}
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
                <p v-if="r.note" class="text-sm text-muted-foreground">
                    {{ r.note }}
                </p>
            </article>
            <p
                v-if="!receptions.data.length"
                class="col-span-full rounded-xl border border-dashed bg-card p-6 text-center text-sm text-muted-foreground"
            >
                Aucune réception enregistrée.
            </p>
        </div>
        <PaginationBar :page="receptions" />

        <FormDialog
            v-model:open="createOpen"
            title="Enregistrer une réception"
            :fields="fields"
            submit-label="Continuer"
            @submit="start"
        />
    </div>
</template>
