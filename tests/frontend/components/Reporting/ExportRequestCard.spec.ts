import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

const { submitted } = vi.hoisted(() => ({
    submitted: vi.fn<(url: string, data: Record<string, unknown>) => void>(),
}))

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue')
    return {
        useForm: <T extends object>(data: T) => {
            let transformer = (d: Record<string, unknown>) => d
            const form: Record<string, unknown> = reactive({
                ...data,
                errors: {},
                processing: false,
                transform(fn: (d: Record<string, unknown>) => Record<string, unknown>) {
                    transformer = fn
                    return form
                },
                post(url: string) {
                    const { errors: _e, processing: _p, transform: _t, post: _post, ...rest } = form
                    submitted(url, transformer(rest))
                },
            })
            return form
        },
    }
})

import ExportRequestCard from '@/Components/Reporting/ExportRequestCard.vue'
import type { ExportTypeOption } from '@/types/reporting'

const types: ExportTypeOption[] = [
    { value: 'submissions', label: 'Rekap Pengajuan', filterKeys: ['year', 'scheme', 'status'] },
    { value: 'audit-log', label: 'Log Audit', filterKeys: ['year'] },
]
const route = (name: string) =>
    name === 'reporting.exports.store' ? '/reporting/export' : `/${name}`
vi.stubGlobal('route', route)

const mountCard = (initialType: string | null) =>
    mount(ExportRequestCard, {
        props: {
            types,
            formats: [
                { value: 'xlsx', label: 'Excel' },
                { value: 'csv', label: 'CSV' },
            ],
            schemeOptions: [{ value: '4', label: 'DIPA' }],
            statusOptions: [{ value: 'approved', label: 'Approved' }],
            yearOptions: [{ value: '2026', label: '2026' }],
            threshold: 100,
            initialType,
        },
        global: { mocks: { route } },
    })
const labels = (wrapper: ReturnType<typeof mountCard>) =>
    wrapper.findAll('label').map((l) => l.text())

describe('ExportRequestCard', () => {
    it('field Skema dan Status hanya untuk jenis yang menerimanya', () => {
        expect(labels(mountCard(null))).toEqual(
            expect.arrayContaining(['Jenis Ekspor *', 'Skema', 'Status'])
        )

        const audit = labels(mountCard('audit-log'))

        expect(audit).toEqual(expect.arrayContaining(['Jenis Ekspor *']))
        expect(audit).not.toContain('Skema')
        expect(audit).not.toContain('Status')
    })

    it('[E2E-05] Rentang Tahun mengelompokkan kedua select tahun lewat fieldset', () => {
        const fieldset = mountCard(null).find('fieldset')

        expect(fieldset.find('legend').text()).toBe('Rentang Tahun')
        expect(fieldset.findAll('[aria-label]').map((el) => el.attributes('aria-label'))).toEqual([
            'Dari tahun',
            'Sampai tahun',
        ])
        expect(mountCard(null).find('label[for="export-years"]').exists()).toBe(false)
    })

    it('hint ambang berasal dari threshold', () => {
        expect(mountCard(null).text()).toContain('Lebih dari 100 baris diproses di latar belakang.')
    })

    it('format Excel dan CSV sebagai pilihan segmented', () => {
        const wrapper = mountCard(null)

        expect(wrapper.findAll('[role="radio"]').map((r) => r.attributes('aria-checked'))).toEqual([
            'true',
            'false',
        ])
        expect(wrapper.text()).toContain('Excel')
        expect(wrapper.text()).toContain('CSV')
    })

    it('Request Export mengirim jenis, format, dan hanya filter yang terisi', async () => {
        await mountCard('audit-log').find('form').trigger('submit')

        expect(submitted).toHaveBeenCalledWith('/reporting/export', {
            type: 'audit-log',
            format: 'xlsx',
        })
    })
})
