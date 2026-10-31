<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { FileText, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import ClientController from '@/actions/App/Http/Controllers/Clients/ClientController';
import ConfirmDialog from '@/components/erp/ConfirmDialog.vue';
import EmptyState from '@/components/erp/EmptyState.vue';
import FormDialog from '@/components/erp/FormDialog.vue';
import KpiCard from '@/components/erp/KpiCard.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { clientTypeLabels } from '@/lib/clients';
import { dateFr, dateTimeFr, fcfa } from '@/lib/format';
import type { ActivityEntry } from '@/types/erp';
import type { FieldDef, FieldValues } from '@/types/forms';

const props = defineProps<{
    client: {
        id: number;
        number: string;
        name: string;
        type: string;
        contact: string | null;
        phone: string | null;
        email: string | null;
        city: string | null;
        address: string | null;
    };
    summary: {
        projectsInProgress: number;
        projectsTotal: number;
        contracted: number;
        invoiced: number;
        paid: number;
        due: number;
    };
    projects: {
        id: number;
        number: string;
        name: string;
        status: string;
        budget: number;
    }[];
    contacts: {
        id: number;
        name: string;
        role: string | null;
        phone: string | null;
        email: string | null;
    }[];
    documents: { id: number; name: string; size: string; date: string }[];
    history: ActivityEntry[];
    can: { update: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Clients', href: '/clients' }] },
});

const editOpen = ref(false);
const contactOpen = ref(false);
const deleting = ref<{ kind: 'contact' | 'document'; id: number } | null>(null);

const editFields: FieldDef[] = [
    {
        name: 'name',
        label: 'Nom ou raison sociale',
        required: true,
        minLength: 3,
        wide: true,
    },
    {
        name: 'type',
        label: 'Type de client',
        type: 'select',
        required: true,
        options: Object.entries(clientTypeLabels).map(([value, label]) => ({
            value,
            label,
        })),
    },
    { name: 'city', label: 'Ville' },
    { name: 'contact', label: 'Contact principal' },
    {
        name: 'phone',
        label: 'Téléphone',
        type: 'tel',
        pattern: /^\+?[\d\s]{8,18}$/,
        patternMessage: 'Numéro invalide (ex. +225 07 00 00 00 00).',
    },
    { name: 'email', label: 'E-mail', type: 'email' },
    { name: 'address', label: 'Adresse', wide: true },
];
const editInitial = computed(() => ({
    name: props.client.name,
    type: props.client.type,
    city: props.client.city ?? '',
    contact: props.client.contact ?? '',
    phone: props.client.phone ?? '',
    email: props.client.email ?? '',
    address: props.client.address ?? '',
}));

const contactFields: FieldDef[] = [
    { name: 'name', label: 'Nom', required: true },
    { name: 'role', label: 'Fonction' },
    { name: 'phone', label: 'Téléphone', type: 'tel' },
    { name: 'email', label: 'E-mail', type: 'email' },
];

const onError = (errors: Record<string, string>) =>
    toast.error(Object.values(errors)[0] ?? 'Saisie invalide.');

function save(v: FieldValues) {
    router.put(
        ClientController.update.url(props.client.id),
        v as Record<string, string>,
        {
            preserveScroll: true,
            onError,
        },
    );
}

function addContact(v: FieldValues) {
    router.post(
        ClientController.storeContact.url(props.client.id),
        v as Record<string, string>,
        {
            preserveScroll: true,
            onError,
        },
    );
}

const upload = useForm<{ document: File | null }>({ document: null });

function onFile(event: Event) {
    upload.document = (event.target as HTMLInputElement).files?.[0] ?? null;

    if (upload.document) {
        upload.post(ClientController.storeDocument.url(props.client.id), {
            forceFormData: true,
            preserveScroll: true,
            onError,
            onSuccess: () => upload.reset(),
        });
    }
}

function confirmDelete() {
    const target = deleting.value;

    if (!target) {
        return;
    }

    const url =
        target.kind === 'contact'
            ? ClientController.destroyContact.url({
                  client: props.client.id,
                  contact: target.id,
              })
            : ClientController.destroyDocument.url({
                  client: props.client.id,
                  media: target.id,
              });

    router.delete(url, { preserveScroll: true });
}

const statusLabel: Record<string, string> = {
    etude: 'Étude',
    devis: 'Devis',
    appel_offres: 'Appel d’offres',
    marche: 'Marché',
    preparation: 'Préparation',
    en_cours: 'En cours',
    reception: 'Réception',
    cloture: 'Clôturé',
    abandonne: 'Abandonné',
};
</script>

<template>
    <Head :title="client.name" />

    <div class="space-y-5 p-4">
        <PageHeader
            :title="client.name"
            :description="`${client.number} · ${clientTypeLabels[client.type]}${client.city ? ` · ${client.city}` : ''}`"
        >
            <StatusBadge
                :label="clientTypeLabels[client.type]"
                tone="neutral"
            />
            <Button v-if="can.update" variant="outline" @click="editOpen = true"
                >Modifier la fiche</Button
            >
        </PageHeader>

        <!-- CA2 : en un écran, les projets en cours et le montant des créances. -->
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <KpiCard
                label="Projets en cours"
                :value="String(summary.projectsInProgress)"
                :hint="`${summary.projectsTotal} projet(s) au total`"
                tone="info"
            />
            <KpiCard label="Facturé" :value="fcfa(summary.invoiced)" />
            <KpiCard
                label="Encaissé"
                :value="fcfa(summary.paid)"
                tone="success"
            />
            <KpiCard
                label="Créances (solde dû)"
                :value="fcfa(summary.due)"
                :tone="summary.due > 0 ? 'warning' : 'success'"
            />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-xl border bg-card p-4 shadow-xs">
                <h2 class="mb-3 font-display font-semibold">Coordonnées</h2>
                <dl class="grid grid-cols-[8rem_1fr] gap-y-1.5 text-sm">
                    <dt class="text-muted-foreground">Contact</dt>
                    <dd>{{ client.contact || '—' }}</dd>
                    <dt class="text-muted-foreground">Téléphone</dt>
                    <dd>{{ client.phone || '—' }}</dd>
                    <dt class="text-muted-foreground">E-mail</dt>
                    <dd>{{ client.email || '—' }}</dd>
                    <dt class="text-muted-foreground">Adresse</dt>
                    <dd>{{ client.address || '—' }}</dd>
                </dl>
            </section>

            <section class="rounded-xl border bg-card p-4 shadow-xs">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="font-display font-semibold">Contacts</h2>
                    <Button
                        v-if="can.update"
                        size="sm"
                        variant="outline"
                        @click="contactOpen = true"
                        >Ajouter un contact</Button
                    >
                </div>
                <ul v-if="contacts.length" class="divide-y text-sm">
                    <li
                        v-for="c in contacts"
                        :key="c.id"
                        class="flex items-start justify-between gap-2 py-2"
                    >
                        <span>
                            <span class="font-medium">{{ c.name }}</span>
                            <span v-if="c.role" class="text-muted-foreground">
                                · {{ c.role }}</span
                            >
                            <span class="block text-xs text-muted-foreground">{{
                                [c.phone, c.email].filter(Boolean).join(' · ')
                            }}</span>
                        </span>
                        <button
                            v-if="can.update"
                            type="button"
                            class="rounded p-1 text-muted-foreground hover:text-destructive"
                            :aria-label="`Supprimer ${c.name}`"
                            @click="deleting = { kind: 'contact', id: c.id }"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </li>
                </ul>
                <EmptyState v-else compact title="Aucun autre contact" />
            </section>
        </div>

        <section class="rounded-xl border bg-card p-4 shadow-xs">
            <h2 class="mb-3 font-display font-semibold">Projets associés</h2>
            <ul v-if="projects.length" class="divide-y text-sm">
                <li
                    v-for="p in projects"
                    :key="p.id"
                    class="flex flex-wrap items-center justify-between gap-2 py-2.5"
                >
                    <span>
                        <Link
                            :href="`/projets/${p.id}`"
                            class="font-medium hover:underline"
                            >{{ p.name }}</Link
                        >
                        <span class="block text-xs text-muted-foreground">{{
                            p.number
                        }}</span>
                    </span>
                    <span class="flex items-center gap-3">
                        <span class="text-muted-foreground tabular-nums">{{
                            fcfa(p.budget)
                        }}</span>
                        <StatusBadge
                            :label="statusLabel[p.status] ?? p.status"
                            tone="neutral"
                        />
                    </span>
                </li>
            </ul>
            <EmptyState v-else compact title="Aucun projet" />
        </section>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-xl border bg-card p-4 shadow-xs">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="font-display font-semibold">Documents</h2>
                    <label
                        v-if="can.update"
                        class="inline-flex h-8 cursor-pointer items-center rounded-md border px-3 text-sm hover:bg-accent"
                    >
                        Ajouter un document
                        <input
                            type="file"
                            class="sr-only"
                            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                            @change="onFile"
                        />
                    </label>
                </div>
                <ul v-if="documents.length" class="divide-y text-sm">
                    <li
                        v-for="d in documents"
                        :key="d.id"
                        class="flex items-center justify-between gap-2 py-2"
                    >
                        <a
                            :href="
                                ClientController.document.url({
                                    client: client.id,
                                    media: d.id,
                                })
                            "
                            class="inline-flex items-center gap-2 hover:underline"
                            ><FileText class="size-4 text-muted-foreground" />
                            {{ d.name }}
                            <span class="text-xs text-muted-foreground"
                                >{{ d.size }} · {{ dateFr(d.date) }}</span
                            ></a
                        >
                        <button
                            v-if="can.update"
                            type="button"
                            class="rounded p-1 text-muted-foreground hover:text-destructive"
                            :aria-label="`Supprimer ${d.name}`"
                            @click="deleting = { kind: 'document', id: d.id }"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </li>
                </ul>
                <EmptyState v-else compact title="Aucun document" />
            </section>

            <section class="rounded-xl border bg-card p-4 shadow-xs">
                <h2 class="mb-3 font-display font-semibold">Historique</h2>
                <ul v-if="history.length" class="divide-y text-sm">
                    <li v-for="h in history" :key="h.id" class="py-2">
                        <span class="font-medium">{{ h.actor }}</span>
                        <span class="text-muted-foreground">
                            · {{ dateTimeFr(h.date) }}</span
                        >
                        <p
                            v-for="c in h.changes"
                            :key="c.field"
                            class="text-xs text-muted-foreground"
                        >
                            {{ c.field }} : {{ c.before }} → {{ c.after }}
                        </p>
                    </li>
                </ul>
                <EmptyState
                    v-else
                    compact
                    title="Aucune modification enregistrée"
                />
            </section>
        </div>

        <FormDialog
            v-model:open="editOpen"
            title="Modifier la fiche client"
            :fields="editFields"
            :initial="editInitial"
            @submit="save"
        />
        <FormDialog
            v-model:open="contactOpen"
            title="Ajouter un contact"
            :fields="contactFields"
            @submit="addContact"
        />
        <ConfirmDialog
            :open="deleting !== null"
            :title="
                deleting?.kind === 'contact'
                    ? 'Supprimer le contact'
                    : 'Supprimer le document'
            "
            confirm-label="Supprimer"
            destructive
            @update:open="(v: boolean) => (v ? null : (deleting = null))"
            @confirm="confirmDelete"
        />
    </div>
</template>
