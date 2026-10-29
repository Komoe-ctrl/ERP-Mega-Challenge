import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { nextTick } from 'vue';
import SchemaForm from '@/components/erp/SchemaForm.vue';
import type { FieldDef } from '@/types/forms';

const fields: FieldDef[] = [
    { name: 'name', label: 'Nom', required: true, minLength: 3 },
    { name: 'budget', label: 'Budget', type: 'number', required: true, min: 1 },
    {
        name: 'kind',
        label: 'Type',
        type: 'select',
        required: true,
        options: [{ value: 'a', label: 'A' }],
    },
];

describe('SchemaForm', () => {
    it('affiche un libellé par champ, avec une étoile pour les champs obligatoires', () => {
        const wrapper = mount(SchemaForm, { props: { fields } });

        expect(wrapper.findAll('label')).toHaveLength(3);
        expect(wrapper.text()).toContain('Nom');
        expect(wrapper.findAll('label span[aria-hidden="true"]')).toHaveLength(
            3,
        );
    });

    it('bloque l’envoi et affiche les erreurs en français quand le formulaire est invalide', async () => {
        const wrapper = mount(SchemaForm, { props: { fields } });

        await wrapper.find('form').trigger('submit');

        expect(wrapper.emitted('submit')).toBeUndefined();
        expect(wrapper.findAll('[role="alert"]').length).toBeGreaterThanOrEqual(
            3,
        );
        expect(wrapper.text()).toContain('Ce champ est obligatoire.');
        expect(wrapper.text()).toContain('champ(s) à corriger');
    });

    it('signale une valeur trop courte après la perte de focus', async () => {
        const wrapper = mount(SchemaForm, { props: { fields } });

        const input = wrapper.find('#f-name');
        await input.setValue('ab');
        await input.trigger('blur');
        await nextTick();

        expect(wrapper.text()).toContain('Au moins 3 caractères.');
    });

    it('émet les valeurs quand tout est valide', async () => {
        const wrapper = mount(SchemaForm, { props: { fields } });

        await wrapper.find('#f-name').setValue('Palmiers');
        await wrapper.find('#f-budget').setValue('1500000');
        await wrapper.find('#f-kind').setValue('a');
        await wrapper.find('form').trigger('submit');

        const [values] = wrapper.emitted('submit')![0] as [
            Record<string, unknown>,
        ];

        expect(values.name).toBe('Palmiers');
        expect(Number(values.budget)).toBe(1_500_000);
        expect(values.kind).toBe('a');
    });

    it('reprend les valeurs initiales', () => {
        const wrapper = mount(SchemaForm, {
            props: { fields, initial: { name: 'Existant' } },
        });

        expect(
            (wrapper.find('#f-name').element as HTMLInputElement).value,
        ).toBe('Existant');
    });

    it('le bouton Annuler émet cancel', async () => {
        const wrapper = mount(SchemaForm, { props: { fields } });

        await wrapper
            .findAll('button')
            .find((b) => b.text() === 'Annuler')!
            .trigger('click');

        expect(wrapper.emitted('cancel')).toHaveLength(1);
    });
});
