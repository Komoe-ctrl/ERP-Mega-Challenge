<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import ProjectController from '@/actions/App/Http/Controllers/Projets/ProjectController';
import PageHeader from '@/components/erp/PageHeader.vue';
import SchemaForm from '@/components/erp/SchemaForm.vue';
import type { Project } from '@/types/erp';
import type { FieldDef, FieldValues } from '@/types/forms';

type Option = { value: string; label: string };

const props = defineProps<{
    project: Project | null;
    clients: Option[];
    managers: Option[];
    agencies: Option[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Projets', href: '/projets' }] },
});

const title = props.project ? 'Modifier le projet' : 'Nouveau projet';

const fields: FieldDef[] = [
    {
        name: 'name',
        label: 'Nom du projet',
        required: true,
        minLength: 5,
        wide: true,
    },
    {
        name: 'client_id',
        label: 'Client',
        type: 'select',
        required: true,
        options: props.clients,
    },
    { name: 'location', label: 'Localisation', required: true },
    {
        name: 'manager_id',
        label: 'Responsable',
        type: 'select',
        required: true,
        options: props.managers,
    },
    {
        name: 'agency_id',
        label: 'Agence',
        type: 'select',
        required: true,
        options: props.agencies,
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
];

const initial: FieldValues = {
    name: props.project?.name ?? '',
    client_id: props.project ? String(props.project.clientId) : '',
    location: props.project?.location ?? '',
    manager_id: props.project ? String(props.project.managerId) : '',
    agency_id: props.project ? String(props.project.agencyId) : '',
    budget: props.project?.budget ?? null,
    start_date: props.project?.startDate ?? '',
    end_date: props.project?.endDate ?? '',
};

// Le serveur refait toute la validation ; ses messages reviennent en notification.
function submit(values: FieldValues) {
    const options = {
        onError: (errors: Record<string, string>) =>
            toast.error(Object.values(errors)[0]),
    };

    if (props.project) {
        router.put(
            ProjectController.update.url(props.project.id),
            values as never,
            options,
        );
    } else {
        router.post(ProjectController.store.url(), values as never, options);
    }
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
                @cancel="router.visit(ProjectController.index.url())"
            />
        </div>
    </div>
</template>
