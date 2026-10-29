import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/lib/dataTable';

type Row = { id: number; name: string; role: string };

const columns: DataTableColumn<Row>[] = [
    { accessorKey: 'name', header: 'Nom' },
    { accessorKey: 'role', header: 'Rôle' },
];

describe('DataTable', () => {
    it('affiche les en-têtes et une ligne par enregistrement', () => {
        const wrapper = mount(DataTable<Row>, {
            props: {
                columns,
                data: [
                    { id: 1, name: 'Awa Koné', role: 'Magasinier' },
                    { id: 2, name: 'Yao Kouassi', role: 'Chef de chantier' },
                ],
            },
        });

        expect(wrapper.findAll('th').map((th) => th.text())).toEqual([
            'Nom',
            'Rôle',
        ]);
        expect(wrapper.findAll('tbody tr')).toHaveLength(2);
        expect(wrapper.text()).toContain('Yao Kouassi');
    });

    it('affiche un message quand il n’y a aucun résultat', () => {
        const wrapper = mount(DataTable<Row>, {
            props: { columns, data: [], emptyMessage: 'Aucun utilisateur.' },
        });

        expect(wrapper.findAll('tbody tr')).toHaveLength(1);
        expect(wrapper.text()).toContain('Aucun utilisateur.');
    });

    it('se met à jour quand les données changent', async () => {
        const wrapper = mount(DataTable<Row>, {
            props: { columns, data: [] },
        });

        await wrapper.setProps({
            data: [{ id: 1, name: 'Awa Koné', role: 'Magasinier' }],
        });

        expect(wrapper.text()).toContain('Awa Koné');
    });
});
