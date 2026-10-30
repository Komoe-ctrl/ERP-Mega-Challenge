<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, FileText, Paperclip, Pencil } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import ProjectController from '@/actions/App/Http/Controllers/Projets/ProjectController';
import ConfirmDialog from '@/components/erp/ConfirmDialog.vue';
import KpiCard from '@/components/erp/KpiCard.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import StatusTimeline from '@/components/erp/StatusTimeline.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { dateFr, fcfa } from '@/lib/format';
import { statuses } from '@/lib/status';
import type { Project, ProjectStatus, SiteStatus } from '@/types/erp';

const props = defineProps<{
    project: Project;
    chantiers: { id: number; code: string; name: string; status: SiteStatus }[];
    nextStatus: ProjectStatus | null;
    canAbandon: boolean;
    quote: { id: number; number: string; version: number } | null;
    contract: { id: number; number: string } | null;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Projets', href: '/projets' }] },
});

const { can } = usePermissions();

// Parcours du projet (cahier des charges, M4).
const journey = [
    'etude',
    'devis',
    'appel_offres',
    'marche',
    'preparation',
    'en_cours',
    'reception',
    'cloture',
] as const;
const steps = journey.map((key) => ({
    key,
    label: statuses.project[key].label,
}));

const status = computed(() => props.project.status);
const documents = computed(() => props.project.documents ?? []);
const abandonOpen = ref(false);
// RG4.3 : un projet clôturé ou abandonné est en lecture seule.
const readOnly = computed(
    () => status.value === 'cloture' || status.value === 'abandonne',
);

// Toutes les règles (RG4.1 à RG4.3) sont appliquées par le serveur ; ses refus
// reviennent en notification.
const onError = (errors: Record<string, string>) =>
    toast.error(Object.values(errors)[0]);

function advance() {
    router.post(
        ProjectController.advance.url(props.project.id),
        {},
        { preserveScroll: true, onError },
    );
}

function abandon(reason: string) {
    router.post(
        ProjectController.abandon.url(props.project.id),
        { reason },
        { preserveScroll: true, onError },
    );
}

function addDocument(event: Event) {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []);

    if (files.length) {
        router.post(
            ProjectController.storeDocument.url(props.project.id),
            { files },
            { preserveScroll: true, forceFormData: true, onError },
        );
    }

    input.value = '';
}
</script>

