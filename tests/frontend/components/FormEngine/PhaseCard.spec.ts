import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

type FakeForm = { submission_period_id: string | null; errors: Record<string, string> }

const { posted } = vi.hoisted(() => ({
    posted: vi.fn<(url: string, data: { submission_period_id: string | null }) => void>(),
}))

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue')
    return {
        useForm: (data: { submission_period_id: string | null }) => {
            const form: FakeForm & Record<string, unknown> = reactive({
                ...data,
                errors: {},
                processing: false,
                reset() {},
                clearErrors() {},
                post(url: string, options?: { onSuccess?: () => void }) {
                    posted(url, { submission_period_id: form.submission_period_id })
                    options?.onSuccess?.()
                },
            })
            return form
        },
        Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    }
})

import OptionSelect from '@/Components/app/OptionSelect.vue'
import PhaseCard from '@/Components/FormEngine/PhaseCard.vue'
import PhasePeriodDialog from '@/Components/FormEngine/PhasePeriodDialog.vue'
import type { PhaseCardData } from '@/types/form-engine'

const route = (name: string, params?: number | Record<string, number>) =>
    [`/${name}`, typeof params === 'object' ? Object.values(params).join('/') : params]
        .filter((part) => part !== undefined)
        .join('/')

const phase = (overrides: Partial<PhaseCardData> = {}): PhaseCardData => ({
    id: 7,
    title: 'Pengajuan',
    description: null,
    isActive: true,
    detailCount: 2,
    periodNames: ['2026 Gelombang 1'],
    submissionCount: 0,
    linkablePeriods: [{ value: '9', label: '2026 Gelombang 2' }],
    deadlineGaps: [],
    submissionFormTitle: 'Proposal Penelitian',
    versionLabel: 'Versi 1',
    hasDraft: false,
    ...overrides,
})

const mountCard = (props: { phase: PhaseCardData; canLinkPeriod: boolean }) =>
    mount(PhaseCard, { props, global: { mocks: { route } } })

beforeEach(() => vi.stubGlobal('route', route))
afterEach(() => posted.mockClear())

describe('PhaseCard — hubungkan ke periode', () => {
    it('tombol tersembunyi tanpa wewenang atau tanpa periode layak', () => {
        const noRight = mountCard({ phase: phase(), canLinkPeriod: false })
        const noPeriod = mountCard({ phase: phase({ linkablePeriods: [] }), canLinkPeriod: true })

        expect(noRight.text()).not.toContain('Hubungkan ke Periode')
        expect(noPeriod.text()).not.toContain('Hubungkan ke Periode')
    })

    it('tombol tampil dan memancarkan link-period', async () => {
        const wrapper = mountCard({ phase: phase(), canLinkPeriod: true })

        const button = wrapper.findAll('button').find((b) => b.text() === 'Hubungkan ke Periode')
        await button?.trigger('click')

        expect(wrapper.emitted('link-period')?.[0]).toEqual([phase()])
    })

    it('[FE-WINDOW-08] deadlineGaps menjadi peringatan per periode', () => {
        const wrapper = mountCard({
            phase: phase({
                deadlineGaps: [
                    { period: '2026 Gelombang 2', count: 2 },
                    { period: '2027 Gelombang 1', count: 1 },
                ],
            }),
            canLinkPeriod: false,
        })

        expect(wrapper.text()).toContain(
            'Periode 2026 Gelombang 2 belum punya 2 tanggal yang dibutuhkan tahap ini.'
        )
        expect(wrapper.text()).toContain(
            'Periode 2027 Gelombang 1 belum punya 1 tanggal yang dibutuhkan tahap ini.'
        )
    })

    it('tanpa deadlineGaps tidak ada peringatan', () => {
        const wrapper = mountCard({ phase: phase(), canLinkPeriod: true })

        expect(wrapper.text()).not.toContain('tanggal yang dibutuhkan tahap ini')
    })
})

describe('PhaseCard — Form pengajuan', () => {
    it('menyebut Form pengajuan tahap (BR-FE-28)', () => {
        const wrapper = mountCard({ phase: phase(), canLinkPeriod: false })

        expect(wrapper.text()).toContain('Formulir pengajuan: Proposal Penelitian')
    })

    it('tanpa langkah Pengajuan tidak menyebut Form pengajuan', () => {
        const wrapper = mountCard({
            phase: phase({ submissionFormTitle: null }),
            canLinkPeriod: false,
        })

        expect(wrapper.text()).not.toContain('Formulir pengajuan:')
    })
})

describe('PhaseCard — tautan Edit', () => {
    it('menaut ke editor tahap mandiri', () => {
        const wrapper = mountCard({ phase: phase(), canLinkPeriod: false })

        expect(wrapper.find('a[href="/admin.phases.edit/7"]').text()).toContain('Ubah')
    })
})

describe('PhasePeriodDialog', () => {
    afterEach(() => {
        document.body.innerHTML = ''
    })

    it('memilih periode lalu Hubungkan mengirim submission_period_id', async () => {
        const wrapper = mount(PhasePeriodDialog, {
            props: { open: true, phase: phase() },
            attachTo: document.body,
            global: { mocks: { route } },
        })
        await flushPromises()

        const select = wrapper.findComponent(OptionSelect)
        expect(select.props('options')).toEqual([{ value: '9', label: '2026 Gelombang 2' }])
        expect(document.body.textContent).toContain(
            'Detail tahap baru terbuka di periode ini setelah tenggatnya dipindah ke tanggal periode ini.'
        )
        select.vm.$emit('update:modelValue', '9')
        await flushPromises()

        const submit = Array.from(document.querySelectorAll('button')).find(
            (b) => b.textContent?.trim() === 'Hubungkan'
        )
        submit?.click()
        await flushPromises()

        expect(posted).toHaveBeenCalledWith('/admin.phases.periods.store/7', {
            submission_period_id: '9',
        })
        wrapper.unmount()
    })

    // BR-FE-33 — tahap tanpa versi belum bisa dipakai periode; draf diberi banner.
    it('lencana versi atau "Belum terbit", dan banner draf', () => {
        const published = mountCard({ phase: phase(), canLinkPeriod: true })
        expect(published.text()).toContain('Versi 1')
        expect(published.text()).not.toContain('Draf belum diterbitkan')

        const fresh = mountCard({
            phase: phase({ versionLabel: null, hasDraft: true, linkablePeriods: [] }),
            canLinkPeriod: true,
        })
        expect(fresh.text()).toContain('Belum terbit')
        expect(fresh.text()).toContain(
            'Draf belum diterbitkan. Tahap ini belum bisa dipakai periode.'
        )
        expect(fresh.text()).not.toContain('Hubungkan ke Periode')
    })
})
