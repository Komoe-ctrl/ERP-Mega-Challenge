import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import { nextTick } from 'vue';
import ConfirmDialog from '@/components/erp/ConfirmDialog.vue';

// Le dialogue est téléporté dans <body> : on interroge le document.
const buttonByText = (text: string) =>
    [...document.body.querySelectorAll('button')].find(
        (b) => b.textContent?.trim() === text,
    ) as HTMLButtonElement;

function open(props: Record<string, unknown> = {}) {
    return mount(ConfirmDialog, {
        props: {
            open: true,
            title: 'Refuser la demande',
            requireReason: true,
            ...props,
        },
        attachTo: document.body,
    });
}

afterEach(() => {
    document.body.innerHTML = '';
});

describe('ConfirmDialog (motif obligatoire)', () => {
    it('affiche le titre et le champ « motif » quand il est requis', async () => {
        const wrapper = open();
        await nextTick();

        expect(document.body.textContent).toContain('Refuser la demande');
        expect(document.body.querySelector('textarea')).not.toBeNull();
        expect(document.body.textContent).toContain('Motif (obligatoire)');
        wrapper.unmount();
    });

    it('refuse de confirmer sans motif et affiche une erreur en français', async () => {
        const wrapper = open();
        await nextTick();

        buttonByText('Confirmer').click();
        await nextTick();

        expect(wrapper.emitted('confirm')).toBeUndefined();
        expect(document.body.textContent).toContain(
            'Le motif est obligatoire.',
        );
        wrapper.unmount();
    });

    it('refuse un motif composé uniquement d’espaces', async () => {
        const wrapper = open();
        await nextTick();

        const textarea = document.body.querySelector(
            'textarea',
        ) as HTMLTextAreaElement;
        textarea.value = '    ';
        textarea.dispatchEvent(new Event('input'));
        buttonByText('Confirmer').click();
        await nextTick();

        expect(wrapper.emitted('confirm')).toBeUndefined();
        wrapper.unmount();
    });

    it('confirme avec le motif saisi (espaces superflus retirés)', async () => {
        const wrapper = open();
        await nextTick();

        const textarea = document.body.querySelector(
            'textarea',
        ) as HTMLTextAreaElement;
        textarea.value = '  Quantités à revoir  ';
        textarea.dispatchEvent(new Event('input'));
        buttonByText('Confirmer').click();
        await nextTick();

        expect(wrapper.emitted('confirm')).toEqual([['Quantités à revoir']]);
        expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false]);
        wrapper.unmount();
    });

    it('sans motif requis, confirme directement', async () => {
        const wrapper = open({ requireReason: false, title: 'Valider' });
        await nextTick();

        expect(document.body.querySelector('textarea')).toBeNull();
        buttonByText('Confirmer').click();
        await nextTick();

        expect(wrapper.emitted('confirm')).toEqual([['']]);
        wrapper.unmount();
    });

    it('annuler ferme le dialogue sans confirmer', async () => {
        const wrapper = open();
        await nextTick();

        buttonByText('Annuler').click();
        await nextTick();

        expect(wrapper.emitted('confirm')).toBeUndefined();
        expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false]);
        wrapper.unmount();
    });
});
