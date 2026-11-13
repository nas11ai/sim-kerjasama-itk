import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const { get } = vi.hoisted(() => ({ get: vi.fn() }))

vi.mock('@inertiajs/vue3', () => ({
    Head: defineComponent({ setup: () => () => null }),
    Link: defineComponent({
        props: { href: { type: String, default: '' } },
        setup:
            (props, { slots }) =>
            () =>
                h('a', { href: props.href }, slots.default?.()),
    }),
    router: { get },
}))
vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('div', slots.default?.()),
    }),
}))

import AuditPage from '@/Pages/Reporting/AuditPage.vue'
import type { AuditEntryRow, AuditPageProps } from '@/types/reporting'

import { auditEntry } from '../../fixtures/auditEntry'

const route = (name: string, params?: Record<string, string>) =>
    `/${name}${params ? `?${new URLSearchParams(params)}` : ''}`
vi.stubGlobal('route', route)

const mountPage = (
    overrides: Partial<AuditPageProps> = {},
    rows: AuditEntryRow[] = [auditEntry()]
) => {
    const props: AuditPageProps = {
        entries: {
            data: rows,
            current_page: 1,
            last_page: 1,
            per_page: 25,
            total: rows.length,
            from: rows.length > 0 ? 1 : null,
            to: rows.length > 0 ? rows.length : null,
            prev_page_url: null,
            next_page_url: null,
        },
        filters: { search: '', range: '7d', action: '', actor: '' },
        actionOptions: [{ value: 'Pengajuan disubmit', label: 'Pengajuan disubmit' }],
        actorOptions: [
            { value: 'operator', label: 'Operator' },
            { value: 'system', label: 'Sistem' },
        ],
        rangeOptions: [
            { value: '7d', label: '7 hari' },
            { value: '30d', label: '30 hari' },
            { value: '90d', label: '90 hari' },
            { value: 'all', label: 'Semua' },
        ],
        seesEverything: true,
        ...overrides,
    }
    return mount(AuditPage, { props, global: { mocks: { route } }, attachTo: document.body })
}

let wrapper: ReturnType<typeof mountPage> | undefined

beforeEach(() => vi.clearAllMocks())
afterEach(() => {
    wrapper?.unmount()
    wrapper = undefined
})

describe('AuditPage', () => {
    it('judul, subjudul, dan toolbar frame', () => {
        wrapper = mountPage()

        expect(wrapper.text()).toContain('Log Audit')
        expect(wrapper.text()).toContain(
            'Catatan seluruh aktivitas sistem, bersifat permanen dan tidak dapat dihapus.'
        )
        expect(wrapper.find('input[type="search"]').attributes('placeholder')).toBe(
            'Cari subjek / pelaku...'
        )
        // Label pilihan terpilih digambar reka-ui lewat portal; isinya dicek di browser.
        expect(wrapper.find('[aria-label="Waktu"]').exists()).toBe(true)
        expect(wrapper.find('[aria-label="Tipe Aksi"]').exists()).toBe(true)
        expect(wrapper.find('[aria-label="Pelaku"]').exists()).toBe(true)
    })

    it('Export Log Audit menaut ke ekspor jenis audit-log', () => {
        wrapper = mountPage()
        const link = wrapper.findAll('a').find((a) => a.text().includes('Export Log Audit'))

        expect(link?.attributes('href')).toBe('/reporting.exports.index?type=audit-log')
    })

    it('[BR-RPT-05] peneliti melihat banner cakupan dan judul yang sama', () => {
        wrapper = mountPage({ seesEverything: false }, [auditEntry({ ipAddress: null })])

        expect(wrapper.text()).toContain('Log Audit')
        expect(wrapper.text()).toContain(
            'Daftar ini hanya memuat aktivitas pada pengajuan Anda dan akun Anda sendiri.'
        )
    })

    it('Operator tidak melihat banner cakupan', () => {
        wrapper = mountPage()

        expect(wrapper.text()).not.toContain('Daftar ini hanya memuat aktivitas')
    })

    it('Lihat membuka dialog entri', async () => {
        wrapper = mountPage({}, [auditEntry({ action: 'Skema disimpan' })])

        await wrapper
            .findAll('button')
            .find((b) => b.text() === 'Lihat')!
            .trigger('click')
        await flushPromises()

        expect(document.body.querySelector('[role="dialog"]')?.textContent).toContain(
            'Skema disimpan'
        )
    })
})
