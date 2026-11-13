import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const { post, reload, page } = vi.hoisted(() => ({
    post: vi.fn(),
    reload: vi.fn(),
    page: { props: { errors: {} as Record<string, string> } },
}))

vi.mock('@inertiajs/vue3', () => ({ router: { post, reload }, usePage: () => page }))

import OptionSelect from '@/Components/app/OptionSelect.vue'
import MemberSearch from '@/Components/Submission/Wizard/MemberSearch.vue'
import type { MemberCandidate } from '@/types/submission-wizard'

const roleOptions = [
    { value: 'co_investigator', label: 'Co-Investigator' },
    { value: 'member', label: 'Anggota' },
]

const mountSearch = (candidates: MemberCandidate[] | undefined = undefined) =>
    mount(MemberSearch, { props: { submissionId: 7, roleOptions, candidates, disabled: false } })

beforeEach(() => {
    vi.useFakeTimers()
    vi.stubGlobal('route', (name: string, params: unknown) => `/${name}/${String(params)}`)
    post.mockReset()
    reload.mockReset()
    page.props.errors = {}
})

afterEach(() => vi.useRealTimers())

describe('MemberSearch', () => {
    it('pencarian memuat ulang memberCandidates saja', async () => {
        const wrapper = mountSearch()

        await wrapper.find('input[type="search"]').setValue('Rina')
        expect(reload).not.toHaveBeenCalled()
        vi.advanceTimersByTime(300)

        expect(reload).toHaveBeenCalledWith(
            expect.objectContaining({
                only: ['memberCandidates'],
                data: { member_search: 'Rina' },
            })
        )
    })

    it('[SM-MEMBER-01] kandidat reviewer nonaktif dengan alasan', () => {
        const reason =
            'Dr. Rina sedang ditugaskan sebagai penilai pengajuan ini, jadi ia tidak dapat ditambahkan sebagai anggota.'
        const wrapper = mountSearch([
            { id: 9, name: 'Dr. Rina', nidn: null, blockedReason: reason },
        ])

        expect(wrapper.text()).toContain(reason)
        expect(
            wrapper.find('button[data-action="add-member"]').attributes('disabled')
        ).toBeDefined()
    })

    it('menambah lewat members.store dengan peran', async () => {
        const wrapper = mountSearch([
            { id: 9, name: 'Dr. Budi', nidn: '0099', blockedReason: null },
        ])

        wrapper.findComponent(OptionSelect).vm.$emit('update:modelValue', 'member')
        await wrapper.vm.$nextTick()
        await wrapper.find('button[data-action="add-member"]').trigger('click')

        expect(post).toHaveBeenCalledWith(
            '/user.submissions.members.store/7',
            { user_id: 9, role: 'member' },
            expect.objectContaining({ preserveScroll: true })
        )
    })

    it('penolakan backend dirender', () => {
        page.props.errors = { user_id: 'Jumlah anggota sudah mencapai batas skema (3 orang).' }

        expect(mountSearch().text()).toContain(
            'Jumlah anggota sudah mencapai batas skema (3 orang).'
        )
    })
})
