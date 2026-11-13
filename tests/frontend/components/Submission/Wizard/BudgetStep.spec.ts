import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { post, put, destroy, page } = vi.hoisted(() => ({
    post: vi.fn(),
    put: vi.fn(),
    destroy: vi.fn(),
    page: { props: { errors: {} as Record<string, string> } },
}))

vi.mock('@inertiajs/vue3', () => ({ router: { post, put, delete: destroy }, usePage: () => page }))

import BudgetItemForm from '@/Components/Submission/Wizard/BudgetItemForm.vue'
import BudgetStep from '@/Components/Submission/Wizard/BudgetStep.vue'
import { formatRupiah } from '@/lib/format'
import type { WizardBudget } from '@/types/submission-wizard'

const budget: WizardBudget = {
    items: [
        {
            id: 4,
            componentId: 2,
            component: 'Bahan',
            item: 'Kertas',
            volume: 2,
            unit: 'rim',
            unitPrice: 650_000,
            total: 1_300_000,
        },
    ],
    total: 1_300_000,
    maxBudget: 50_000_000,
    components: [{ value: '2', label: 'Bahan' }],
    blockedReason: null,
    editable: true,
}

const mountStep = (b: Partial<WizardBudget> = {}) =>
    mount(BudgetStep, { props: { submissionId: 7, budget: { ...budget, ...b } } })

beforeEach(() => {
    vi.stubGlobal(
        'route',
        (name: string, params: unknown) => `/${name}/${[params].flat().join(',')}`
    )
    post.mockReset()
    put.mockReset()
    destroy.mockReset()
    page.props.errors = {}
})

describe('BudgetStep', () => {
    it('[SM-WIZARD-04] tanpa skema hanya menampilkan alasan', async () => {
        const reason = 'Pilih skema di langkah 3 lebih dulu, batas anggaran berasal dari skema.'
        const wrapper = mountStep({ blockedReason: reason })

        expect(wrapper.text()).toContain(reason)
        expect(wrapper.find('table').exists()).toBe(false)
        expect(wrapper.findComponent(BudgetItemForm).exists()).toBe(false)

        await wrapper.find('button[data-goto="3"]').trigger('click')
        expect(wrapper.emitted('goto')).toEqual([[3]])
    })

    it('[SM-WIZARD-09] tanpa batas menampilkan catatan', () => {
        const wrapper = mountStep({ maxBudget: null })

        expect(wrapper.text()).toContain('Pengajuan tanpa skema tidak punya batas anggaran.')
        expect(wrapper.text()).not.toContain(' dari ')
    })

    it('total dan batas dirender rupiah', () => {
        const text = mountStep().text()

        expect(text).toContain(`Total ${formatRupiah(1_300_000)} dari ${formatRupiah(50_000_000)}`)
        expect(text).toContain(formatRupiah(650_000))
    })

    it('[BUD-WHO-03] tidak editable: tanpa form', () => {
        const wrapper = mountStep({ editable: false })

        expect(wrapper.findComponent(BudgetItemForm).exists()).toBe(false)
        expect(wrapper.find('button[data-action="edit-item"]').exists()).toBe(false)
        expect(wrapper.find('button[data-action="remove-item"]').exists()).toBe(false)
    })

    it('menghapus item lewat budget-items.destroy', async () => {
        await mountStep().find('button[data-action="remove-item"]').trigger('click')

        expect(destroy).toHaveBeenCalledWith(
            '/user.submissions.budget-items.destroy/7,4',
            expect.objectContaining({ preserveScroll: true })
        )
    })

    it('mengubah item mengisi form dan mengirim lewat budget-items.update', async () => {
        const wrapper = mountStep()

        await wrapper.find('button[data-action="edit-item"]').trigger('click')
        expect(wrapper.findComponent(BudgetItemForm).props('item')?.id).toBe(4)
        await wrapper.find('form').trigger('submit')

        expect(put).toHaveBeenCalledWith(
            '/user.submissions.budget-items.update/7,4',
            {
                budget_component_id: '2',
                item_name: 'Kertas',
                volume: 2,
                unit: 'rim',
                unit_price: 650_000,
            },
            expect.objectContaining({ preserveScroll: true })
        )
    })

    it('[BUD-LIMIT-01] error batas skema dari backend dirender apa adanya', () => {
        page.props.errors = { total: 'Total anggaran melebihi batas maksimum skema.' }

        expect(mountStep().text()).toContain('Total anggaran melebihi batas maksimum skema.')
    })
})
