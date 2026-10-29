<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, FileText, Paperclip, Pencil } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDialog from '@/components/erp/ConfirmDialog.vue';
import KpiCard from '@/components/erp/KpiCard.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import StatusTimeline from '@/components/erp/StatusTimeline.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { dateFr, fcfa, TODAY } from '@/lib/format';
import { statuses } from '@/lib/status';
import { budgetOf } from '@/mocks/achats';
import { chantiers, projects } from '@/mocks/chantiers';
import { clients, contracts, quotes } from '@/mocks/v3';
import type { ProjectStatus } from '@/types/erp';

const props = defineProps<{ id: number }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Projets', href: '/projets' }] },
});

const { can } = usePermissions();
const project = computed(
    () => projects.find((p) => p.id === props.id) ?? projects[0],
);
const projectChantiers = computed(() =>
    chantiers.filter((c) => c.projectId === project.value.id),
);

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

// Statut courant modifiable localement (maquette).
const status = ref<ProjectStatus>(project.value.status);
const documents = ref([...project.value.documents]);
const nextStatus = computed(() => {
    const i = journey.indexOf(status.value as (typeof journey)[number]);

    return i >= 0 && i < journey.length - 1 ? journey[i + 1] : null;
});

const abandonOpen = ref(false);
// RG4.1 : abandon possible à tout statut antérieur à « Marché ».
const canAbandon = computed(() =>
    ['etude', 'devis', 'appel_offres'].includes(status.value),
);
const readOnly = computed(
    () => status.value === 'cloture' || status.value === 'abandonne',
);

// Liens croisés : client, marché et devis du projet.
const client = computed(() =>
    clients.find((c) => c.name === project.value.clientName),
);
const contract = computed(() =>
    contracts.find((c) =>
        c.projectName.startsWith(project.value.name.split(' — ')[0]),
    ),
);
const quote = computed(() =>
    quotes.find((q) =>
        q.projectName.startsWith(project.value.name.split(' — ')[0]),
    ),
);

function advance() {
    if (!nextStatus.value) {
        return;
    }

    // RG4.2 : « En cours » exige au moins un chantier avec budget (DS) et responsable.
    if (nextStatus.value === 'en_cours') {
        const ready = projectChantiers.value.some(
            (c) => c.manager && budgetOf(c.id).length > 0,
        );

        if (!ready) {
            toast.error(
                'Impossible de passer à « En cours » : créez au moins un chantier avec un budget (déboursé sec) et un responsable.',
            );

            return;
        }
    }

    status.value = nextStatus.value;
    toast.success(
        `Projet passé au statut « ${statuses.project[status.value].label} » (maquette).`,
    );
}

function abandon(reason: string) {
    status.value = 'abandonne';
    toast.success(`Projet abandonné — motif : ${reason}`);
}

function addDocument(event: Event) {
    const files = (event.target as HTMLInputElement).files;

    for (const file of Array.from(files ?? [])) {
        if (file.size > 20 * 1024 * 1024) {
            toast.error(`${file.name} dépasse 20 Mo.`);
            continue;
        }

        documents.value.push({
            name: file.name,
            size: `${(file.size / 1024 / 1024).toFixed(1).replace('.', ',')} Mo`,
            date: TODAY,
        });
    }
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
                <Link :href="`/projets/${project.id}/modifier`"
                    ><Pencil /> Modifier</Link
                >
            </Button>
            <Button v-if="can('projets.update') && nextStatus" @click="advance">
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
            <KpiCard
                label="Chantiers"
                :value="String(projectChantiers.length)"
            />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-xl border bg-card p-4 shadow-xs">
                <h2 class="mb-3 font-display font-semibold">Informations</h2>
                <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
                    <dt class="text-muted-foreground">Client</dt>
                    <dd>
                        <Link
                            v-if="client"
                            :href="`/clients/${client.id}`"
                            class="hover:underline"
                            >{{ project.clientName }}</Link
                        >
                        <template v-else>{{ project.clientName }}</template>
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
            <ul v-if="projectChantiers.length" class="divide-y text-sm">
                <li
                    v-for="c in projectChantiers"
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
