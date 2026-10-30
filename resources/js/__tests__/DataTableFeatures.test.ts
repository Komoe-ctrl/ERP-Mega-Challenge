import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { nextTick } from 'vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/lib/dataTable';

type Row = { id: number; name: string; status: string; amount: number };

const data: Row[] = Array.from({ length: 25 }, (_, i) => ({
    id: i + 1,
    name: `Projet ${String.fromCharCode(90 - i)}`,
    status: i % 2 ? 'actif' : 'clos',
    amount: (i + 1) * 1000,
}));

const columns: DataTableColumn<Row>[] = [
    { accessorKey: 'name', header: 'Nom' },
    { accessorKey: 'status', header: 'Statut', filterFn: 'equalsString' },
    { accessorKey: 'amount', header: 'Montant' },
];

const firstCell = (wrapper: ReturnType<typeof mount>) =>
    wrapper.find('tbody tr td').text();

describe('DataTable : tri, recherche, filtres et pagination', () => {
    it('pagine par 10 lignes et indique la plage affichée', () => {
        const wrapper = mount(DataTable<Row>, { props: { columns, data } });

        expect(wrapper.findAll('tbody tr')).toHaveLength(10);
        expect(wrapper.text()).toContain('1–10 sur 25');
        expect(wrapper.text()).toContain('Page 1 / 3');
    });

    it('passe à la page suivante', async () => {
        const wrapper = mount(DataTable<Row>, { props: { columns, data } });

        await wrapper
            .findAll('button')
            .find((b) => b.text() === 'Suivant')!
            .trigger('click');

        expect(wrapper.text()).toContain('11–20 sur 25');
    });

    it('désactive « Précédent » sur la première page', () => {
        const wrapper = mount(DataTable<Row>, { props: { columns, data } });

        expect(
            wrapper
                .findAll('button')
                .find((b) => b.text() === 'Précédent')!
                .attributes('disabled'),
        ).toBeDefined();
    });

    it('trie au clic sur l’en-tête (croissant puis décroissant)', async () => {
        const wrapper = mount(DataTable<Row>, { props: { columns, data } });
        const header = wrapper
            .findAll('thead button')
            .find((b) => b.text() === 'Nom')!;

        await header.trigger('click');
        expect(firstCell(wrapper)).toBe('Projet B');

        await header.trigger('click');
        expect(firstCell(wrapper)).toBe('Projet Z');
    });

    it('recherche dans toutes les colonnes texte', async () => {
        const wrapper = mount(DataTable<Row>, { props: { columns, data } });

        await wrapper.find('input[type="search"]').setValue('Projet Z');
        await nextTick();

        expect(wrapper.findAll('tbody tr')).toHaveLength(1);
        expect(wrapper.text()).toContain('1–1 sur 1');
    });

    it('filtre par colonne via la liste déroulante', async () => {
        const wrapper = mount(DataTable<Row>, {
            props: {
                columns,
                data,
                filters: [
                    {
                        columnId: 'status',
                        label: 'Statut',
                        options: [{ value: 'actif', label: 'Actif' }],
                    },
                ],
            },
        });

        await wrapper.find('select').setValue('actif');
        await nextTick();

        // 12 lignes « actif » sur 25 -> 10 affichées sur la première page
        expect(wrapper.text()).toContain('1–10 sur 12');
    });

    it('affiche l’état vide quand la recherche ne donne rien', async () => {
        const wrapper = mount(DataTable<Row>, {
            props: { columns, data, emptyMessage: 'Aucun projet.' },
        });

        await wrapper.find('input[type="search"]').setValue('zzzz');
        await nextTick();

        expect(wrapper.text()).toContain('Aucun projet.');
        expect(wrapper.text()).toContain('0 résultat');
    });

    it('sans pagination (liste déjà paginée par le serveur), affiche toutes les lignes', () => {
        const wrapper = mount(DataTable<Row>, {
            props: {
                columns,
                data: data.slice(0, 15),
                paginate: false,
                pageSize: 100,
            },
        });

        expect(wrapper.text()).not.toContain('Suivant');
    });
});
