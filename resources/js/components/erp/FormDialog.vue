<script setup lang="ts">
import SchemaForm from '@/components/erp/SchemaForm.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { FieldDef, FieldValues } from '@/types/forms';

/** Formulaire en fenêtre : création rapide depuis une liste. */
defineProps<{
    title: string;
    description?: string;
    fields: FieldDef[];
    initial?: FieldValues;
    submitLabel?: string;
}>();

const open = defineModel<boolean>('open', { default: false });
const emit = defineEmits<{ submit: [values: FieldValues] }>();

function onSubmit(values: FieldValues) {
    emit('submit', values);
    open.value = false;
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription v-if="description">{{
                    description
                }}</DialogDescription>
            </DialogHeader>
            <!-- key : le formulaire est recréé (valeurs vides) à chaque ouverture -->
            <SchemaForm
                v-if="open"
                :fields="fields"
                :initial="initial"
                :submit-label="submitLabel"
                @submit="onSubmit"
                @cancel="open = false"
            />
        </DialogContent>
    </Dialog>
</template>