<template>
    <Head :title="project.name" />

    <div class="space-y-5 p-4">
        <PageHeader
            :title="project.name"
            :description="`${project.number} · ${project.location}`"
        >
            <StatusBadge kind="project" :status="status" />
            <Button
                v-if="can('projets.update') && !readOnly"
                variant="outline"
                as-child
            >
                <Link :href="ProjectController.edit.url(project.id)"
                    ><Pencil /> Modifier</Link
                >
            </Button>
            <Button
                v-if="can('projets.update') && nextStatus && !readOnly"
                @click="advance"
            >
                <ArrowRight /> Passer à «
                {{ statuses.project[nextStatus].label }} »
            </Button>
            <Button
                v-if="can('projets.update') && canAbandon"
                variant="outline"
                class="text-destructive"
                @click="abandonOpen = true"
                >Abandonner</Button
            >
        </PageHeader>

        <p
            v-if="readOnly"
            class="rounded-lg bg-muted p-3 text-sm text-muted-foreground"
        >
            Projet {{ status === 'cloture' ? 'clôturé' : 'abandonné' }} :
            lecture seule, y compris ses chantiers (RG4.3).
        </p>

        <section class="rounded-xl border bg-card p-4 shadow-xs">
            <h2 class="mb-4 font-display font-semibold">
                Avancement du dossier
            </h2>
            <StatusTimeline
                :steps="steps"
                :current="status === 'abandonne' ? 'etude' : status"
                :aborted="status === 'abandonne'"
            />
            <p
                v-if="status === 'abandonne'"
                class="mt-3 rounded-lg bg-danger-soft p-3 text-sm text-danger"
            >
                Abandonné —
                {{ project.abandonReason ?? 'motif renseigné à l’abandon.' }}
            </p>
        </section>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <KpiCard label="Budget" :value="fcfa(project.budget)" />
            <KpiCard label="Début" :value="dateFr(project.startDate)" />
            <KpiCard label="Fin prévue" :value="dateFr(project.endDate)" />
            <KpiCard label="Chantiers" :value="String(chantiers.length)" />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-xl border bg-card p-4 shadow-xs">
                <h2 class="mb-3 font-display font-semibold">Informations</h2>
                <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
                    <dt class="text-muted-foreground">Client</dt>
                    <dd>
                        <Link
                            v-if="project.clientId"
                            :href="`/clients/${project.clientId}`"
                            class="hover:underline"
                            >{{ project.clientName }}</Link
                        >
                    </dd>
                    <dt class="text-muted-foreground">Responsable</dt>
                    <dd>{{ project.manager }}</dd>
                    <dt class="text-muted-foreground">Agence</dt>
                    <dd>{{ project.agency }}</dd>
                    <dt class="text-muted-foreground">Localisation</dt>
                    <dd>{{ project.location }}</dd>
                    <dt class="text-muted-foreground">Devis</dt>
                    <dd>
                        <Link
                            v-if="quote"
                            :href="`/devis/${quote.id}`"
                            class="hover:underline"
                            >{{ quote.number }} (v{{ quote.version }})</Link
                        >
                        <template v-else>—</template>
                    </dd>
                    <dt class="text-muted-foreground">Marché</dt>
                    <dd>
                        <Link
                            v-if="contract"
                            :href="`/marches/${contract.id}`"
                            class="hover:underline"
                            >{{ contract.number }}</Link
                        >
                        <template v-else>—</template>
                    </dd>
                </dl>
            </section>

            <section class="rounded-xl border bg-card p-4 shadow-xs">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="font-display font-semibold">Documents</h2>
                    <label
                        v-if="can('projets.update') && !readOnly"
                        class="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-md border px-3 text-sm hover:bg-accent"
                    >
                        <Paperclip class="size-4" /> Joindre
                        <input
                            type="file"
                            multiple
                            class="sr-only"
                            @change="addDocument"
                        />
                    </label>
                </div>
                <ul v-if="documents.length" class="divide-y text-sm">
                    <li
                        v-for="doc in documents"
                        :key="doc.name"
                        class="flex items-center gap-3 py-2"
                    >
                        <FileText class="size-4 text-muted-foreground" />
                        <span class="min-w-0 flex-1 truncate">{{
                            doc.name
                        }}</span>
                        <span class="text-xs text-muted-foreground"
                            >{{ doc.size }} · {{ dateFr(doc.date) }}</span
                        >
                    </li>
                </ul>
                <p v-else class="text-sm text-muted-foreground">
                    Aucun document. 20 Mo maximum par fichier.
                </p>
            </section>
        </div>

        <section class="rounded-xl border bg-card p-4 shadow-xs">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 class="font-display font-semibold">Chantiers du projet</h2>
                <Button
                    v-if="can('chantiers.create') && !readOnly"
                    size="sm"
                    variant="outline"
                    as-child
                >
                    <Link :href="`/chantiers/creer?projet=${project.id}`"
                        >Créer un chantier</Link
                    >
                </Button>
            </div>
            <ul v-if="chantiers.length" class="divide-y text-sm">
                <li
                    v-for="c in chantiers"
                    :key="c.id"
                    class="flex flex-wrap items-center justify-between gap-2 py-2.5"
                >
                    <Link
                        :href="`/chantiers/${c.id}`"
                        class="font-medium hover:underline"
                        >{{ c.code }} — {{ c.name }}</Link
                    >
                    <StatusBadge kind="site" :status="c.status" />
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">
                Aucun chantier. Le passage à « En cours » exige au moins un
                chantier avec un budget et un responsable (RG4.2).
            </p>
        </section>

        <ConfirmDialog
            v-model:open="abandonOpen"
            title="Abandonner le projet"
            description="Le projet sera marqué comme perdu ou annulé. Cette action est définitive."
            confirm-label="Abandonner"
            require-reason
            destructive
            @confirm="abandon"
        />
    </div>
</template>
