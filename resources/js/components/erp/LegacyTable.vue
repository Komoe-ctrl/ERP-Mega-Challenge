<script setup lang="ts">
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { dateFr, fcfa, percent } from '@/lib/format';
import type { Row, TableScreen } from '@/lib/legacyScreens';
import type { Tone } from '@/lib/status';

const props = defineProps<{ screen: TableScreen }>();

const rows = ref<Row[]>(props.screen.rows.map((r) => ({ ...r })));
const selected = ref<number | null>(null);
const search = ref('');

const shown = computed(() =>
    rows.value
        .map((row, index) => ({ row, index }))
        .filter(({ row }) =>
            Object.values(row)
                .join(' ')
                .toLowerCase()
                .includes(search.value.toLowerCase()),
        ),
);

const totalOf = (key: string): number =>
    shown.value.reduce((s, { row }) => s + Number(row[key] ?? 0), 0);

const tone = (value: string): Tone => {
    if (
        /pay|appr|valid|reçu|int[ée]gr|trait|justif|émis|conver|oui/i.test(
            value,
        )
    ) {
        return 'success';
    }

    if (/partiel|à |en cours|brouillon|attendu/i.test(value)) {
        return 'warning';
    }

    return 'neutral';
};

function format(type: string | undefined, value: Row[string]): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (type === 'money') {
        return fcfa(Number(value));
    }

    if (type === 'date') {
        return dateFr(String(value));
    }

    if (type === 'percent') {
        return percent(Number(value), 1);
    }

    return String(value);
}

function applyRowAction() {
    const action = props.screen.rowAction;

    if (!action || selected.value === null) {
        toast.error('Sélectionnez d’abord une ligne.');

        return;
    }

    rows.value[selected.value] = {
        ...rows.value[selected.value],
        [action.field]: action.value,
    };
    toast.success(action.done);
}
</script>

<template>
    <div class="space-y-4 p-4" data-test="legacy-table">
        <PageHeader :title="screen.title" :description="screen.description">
            <input
                v-model="search"
                type="search"
                placeholder="Recherche"
                aria-label="Recherche"
                class="h-8 rounded-md border bg-background px-2 text-sm"
            />
            <Button
                v-for="a in screen.actions ?? []"
                :key="a"
                variant="outline"
                size="sm"
                @click="toast.success(`${a} (maquette).`)"
            >
                {{ a }}
            </Button>
            <Button
                v-if="screen.rowAction"
                size="sm"
                :disabled="selected === null"
                @click="applyRowAction"
            >
                {{ screen.rowAction.label }}
            </Button>
        </PageHeader>

        <div class="overflow-x-auto rounded-xl border bg-card shadow-xs">
            <table class="w-full min-w-[36rem] text-sm">
                <thead
                    class="border-b bg-muted/50 text-left text-xs text-muted-foreground"
                >
                    <tr>
                        <th
                            v-for="c in screen.columns"
                            :key="c.key"
                            class="px-3 py-2"
                            :class="
                                c.type === 'money' || c.type === 'percent'
                                    ? 'text-right'
                                    : ''
                            "
                        >
                            {{ c.label }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="{ row, index } in shown"
                        :key="index"
                        class="border-b last:border-0"
                        :class="[
                            selected === index
                                ? 'bg-accent'
                                : 'hover:bg-accent/50',
                            screen.rowAction ? 'cursor-pointer' : '',
                        ]"
                        :tabindex="screen.rowAction ? 0 : undefined"
                        @click="selected = index"
                        @keydown.enter="selected = index"
                    >
                        <td
                            v-for="c in screen.columns"
                            :key="c.key"
                            class="px-3 py-2"
                            :class="
                                c.type === 'money' || c.type === 'percent'
                                    ? 'text-right tabular-nums'
                                    : ''
                            "
                        >
                            <StatusBadge
                                v-if="c.type === 'badge'"
                                :label="String(row[c.key])"
                                :tone="tone(String(row[c.key]))"
                            />
                            <template v-else>{{
                                format(c.type, row[c.key])
                            }}</template>
                        </td>
                    </tr>
                    <tr v-if="!shown.length">
                        <td
                            :colspan="screen.columns.length"
                            class="px-3 py-6 text-center text-muted-foreground"
                        >
                            Aucune ligne.
                        </td>
                    </tr>
                </tbody>
                <tfoot v-if="screen.totals?.length">
                    <tr class="border-t bg-muted/30 font-medium">
                        <td
                            v-for="(c, i) in screen.columns"
                            :key="c.key"
                            class="px-3 py-2"
                            :class="
                                c.type === 'money'
                                    ? 'text-right tabular-nums'
                                    : ''
                            "
                        >
                            <template v-if="screen.totals.includes(c.key)">{{
                                fcfa(totalOf(c.key))
                            }}</template>
                            <template v-else-if="i === 0">Total</template>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</template>
