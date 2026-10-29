<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { toast } from 'vue-sonner';
import PageHeader from '@/components/erp/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { projects } from '@/mocks/chantiers';

const props = defineProps<{ id?: number }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Projets', href: '/projets' }] },
});

const existing = computed(() => projects.find((p) => p.id === props.id));
const title = computed(() =>
    existing.value ? 'Modifier le projet' : 'Nouveau projet',
);

const form = reactive({
    name: existing.value?.name ?? '',
    client: existing.value?.clientName ?? '',
    location: existing.value?.location ?? '',
    manager: existing.value?.manager ?? '',
    agency: existing.value?.agency ?? 'Siège Abidjan',
    budget: existing.value?.budget ?? 0,
    startDate: existing.value?.startDate ?? '',
    endDate: existing.value?.endDate ?? '',
});

const managers = [...new Set(projects.map((p) => p.manager))];

function submit() {
    toast.success(
        'Projet enregistré (maquette : aucune donnée n’est sauvegardée).',
    );
    router.visit('/projets');
}

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/40';
</script>

<template>
    <Head :title="title" />

    <div class="mx-auto w-full max-w-3xl space-y-5 p-4">
        <PageHeader
            :title="title"
            description="Un projet porte un ou plusieurs chantiers."
        />

        <form
            class="space-y-5 rounded-xl border bg-card p-4 shadow-xs sm:p-6"
            @submit.prevent="submit"
        >
            <div class="grid gap-2">
                <Label for="name">Nom du projet</Label>
                <Input id="name" v-model="form.name" required />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="client">Client</Label>
                    <Input id="client" v-model="form.client" required />
                </div>
                <div class="grid gap-2">
                    <Label for="location">Localisation</Label>
                    <Input id="location" v-model="form.location" required />
                </div>
                <div class="grid gap-2">
                    <Label for="manager">Responsable</Label>
                    <select
                        id="manager"
                        v-model="form.manager"
                        :class="selectClass"
                        required
                    >
                        <option value="" disabled>Choisir…</option>
                        <option v-for="m in managers" :key="m" :value="m">
                            {{ m }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <Label for="agency">Agence</Label>
                    <select
                        id="agency"
                        v-model="form.agency"
                        :class="selectClass"
                    >
                        <option>Siège Abidjan</option>
                        <option>Agence Bouaké</option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <Label for="budget">Budget (FCFA)</Label>
                    <Input
                        id="budget"
                        v-model.number="form.budget"
                        type="number"
                        min="0"
                        step="1"
                        inputmode="numeric"
                    />
                </div>
                <div class="hidden sm:block" />
                <div class="grid gap-2">
                    <Label for="start">Début</Label>
                    <Input
                        id="start"
                        v-model="form.startDate"
                        type="date"
                        required
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="end">Fin prévue</Label>
                    <Input
                        id="end"
                        v-model="form.endDate"
                        type="date"
                        required
                    />
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button type="submit">Enregistrer</Button>
                <Button variant="ghost" as-child>
                    <Link href="/projets">Annuler</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
