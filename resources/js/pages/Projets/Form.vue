<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import PageHeader from '@/components/erp/PageHeader.vue';
import SchemaForm from '@/components/erp/SchemaForm.vue';
import { projects } from '@/mocks/chantiers';
import { clients } from '@/mocks/v3';
import type { FieldDef, FieldValues } from '@/types/forms';

const props = defineProps<{ id?: number }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Projets', href: '/projets' }] },
});

const existing = computed(() => projects.find((p) => p.id === props.id));
const title = computed(() =>
    existing.value ? 'Modifier le projet' : 'Nouveau projet',
);

const managers = [...new Set(projects.map((p) => p.manager))];

const fields: FieldDef[] = [
    {
        name: 'name',
        label: 'Nom du projet',
        required: true,
        minLength: 5,
        wide: true,
    },
    {
        name: 'client',
        label: 'Client',
        type: 'select',
        required: true,
        options: clients.map((c) => ({ value: c.name, label: c.name })),
    },
    { name: 'location', label: 'Localisation', required: true },
    {
        name: 'manager',
        label: 'Responsable',
        type: 'select',
        required: true,
        options: managers.map((m) => ({ value: m, label: m })),
    },
    {
        name: 'agency',
        label: 'Agence',
        type: 'select',
        required: true,
        options: [
            { value: 'Siège Abidjan', label: 'Siège Abidjan' },
            { value: 'Agence Bouaké', label: 'Agence Bouaké' },
        ],
    },
    {
        name: 'budget',
        label: 'Budget',
        type: 'number',
        required: true,
        min: 1,
        step: 1,
        unit: 'FCFA',
        hint: 'Montant entier en FCFA, sans décimales.',
    },
    { name: 'startDate', label: 'Début', type: 'date', required: true },
    {
        name: 'endDate',
        label: 'Fin prévue',
        type: 'date',
        required: true,
        validate: (value, values) =>
            values.startDate &&
            value &&
            String(value) < String(values.startDate)
                ? 'La fin doit être postérieure au début.'
                : null,
    },
];

const initial: FieldValues = {
    name: existing.value?.name ?? '',
    client: existing.value?.clientName ?? '',
    location: existing.value?.location ?? '',
    manager: existing.value?.manager ?? '',
    agency: existing.value?.agency ?? 'Siège Abidjan',
    budget: existing.value?.budget ?? null,
    startDate: existing.value?.startDate ?? '',
    endDate: existing.value?.endDate ?? '',
};

function submit() {
    toast.success(
        'Projet enregistré (maquette : aucune donnée n’est sauvegardée).',
    );
    router.visit('/projets');
}
</script>

<template>
    <Head :title="title" />

    <div class="mx-auto w-full max-w-3xl space-y-5 p-4">
        <PageHeader
            :title="title"
            description="Un projet porte un ou plusieurs chantiers ; il démarre au statut « Étude »."
        />
        <div class="rounded-xl border bg-card p-4 shadow-xs sm:p-6">
            <SchemaForm
                :fields="fields"
                :initial="initial"
                @submit="submit"
                @cancel="router.visit('/projets')"
            />
        </div>
    </div>
</template>
