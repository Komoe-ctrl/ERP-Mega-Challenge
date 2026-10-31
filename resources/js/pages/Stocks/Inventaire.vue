<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import InventoryController from '@/actions/App/Http/Controllers/Stocks/InventoryController';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { number } from '@/lib/format';

type Line = {
    id: number;
    label: string;
    unit: string;
    theoretical: number;
    counted: number | null;
    gap: number | null;
};

const props = defineProps<{
    inventories: {
        id: number;
        number: string;
        warehouse: string;
        status: 'ouvert' | 'valide';
    }[];
    selected: {
        id: number;
        number: string;
        warehouse: string;
        status: 'ouvert' | 'valide';
        openedBy: string;
        canValidate: boolean;
        lines: Line[];
    } | null;
    warehouses: { id: number; name: string }[];
    can: { record: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Stocks', href: '/stocks' },
            { title: 'Inventaire', href: '/stocks/inventaire' },
        ],
    },
});

// CA9 : écart stock théorique / stock compté par article, ajusté après validation.
const counted = ref<Record<number, number | null>>({});
const newWarehouse = ref<string>('');

watch(
    () => props.selected,
    (inventory) => {
        counted.value = Object.fromEntries(
            (inventory?.lines ?? []).map((l) => [l.id, l.counted]),
        );
    },
    { immediate: true },
);

const gapOf = (l: Line): number | null => {
    const value = counted.value[l.id];

    return value === null || value === undefined || (value as unknown) === ''
        ? null
        : Number(value) - l.theoretical;
};

function open() {
    router.post(
        InventoryController.store.url(),
        { warehouse_id: newWarehouse.value },
        {
            onError: (errors) =>
                toast.error(Object.values(errors)[0] ?? 'Ouverture refusée.'),
        },
    );
}

function save() {
    if (!props.selected) {
        return;
    }

    router.put(
        InventoryController.count.url(props.selected.id),
        { counted: counted.value },
        { preserveScroll: true },
    );
}

function validate() {
    if (!props.selected) {
        return;
    }

    router.post(
        InventoryController.validateInventory.url(props.selected.id),
        {},
        {
            preserveScroll: true,
            onError: (errors) =>
                toast.error(Object.values(errors)[0] ?? 'Validation refusée.'),
        },
    );
}
</script>

<template>
    <Head title="Inventaire" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Inventaire"
            :description="
                selected
                    ? `${selected.warehouse} · ${selected.number}, ouvert par ${selected.openedBy}. Les écarts sont ajustés après validation.`
                    : 'Ouvrez un inventaire pour comparer le stock théorique au stock compté.'
            "
        />

        <div class="flex flex-wrap items-end gap-3">
            <div v-if="can.record" class="flex items-end gap-2">
                <label class="grid gap-1 text-sm">
                    <span class="font-medium">Ouvrir un inventaire</span>
                    <select
                        v-model="newWarehouse"
                        class="h-9 rounded-md border bg-background px-2"
                        aria-label="Magasin à inventorier"
                    >
                        <option value="">Magasin…</option>
                        <option
                            v-for="w in warehouses"
                            :key="w.id"
                            :value="w.id"
                        >
                            {{ w.name }}
                        </option>
                    </select>
                </label>
                <Button :disabled="!newWarehouse" @click="open">Ouvrir</Button>
            </div>
            <nav v-if="inventories.length" class="flex flex-wrap gap-2 text-sm">
                <Link
                    v-for="i in inventories"
                    :key="i.id"
                    :href="`/stocks/inventaire?inventaire=${i.id}`"
                    class="rounded-md border px-2.5 py-1 hover:bg-accent"
                    :class="
                        selected?.id === i.id ? 'border-brand bg-brand/10' : ''
                    "
                >
                    {{ i.number }} · {{ i.warehouse }}
                </Link>
            </nav>
        </div>

        <template v-if="selected">
            <div class="flex flex-wrap items-center gap-2">
                <StatusBadge
                    :label="selected.status === 'ouvert' ? 'Ouvert' : 'Validé'"
                    :tone="selected.status === 'ouvert' ? 'warning' : 'success'"
                />
            </div>

            <section
                class="overflow-hidden rounded-xl border bg-card shadow-xs"
            >
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead
                            class="bg-muted/50 text-left text-muted-foreground"
                        >
                            <tr>
                                <th class="p-3 font-medium">Article</th>
                                <th class="p-3 text-right font-medium">
                                    Théorique
                                </th>
                                <th class="p-3 text-right font-medium">
                                    Compté
                                </th>
                                <th class="p-3 text-right font-medium">
                                    Écart
                                </th>
                                <th class="p-3 font-medium" />
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="l in selected.lines"
                                :key="l.id"
                                class="border-t"
                            >
                                <td class="p-3">{{ l.label }}</td>
                                <td class="p-3 text-right tabular-nums">
                                    {{ number(l.theoretical) }} {{ l.unit }}
                                </td>
                                <td class="p-3 text-right">
                                    <input
                                        v-model.number="counted[l.id]"
                                        type="number"
                                        min="0"
                                        step="any"
                                        :disabled="
                                            selected.status !== 'ouvert' ||
                                            !can.record
                                        "
                                        class="h-9 w-28 rounded-md border border-input bg-transparent px-2 text-right tabular-nums"
                                        :aria-label="`Quantité comptée — ${l.label}`"
                                    />
                                </td>
                                <td
                                    class="p-3 text-right font-medium tabular-nums"
                                    :class="
                                        (gapOf(l) ?? 0) < 0
                                            ? 'text-danger'
                                            : (gapOf(l) ?? 0) > 0
                                              ? 'text-success'
                                              : 'text-muted-foreground'
                                    "
                                    data-test="inventory-gap"
                                >
                                    <template v-if="gapOf(l) !== null">
                                        {{ (gapOf(l) ?? 0) > 0 ? '+' : ''
                                        }}{{ number(gapOf(l) ?? 0, 3) }}
                                    </template>
                                    <template v-else>—</template>
                                </td>
                                <td class="p-3">
                                    <StatusBadge
                                        v-if="
                                            gapOf(l) !== null && gapOf(l) !== 0
                                        "
                                        label="Écart à valider"
                                        tone="warning"
                                    />
                                </td>
                            </tr>
                            <tr v-if="!selected.lines.length">
                                <td
                                    colspan="5"
                                    class="p-6 text-center text-muted-foreground"
                                >
                                    Ce magasin n’a aucun article en stock.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <div
                v-if="selected.status === 'ouvert'"
                class="flex flex-wrap gap-2"
            >
                <Button v-if="can.record" variant="outline" @click="save"
                    >Enregistrer le comptage</Button
                >
                <Button v-if="selected.canValidate" @click="validate"
                    >Valider et ajuster le stock</Button
                >
            </div>
        </template>
    </div>
</template>
