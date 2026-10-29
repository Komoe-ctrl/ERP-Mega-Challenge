<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { FileText, Pencil } from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/erp/ConfirmDialog.vue';
import KpiCard from '@/components/erp/KpiCard.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import StatusTimeline from '@/components/erp/StatusTimeline.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { dateFr, fcfa } from '@/lib/format';
import { statuses } from '@/lib/status';
import { chantiers, projects } from '@/mocks/chantiers';
import { toast } from 'vue-sonner';

const props = defineProps<{ id: number }>();

const { can } = usePermissions();
const project = computed(
    () => projects.find((p) => p.id === props.id) ?? projects[0],
);
const projectChantiers = computed(() =>
    chantiers.filter((c) => c.projectId === project.value.id),
);

defineOptions({
    layout: { breadcrumbs: [{ title: 'Projets', href: '/projets' }] },
});

// Parcours du projet (cahier des charges, M4).
const steps = (
    [
        'etude',
        'devis',
        'appel_offres',
        'marche',
        'preparation',
        'en_cours',
        'reception',
        'cloture',
    ] as const
).map((key) => ({ key, label: statuses.project[key].label }));

const abandonOpen = ref(false);
// RG4.1 : abandon possible à tout statut antérieur à « Marché ».
const canAbandon = computed(() =>
    ['etude', 'devis', 'appel_offres'].includes(project.value.status),
);

function abandon(reason: string) {
    toast.success(`Projet abandonné (maquette) — motif : ${reason}`);
}
</script>

<template>
    <Head :title="project.name" />

    <div class="space-y-5 p-4">
        <PageHeader
            :title="project.name"
            :description="`${project.number} · ${project.location}`"
        >
            <StatusBadge kind="project" :status="project.status" />
            <Button v-if="can('projets.update')" variant="outline" as-child>
                <Link :href="`/projets/${project.id}/modifier`"
                    ><Pencil /> Modifier</Link
                >
            </Button>
            <Button
                v-if="can('projets.update') && canAbandon"
                variant="outline"
                class="text-destructive"
                @click="abandonOpen = true"
            >
                Abandonner
            </Button>
        </PageHeader>

        <section class="rounded-xl border bg-card p-4 shadow-xs">
            <h2 class="mb-4 font-display font-semibold">
                Avancement du dossier
            </h2>
            <StatusTimeline
                :steps="steps"
                :current="
                    project.status === 'abandonne' ? 'etude' : project.status
                "
                :aborted="project.status === 'abandonne'"
            />
            <p
                v-if="project.status === 'abandonne'"
                class="mt-3 rounded-lg bg-danger-soft p-3 text-sm text-danger"
            >
                Abandonné — {{ project.abandonReason }}
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
                    <dd>{{ project.clientName }}</dd>
                    <dt class="text-muted-foreground">Responsable</dt>
                    <dd>{{ project.manager }}</dd>
                    <dt class="text-muted-foreground">Agence</dt>
                    <dd>{{ project.agency }}</dd>
                    <dt class="text-muted-foreground">Localisation</dt>
                    <dd>{{ project.location }}</dd>
                </dl>
            </section>

            <section class="rounded-xl border bg-card p-4 shadow-xs">
                <h2 class="mb-3 font-display font-semibold">Documents</h2>
                <ul v-if="project.documents.length" class="divide-y text-sm">
                    <li
                        v-for="doc in project.documents"
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
                    Aucun document.
                </p>
            </section>
        </div>

        <section class="rounded-xl border bg-card p-4 shadow-xs">
            <h2 class="mb-3 font-display font-semibold">Chantiers du projet</h2>
            <ul v-if="projectChantiers.length" class="divide-y text-sm">
                <li
                    v-for="c in projectChantiers"
                    :key="c.id"
                    class="flex flex-wrap items-center justify-between gap-2 py-2.5"
                >
                    <Link
                        :href="`/chantiers/${c.id}`"
                        class="font-medium hover:underline"
                    >
                        {{ c.code }} — {{ c.name }}
                    </Link>
                    <StatusBadge kind="site" :status="c.status" />
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">
                Aucun chantier. Le passage à « En cours » exige au moins un
                chantier avec budget et responsable (RG4.2).
            </p>
        </section>

        <ConfirmDialog
            v-model:open="abandonOpen"
            title="Abandonner le projet"
            description="Le projet sera marqué comme perdu ou annulé."
            confirm-label="Abandonner"
            require-reason
            destructive
            @confirm="abandon"
        />
    </div>
</template>
