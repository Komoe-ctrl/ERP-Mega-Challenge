<script setup lang="ts">
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

/**
 * Dialogue de confirmation. Avec `requireReason`, le motif est obligatoire
 * (refus ou renvoi d'une demande : RG8.5 ; abandon d'un projet : RG4.1).
 */
const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        confirmLabel?: string;
        requireReason?: boolean;
        reasonLabel?: string;
        destructive?: boolean;
    }>(),
    {
        confirmLabel: 'Confirmer',
        requireReason: false,
        reasonLabel: 'Motif',
        destructive: false,
    },
);

const open = defineModel<boolean>('open', { default: false });
const emit = defineEmits<{ confirm: [reason: string] }>();

const reason = ref('');
const touched = ref(false);

watch(open, (value) => {
    if (value) {
        reason.value = '';
        touched.value = false;
    }
});

function submit() {
    touched.value = true;

    if (props.requireReason && reason.value.trim() === '') {
        return;
    }

    emit('confirm', reason.value.trim());
    open.value = false;
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription v-if="description">
                    {{ description }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="requireReason" class="grid gap-2">
                <Label for="confirm-reason"
                    >{{ reasonLabel }} (obligatoire)</Label
                >
                <textarea
                    id="confirm-reason"
                    v-model="reason"
                    rows="3"
                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/40"
                    :aria-invalid="touched && reason.trim() === ''"
                />
                <p
                    v-if="touched && reason.trim() === ''"
                    class="text-sm text-destructive"
                >
                    Le motif est obligatoire.
                </p>
            </div>

            <DialogFooter class="gap-2">
                <Button variant="outline" @click="open = false">Annuler</Button>
                <Button
                    :variant="destructive ? 'destructive' : 'default'"
                    @click="submit"
                >
                    {{ confirmLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
