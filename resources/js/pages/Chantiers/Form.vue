<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import ChantierController from '@/actions/App/Http/Controllers/Chantiers/ChantierController';
import PageHeader from '@/components/erp/PageHeader.vue';
import SchemaForm from '@/components/erp/SchemaForm.vue';
import { statuses } from '@/lib/status';
import type { Chantier } from '@/types/erp';
import type { FieldDef, FieldValues } from '@/types/forms';

type Option = { value: string; label: string };

const props = defineProps<{
    chantier: (Chantier & { conducteurId: number; chefId: number }) | null;
    presetProject: number | null;
    projects: Option[];
    conducteurs: Option[];
    chefs: Option[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Chantiers', href: '/chantiers' }] },
});

const title = props.chantier ? 'Modifier le chantier' : 'Nouveau chantier';

const fields: FieldDef[] = [
    {
        name: 'code',
        label: 'Code',
        required: true,
        pattern: /^CH-\d{3,4}$/,
        patternMessage: 'Format attendu : CH-001.',
        hint: 'Code unique, au format CH-001.',
    },
    { name: 'name', label: 'Nom du chantier', required: true, minLength: 3 },
    // Le projet se choisit à la création et ne change plus ensuite.
    ...(props.chantier
        ? []
        : [
              {
                  name: 'project_id',
                  label: 'Projet',
                  type: 'select',
                  required: true,
                  options: props.projects,
                  wide: true,
              } satisfies FieldDef,
          ]),
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
    { name: 'start_date', label: 'Début', type: 'date', required: true },
    {
        name: 'end_date',
        label: 'Fin prévue',
        type: 'date',
        required: true,
        validate: (value, values) =>
            values.start_date &&
            value &&
            String(value) < String(values.start_date)
                ? 'La fin doit être postérieure au début.'
                : null,
    },
    {
        name: 'conducteur_id',
        label: 'Conducteur de travaux',
        type: 'select',
        required: true,
        options: props.conducteurs,
    },
    {
        name: 'chef_id',
        label: 'Chef de chantier',
        type: 'select',
        required: true,
        options: props.chefs,
        hint: 'Le budget (déboursé sec) se détaille ensuite par poste dans le module Budget.',
    },
];

const initial: FieldValues = {
    code: props.chantier?.code ?? '',
    name: props.chantier?.name ?? '',
    project_id: props.presetProject ? String(props.presetProject) : '',
    location: props.chantier?.location ?? '',
    status: props.chantier?.status ?? 'preparation',
    start_date: props.chantier?.startDate ?? '',
    end_date: props.chantier?.endDate ?? '',
    conducteur_id: props.chantier ? String(props.chantier.conducteurId) : '',
    chef_id: props.chantier ? String(props.chantier.chefId) : '',
};

// Le serveur refait toute la validation ; ses messages reviennent en notification.
function submit(values: FieldValues) {
    const options = {
        onError: (errors: Record<string, string>) =>
            toast.error(Object.values(errors)[0]),
    };

    if (props.chantier) {
        router.put(
            ChantierController.update.url(props.chantier.id),
            values as never,
            options,
        );
    } else {
        router.post(ChantierController.store.url(), values as never, options);
    }
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
                @cancel="router.visit(ChantierController.index.url())"
            />
        </div>
    </div>
</template>
