import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { post, destroy, reload, page } = vi.hoisted(() => ({
    post: vi.fn(),
    destroy: vi.fn(),
    reload: vi.fn(),
    page: { props: { errors: {} as Record<string, string> } },
}))

vi.mock('@inertiajs/vue3', () => ({
    router: { post, delete: destroy, reload },
    usePage: () => page,
}))

import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import MemberSearch from '@/Components/Submission/Wizard/MemberSearch.vue'
import MembersStep from '@/Components/Submission/Wizard/MembersStep.vue'
import type { WizardMembers } from '@/types/submission-wizard'

const members: WizardMembers = {
    members: [
        {
            id: 0,
            name: 'Dr. Ketua',
            role: 'lead_researcher',
            roleLabel: 'Ketua',
            unit: 'Informatika',
            nidn: '0011',
            removable: false,
        },
        {
            id: 5,
            name: 'Dr. Anggota',
            role: 'co_investigator',
            roleLabel: 'Co-Investigator',
            unit: null,
            nidn: null,
            removable: true,
        },
    ],
    students: [],
    partners: [],
    memberLimit: null,
    studentLimit: null,
    roleOptions: [{ value: 'co_investigator', label: 'Co-Investigator' }],
}

const mountStep = (m: Partial<WizardMembers> = {}) =>
    mount(MembersStep, { props: { submissionId: 7, members: { ...members, ...m } } })

beforeEach(() => {
    vi.stubGlobal(
        'route',
        (name: string, params: unknown) => `/${name}/${[params].flat().join(',')}`
    )
    destroy.mockReset()
    page.props.errors = {}
})

describe('MembersStep', () => {
    it('[SM-MEMBER-06] ketua tanpa tombol hapus', () => {
        const wrapper = mountStep()

        expect(wrapper.text()).toContain('Dr. Ketua')
        expect(wrapper.findAll('button[data-action="remove-member"]')).toHaveLength(1)
        expect(wrapper.text()).toContain('Ketua tidak dapat dihapus')
    })

    it('[SM-MEMBER-03] kuota penuh mematikan pencarian', () => {
        const wrapper = mountStep({ memberLimit: 1 })

        expect(wrapper.text()).toContain('1 dari 1')
        expect(wrapper.text()).toContain(
            'Jumlah anggota sudah mencapai batas skema. Keluarkan anggota lain sebelum menambah.'
        )
        expect(wrapper.findComponent(MemberSearch).props('disabled')).toBe(true)
    })

    it('kuota belum penuh: pencarian aktif', () => {
        const wrapper = mountStep({ memberLimit: 3 })

        expect(wrapper.text()).toContain('1 dari 3')
        expect(wrapper.findComponent(MemberSearch).props('disabled')).toBe(false)
    })

    it('[SM-MEMBER-04] hapus anggota lewat members.destroy', async () => {
        const wrapper = mountStep()

        await wrapper.find('button[data-action="remove-member"]').trigger('click')
        wrapper.findComponent(ConfirmDialog).vm.$emit('confirm')

        expect(destroy).toHaveBeenCalledWith(
            '/user.submissions.members.destroy/7,5',
            expect.objectContaining({ preserveScroll: true })
        )
    })
})
