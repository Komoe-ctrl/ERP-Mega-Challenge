<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import AdminNav from '@/components/erp/AdminNav.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import { Button } from '@/components/ui/button';

type Step = {
    label: string;
    role_name: string;
    button_label: string;
    is_active: boolean;
};

const props = defineProps<{
    steps: Step[];
    roles: { value: string; label: string }[];
    can: { update: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administration', href: '/administration' },
            {
                title: 'Étapes de validation',
                href: '/administration/etapes-de-validation',
            },
        ],
    },
});

const rows = ref<Step[]>(props.steps.map((s) => ({ ...s })));
const errors = ref<Record<string, string>>({});
const processing = ref(false);

function move(i: number, d: -1 | 1) {
    const j = i + d;

    if (j < 0 || j >= rows.value.length) {
        return;
    }

    [rows.value[i], rows.value[j]] = [rows.value[j], rows.value[i]];
}

function add() {
    rows.value.push({
        label: '',
        role_name: props.roles[0]?.value ?? '',
        button_label: '',
        is_active: true,
    });
}

function save() {
    processing.value = true;
    router.put(
        '/administration/etapes-de-validation',
        { steps: rows.value },
        {
            preserveScroll: true,
            onError: (e) => (errors.value = e as Record<string, string>),
            onSuccess: () => (errors.value = {}),
            onFinish: () => (processing.value = false),
        },
    );
}
</script>

<template>
    <Head title="Étapes de validation" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Étapes de validation"
            description="Circuit appliqué à toutes les pièces (notes de frais, recettes, pièces de caisse) : l’ordre de la liste est l’ordre des étapes. Une étape est portée par un rôle."
        >
            <Button v-if="can.update" :disabled="processing" @click="save"
                >Enregistrer</Button
            >
        </PageHeader>
        <AdminNav />

        <p
            v-if="errors.steps"
            class="rounded-md bg-destructive/10 p-3 text-sm text-destructive"
            role="alert"
        >
            {{ errors.steps }}
        </p>

        <div class="overflow-x-auto rounded-xl border bg-card shadow-xs">
            <table class="w-full min-w-[44rem] text-sm">
                <thead
                    class="border-b bg-muted/50 text-left text-xs text-muted-foreground"
                >
                    <tr>
                        <th class="px-3 py-2">Ordre</th>
                        <th class="px-3 py-2">Étape</th>
                        <th class="px-3 py-2">Rôle habilité</th>
                        <th class="px-3 py-2">Bouton</th>
                        <th class="px-3 py-2">Active</th>
                        <th class="px-3 py-2" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(s, i) in rows"
                        :key="i"
                        class="border-b last:border-0"
                    >
                        <td class="px-3 py-2 tabular-nums">{{ i + 1 }}</td>
                        <td class="px-3 py-2">
                            <input
                                v-model="s.label"
                                :disabled="!can.update"
                                class="h-8 w-full rounded-md border bg-background px-2"
                                :aria-label="`Libellé de l’étape ${i + 1}`"
                            />
                            <p
                                v-if="errors[`steps.${i}.label`]"
                                class="text-xs text-destructive"
                            >
                                {{ errors[`steps.${i}.label`] }}
                            </p>
                        </td>
                        <td class="px-3 py-2">
                            <select
                                v-model="s.role_name"
                                :disabled="!can.update"
                                class="h-8 w-full rounded-md border bg-background px-2"
                                :aria-label="`Rôle de l’étape ${i + 1}`"
                            >
                                <option
                                    v-for="r in roles"
                                    :key="r.value"
                                    :value="r.value"
                                >
                                    {{ r.label }}
                                </option>
                            </select>
                            <p
                                v-if="errors[`steps.${i}.role_name`]"
                                class="text-xs text-destructive"
                            >
                                {{ errors[`steps.${i}.role_name`] }}
                            </p>
                        </td>
                        <td class="px-3 py-2">
                            <input
                                v-model="s.button_label"
                                :disabled="!can.update"
                                class="h-8 w-full rounded-md border bg-background px-2"
                                :aria-label="`Bouton de l’étape ${i + 1}`"
                            />
                        </td>
                        <td class="px-3 py-2">
                            <input
                                v-model="s.is_active"
                                type="checkbox"
                                :disabled="!can.update"
                                :aria-label="`Étape ${i + 1} active`"
                            />
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            <template v-if="can.update">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    :disabled="i === 0"
                                    aria-label="Monter"
                                    @click="move(i, -1)"
                                    ><ArrowUp
                                /></Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    :disabled="i === rows.length - 1"
                                    aria-label="Descendre"
                                    @click="move(i, 1)"
                                    ><ArrowDown
                                /></Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    aria-label="Supprimer l’étape"
                                    @click="rows.splice(i, 1)"
                                    ><Trash2
                                /></Button>
                            </template>
                        </td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td
                            colspan="6"
                            class="px-3 py-6 text-center text-muted-foreground"
                        >
                            Aucune étape : les pièces ne peuvent pas être
                            soumises.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Button v-if="can.update" variant="outline" size="sm" @click="add"
            ><Plus /> Ajouter une étape</Button
        >
    </div>
</template>
