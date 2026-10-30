<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import FormDialog from '@/components/erp/FormDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { dateFr, number, TODAY } from '@/lib/format';
import { orders, receptions } from '@/mocks/v2';
import type { Reception } from '@/types/erp';
import type { FieldDef, FieldValues } from '@/types/forms';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Achats', href: '/achats/demandes' },
            { title: 'Réceptions', href: '/achats/receptions' },
        ],
    },
});

const { can } = usePermissions();
const rows = ref<Reception[]>(receptions.map((r) => ({ ...r })));
const createOpen = ref(false);

const receivable = computed(() =>
    orders.filter((o) => ['emis', 'partiel'].includes(o.status)),
);
const fields = computed<FieldDef[]>(() => [
    {
        name: 'order',
        label: 'Bon de commande',
        type: 'select',
        required: true,
        wide: true,
        options: receivable.value.map((o) => ({
            value: o.number,
            label: `${o.number} — ${o.supplierName}`,
        })),
    },
    {
        name: 'received',
        label: 'Quantité totale reçue',
        type: 'number',
        required: true,
        min: 0,
        step: 1,
        hint: 'Quantité réellement livrée. Un écart avec la commande génère un reliquat ou un retour (RG8.9).',
    },
    {
        name: 'date',
        label: 'Date de réception',
        type: 'date',
        required: true,
        validate: (v) =>
            v && String(v) > TODAY
                ? 'La réception ne peut pas être dans le futur.'
                : null,
    },
    { name: 'note', label: 'Observations', type: 'textarea' },
]);

function create(v: FieldValues) {
    const order = orders.find((o) => o.number === v.order);

    if (!order) {
        return;
    }

    const ordered = order.lines.reduce((s, l) => s + l.ordered, 0);
    const received = Number(v.received);
    const gap: Reception['gap'] =
        received === ordered
            ? 'conforme'
            : received < ordered
              ? 'reliquat'
              : 'retour';

    rows.value.unshift({
        id: Date.now(),
        number: `BR-2026-00${209 + rows.value.length - receptions.length}`,
        orderNumber: order.number,
        supplierName: order.supplierName,
        date: String(v.date),
        gap,
        lines: [{ label: 'Total de la commande', ordered, received, unit: '' }],
    });
    toast.success(
        gap === 'conforme'
            ? 'Réception conforme : entrée en stock (maquette).'
            : gap === 'reliquat'
              ? 'Réception partielle : un reliquat reste à livrer (maquette).'
              : 'Quantité supérieure à la commande : un retour est généré (maquette).',
    );
}
</script>

<template>
    <Head title="Bons de livraison" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Bons de livraison (réceptions)"
            description="Quantités réellement livrées ; un écart avec la commande génère un reliquat ou un retour (RG8.9)."
        >
            <Button
                v-if="can('stocks.create') || can('achats.create')"
                @click="createOpen = true"
                ><Plus /> Enregistrer une réception</Button
            >
        </PageHeader>

        <div class="grid gap-3 lg:grid-cols-2">
            <article
                v-for="r in rows"
                :key="r.id"
                class="space-y-3 rounded-xl border bg-card p-4 shadow-xs"
            >
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-display font-semibold">{{ r.number }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ r.supplierName }} ·
                            <Link
                                :href="`/achats/commandes/${orders.find((o) => o.number === r.orderNumber)?.id ?? ''}`"
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
            </article>
        </div>

        <FormDialog
            v-model:open="createOpen"
            title="Enregistrer une réception"
            :fields="fields"
            @submit="create"
        />
    </div>
</template>
