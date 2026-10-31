<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Printer } from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/erp/ConfirmDialog.vue';
import PieceForm from '@/components/erp/PieceForm.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { eventLabel, pieceTone } from '@/lib/pieces';
import type {
    PieceDetail,
    PieceKindInfo,
    ProjectTree,
    Rubrique,
} from '@/types/pieces';

/**
 * Détail d'une pièce : saisie (modifiable tant qu'elle n'est pas soumise), boutons du circuit
 * (soumettre, valider à mon étape, rejeter, supprimer) et historique de validation.
 */
const props = defineProps<{
    kind: PieceKindInfo;
    piece: PieceDetail;
    rubriques: Rubrique[];
    projects: ProjectTree[];
}>();

const base = computed(() => `/pieces/${props.kind.value}`);
const rejectOpen = ref(false);
const deleteOpen = ref(false);

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Notes de frais', href: '/pieces/note_frais' }],
    },
});

const editable = computed(() => props.piece.can.update);

function print() {
    window.print();
}

function post(action: string, data: Record<string, string> = {}) {
    router.post(`${base.value}/${props.piece.id}/${action}`, data, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`${kind.label} ${piece.number}`" />

    <div class="space-y-4 p-4" data-test="piece-show">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1
                    class="font-display text-xl font-semibold tracking-tight sm:text-2xl"
                >
                    {{ kind.label }} {{ piece.number }}
                </h1>
                <p class="mt-0.5 text-sm text-muted-foreground">
                    {{ piece.title }} · saisie par {{ piece.requester }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <StatusBadge
                    :label="piece.status_label"
                    :tone="pieceTone[piece.status]"
                />
                <span
                    v-if="piece.waiting"
                    class="text-sm text-muted-foreground"
                    >{{ piece.waiting }}</span
                >
            </div>
        </div>

        <div
            class="flex flex-wrap items-center gap-2"
            data-test="piece-actions"
        >
            <Button
                v-if="piece.can.update"
                data-test="submit-piece"
                @click="post('soumettre')"
                >Valider (soumettre)</Button
            >
            <Button
                v-if="piece.can.decide"
                variant="secondary"
                data-test="advance-piece"
                @click="post('valider')"
                >{{ piece.can.button ?? 'Valider' }}</Button
            >
            <Button
                v-if="piece.can.decide"
                variant="outline"
                @click="rejectOpen = true"
                >Rejeter</Button
            >
            <Button
                v-if="piece.can.update"
                variant="outline"
                @click="deleteOpen = true"
                >Supprimer</Button
            >
            <Button variant="outline" @click="print()"
                ><Printer /> Imprimer</Button
            >
            <span
                v-if="piece.invoice_number"
                class="text-sm text-muted-foreground"
                >Facture {{ piece.invoice_number }} · remboursée le
                {{ piece.reimbursed_at }}</span
            >
            <Button
                v-else-if="piece.can.reimburse"
                variant="secondary"
                @click="post('rembourser')"
                >Rembourser</Button
            >
        </div>

        <PieceForm
            :kind="kind"
            :projects="projects"
            :rubriques="rubriques"
            :piece="piece"
            :readonly="!editable"
        />

        <section class="rounded-xl border bg-card p-4 shadow-xs">
            <h2 class="font-display text-base font-semibold">
                Historique de validation
            </h2>
            <ol class="mt-3 space-y-3 border-l pl-4">
                <li
                    v-for="(e, i) in piece.events"
                    :key="i"
                    class="relative text-sm"
                >
                    <span
                        class="absolute top-1.5 -left-[1.35rem] size-2.5 rounded-full bg-brand"
                    />
                    <p class="font-medium">
                        {{ eventLabel[e.action] ?? e.action
                        }}<span v-if="e.step" class="font-normal">
                            — {{ e.step }}</span
                        >
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ e.at }} · {{ e.by }}
                    </p>
                    <p v-if="e.comment" class="mt-0.5">{{ e.comment }}</p>
                </li>
            </ol>
        </section>

        <ConfirmDialog
            v-model:open="rejectOpen"
            title="Rejeter la pièce"
            description="L’auteur pourra la corriger et la soumettre à nouveau."
            confirm-label="Rejeter"
            require-reason
            destructive
            @confirm="(reason) => post('rejeter', { comment: reason })"
        />
        <ConfirmDialog
            v-model:open="deleteOpen"
            title="Supprimer la pièce"
            confirm-label="Supprimer"
            destructive
            @confirm="
                router.delete(`${base}/${piece.id}`, { preserveScroll: false })
            "
        />
    </div>
</template>
