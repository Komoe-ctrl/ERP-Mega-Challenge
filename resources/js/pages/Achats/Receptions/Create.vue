<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import ReceptionController from '@/actions/App/Http/Controllers/Achats/ReceptionController';
import PageHeader from '@/components/erp/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { number } from '@/lib/format';
import type { PurchaseOrder } from '@/types/erp';

/**
 * Réception d'un bon de commande : quantités livrées et renvoyées par ligne. Un écart avec la
 * commande génère un reliquat ou un retour (RG8.9) ; la réception conforme entre en stock.
 */
const props = defineProps<{ order: PurchaseOrder }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Achats', href: '/achats/demandes' },
            { title: 'Bons de livraison', href: '/achats/receptions' },
        ],
    },
});

const form = useForm({
    note: '',
    lines: props.order.lines.map((l) => ({
        purchase_order_line_id: l.id as number,
        delivered: Math.max(0, l.ordered - l.received),
        returned: 0,
    })),
});

function submit() {
    form.post(ReceptionController.store.url(props.order.id));
}
</script>

<template>
    <Head :title="`Réception de ${order.number}`" />

    <div class="space-y-4 p-4">
        <PageHeader
            :title="`Réception de ${order.number}`"
            :description="`${order.supplierName} · ${order.chantierName}`"
        />

        <form class="space-y-4" @submit.prevent="submit">
            <section
                class="overflow-x-auto rounded-xl border bg-card shadow-xs"
            >
                <table class="w-full min-w-[40rem] text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="p-3 font-medium">Article</th>
                            <th class="p-3 text-right font-medium">Commandé</th>
                            <th class="p-3 text-right font-medium">
                                Déjà reçu
                            </th>
                            <th class="w-32 p-3 font-medium">Livré</th>
                            <th class="w-32 p-3 font-medium">Renvoyé</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(l, i) in order.lines"
                            :key="l.id"
                            class="border-t align-top"
                        >
                            <td class="p-3">{{ l.label }}</td>
                            <td class="p-3 text-right tabular-nums">
                                {{ number(l.ordered) }} {{ l.unit }}
                            </td>
                            <td class="p-3 text-right tabular-nums">
                                {{ number(l.received) }}
                            </td>
                            <td class="p-3">
                                <input
                                    v-model.number="form.lines[i].delivered"
                                    type="number"
                                    min="0"
                                    step="any"
                                    class="h-8 w-full rounded-md border bg-background px-2 tabular-nums"
                                    :aria-label="`Quantité livrée : ${l.label}`"
                                />
                                <InputError
                                    :message="
                                        form.errors[
                                            `lines.${i}.delivered` as keyof typeof form.errors
                                        ]
                                    "
                                />
                            </td>
                            <td class="p-3">
                                <input
                                    v-model.number="form.lines[i].returned"
                                    type="number"
                                    min="0"
                                    step="any"
                                    class="h-8 w-full rounded-md border bg-background px-2 tabular-nums"
                                    :aria-label="`Quantité renvoyée : ${l.label}`"
                                />
                                <InputError
                                    :message="
                                        form.errors[
                                            `lines.${i}.returned` as keyof typeof form.errors
                                        ]
                                    "
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
            <InputError :message="form.errors.lines" />

            <label class="grid max-w-xl gap-1 text-sm">
                <span class="font-medium">Observations</span>
                <textarea
                    v-model="form.note"
                    rows="3"
                    class="rounded-md border bg-background px-2 py-1.5"
                />
            </label>

            <div class="flex flex-wrap gap-2">
                <Button type="submit" :disabled="form.processing"
                    >Enregistrer la réception</Button
                >
                <Button variant="outline" as-child
                    ><Link :href="`/achats/commandes/${order.id}`"
                        >Annuler</Link
                    ></Button
                >
            </div>
        </form>
    </div>
</template>
