<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import PageHeader from '@/components/erp/PageHeader.vue';
import SchemaForm from '@/components/erp/SchemaForm.vue';
import { statuses } from '@/lib/status';
import { chantiers, projects } from '@/mocks/chantiers';
import type { FieldDef, FieldValues } from '@/types/forms';

const props = defineProps<{ id?: number }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Chantiers', href: '/chantiers' }] },
});

const page = usePage();
const existing = computed(() => chantiers.find((c) => c.id === props.id));
const title = computed(() =>
    existing.value ? 'Modifier le chantier' : 'Nouveau chantier',
);

// Création depuis la fiche d'un projet : /chantiers/creer?projet=1
const presetProject =
    Number(new URLSearchParams(page.url.split('?')[1] ?? '').get('projet')) ||
    null;

const people = (role: string) => [
    ...new Set(
        chantiers.map((c) => (role === 'manager' ? c.manager : c.foreman)),
    ),
];

const fields: FieldDef[] = [
    {
        name: 'code',
        label: 'Code',
        required: true,
        pattern: /^CH-\d{3}$/,
        patternMessage: 'Format attendu : CH-001.',
        hint: 'Code unique, au format CH-001.',
        validate: (value) =>
            chantiers.some(
                (c) => c.code === value && c.id !== existing.value?.id,
            )
                ? 'Ce code est déjà utilisé (RG7.1).'
                : null,
    },
    { name: 'name', label: 'Nom du chantier', required: true, minLength: 3 },
    {
        name: 'projectId',
        label: 'Projet',
        type: 'select',
        required: true,
        options: projects
            .filter((p) => p.status !== 'abandonne' && p.status !== 'cloture')
            .map((p) => ({ value: p.id, label: p.name })),
        wide: true,
    },
    { name: 'location', label: 'Localisation', required: true },
    {
        name: 'status',
        label: 'Statut',
        type: 'select',
        required: true,
        options: Object.entries(statuses.site).map(([value, m]) => ({
            value,
            label: m.label,
        })),
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
    {
        name: 'manager',
        label: 'Conducteur de travaux',
        type: 'select',
        required: true,
        options: people('manager').map((m) => ({ value: m, label: m })),
    },
    {
        name: 'foreman',
        label: 'Chef de chantier',
        type: 'select',
        required: true,
        options: people('foreman').map((m) => ({ value: m, label: m })),
    },
    {
        name: 'budget',
        label: 'Budget initial (déboursé sec)',
        type: 'number',
        min: 0,
        step: 1,
        unit: 'FCFA',
        hint: 'Détaillé ensuite par poste dans le module Budget.',
    },
];

const initial: FieldValues = {
    code: existing.value?.code ?? '',
    name: existing.value?.name ?? '',
    projectId: existing.value?.projectId ?? presetProject ?? '',
    location: existing.value?.location ?? '',
    status: existing.value?.status ?? 'preparation',
    startDate: existing.value?.startDate ?? '',
    endDate: existing.value?.endDate ?? '',
    manager: existing.value?.manager ?? '',
    foreman: existing.value?.foreman ?? '',
    budget: null,
};

function submit() {
    toast.success(
        'Chantier enregistré (maquette : aucune donnée n’est sauvegardée).',
    );
    router.visit('/chantiers');
}
</script>

<template>
    <Head :title="title" />

    <div class="mx-auto w-full max-w-3xl space-y-5 p-4">
        <PageHeader
            :title="title"
            description="Un conducteur de travaux et un chef de chantier y sont affectés ; ils ne voient que leurs chantiers (RG7.2)."
        />
        <div class="rounded-xl border bg-card p-4 shadow-xs sm:p-6">
            <SchemaForm
                :fields="fields"
                :initial="initial"
                @submit="submit"
                @cancel="router.visit('/chantiers')"
            />
        </div>
    </div>
</template>
