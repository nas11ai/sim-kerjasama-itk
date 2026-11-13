import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { router, page } = vi.hoisted(() => ({
    router: { get: vi.fn(), post: vi.fn() },
    page: { props: { errors: {} as Record<string, string> } },
}))

vi.mock('@inertiajs/vue3', () => ({
    router,
    usePage: () => page,
    Head: defineComponent({ setup: () => () => null }),
    Link: defineComponent({
        props: { href: { type: String, required: true } },
        setup:
            (props, { slots }) =>
            () =>
                h('a', { href: props.href }, slots.default?.()),
    }),
}))
vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('div', slots.default?.()),
    }),
}))

import MonevReportFormPage from '@/Pages/Monev/ReportFormPage.vue'
import type { MonevReportFormPageProps } from '@/types/monev'

const props = (overrides: Partial<MonevReportFormPageProps> = {}): MonevReportFormPageProps => ({
    report: {
        id: 9,
        parentId: 7,
        title: 'Smart Farming Hidroponik Kampus',
        scheme: 'Penelitian Mandiri',
        cycleName: 'Monev I',
        deadline: '2026-07-30T23:59:00+08:00',
        lastUpdatedAt: '2026-07-01T10:00:00.000000Z',
    },
    cycle: {
        detailId: 31,
        name: 'Monev I',
        order: 3,
        needsReview: false,
        deadline: '2026-07-30T23:59:00+08:00',
        state: 'open',
        blockedReason: null,
        reportId: 9,
        submittedAt: null,
        openedAt: null,
    },
    fields: [
        {
            id: 51,
            label: 'Ringkasan Kemajuan',
            type: 'textarea',
            required: true,
            value: 'Capaian awal',
            options: [],
            fileName: null,
            downloadUrl: null,
        },
    ],
    phaseAccess: { is_open: true, deadline: null, form_phase_detail_id: 31, override: null },
    can: { save: true, submit: true },
    ...overrides,
})

const route = (name: string, params?: unknown) => `/${name}/${JSON.stringify(params ?? null)}`

const mountPage = (overrides: Partial<MonevReportFormPageProps> = {}) =>
    mount(MonevReportFormPage, {
        props: props(overrides),
        global: { mocks: { route } },
        attachTo: document.body,
    })

const button = (label: string) =>
    Array.from(document.querySelectorAll('button')).find((b) => b.textContent?.trim() === label)

const blocked = (state: 'closed' | 'locked', reason: string) =>
    mountPage({
        cycle: { ...props().cycle, state, blockedReason: reason },
        can: { save: false, submit: false },
    })

beforeEach(() => {
    vi.stubGlobal('route', route)
    document.body.innerHTML = ''
    page.props.errors = {}
    router.post.mockClear()
})

describe('Monev/ReportFormPage', () => {
    it('menggambar judul, subjudul, badge, dan banner frame', () => {
        const text = mountPage().text()

        expect(text).toContain('Isi Laporan Monev I')
        expect(text).toContain('Smart Farming Hidroponik Kampus · Penelitian Mandiri')
        expect(text).toContain('Tenggat')
        expect(text).toContain(
            'Setiap siklus monev hanya menerima satu laporan. Setelah batas waktu terlewat, formulir ditutup, hubungi Operator LPPM bila membutuhkan perpanjangan.'
        )
        expect(text).toContain('Laporan Kemajuan')
        expect(text).toContain('Ringkasan Kemajuan')
    })

    it('[MON-CYCLE-08] siklus tertutup menampilkan banner kontak Operator tanpa form', () => {
        const wrapper = blocked(
            'closed',
            'Batas waktu Monev I sudah lewat. Hubungi Operator LPPM bila membutuhkan perpanjangan.'
        )

        expect(wrapper.text()).toContain('Batas waktu Monev I sudah lewat.')
        expect(wrapper.text()).not.toContain('Ringkasan Kemajuan')
        expect(button('Simpan Draf')).toBeUndefined()
        expect(button('Kirim Laporan')).toBeUndefined()
    })

    it('[MON-CYCLE-03] siklus terkunci menampilkan alasan terkunci', () => {
        const wrapper = blocked(
            'locked',
            'Selesaikan Laporan Monev I lebih dulu sebelum mengisi Monev II.'
        )

        expect(wrapper.text()).toContain(
            'Selesaikan Laporan Monev I lebih dulu sebelum mengisi Monev II.'
        )
        expect(button('Simpan Draf')).toBeUndefined()
    })

    it('override aktif menampilkan banner perpanjangan', () => {
        const text = mountPage({
            phaseAccess: {
                is_open: true,
                deadline: null,
                form_phase_detail_id: 31,
                override: {
                    id: 3,
                    status: 'active',
                    expires_at: '2026-06-20 23:59:00',
                    reason: null,
                },
            },
        }).text()

        expect(text).toContain('Anda mendapatkan perpanjangan akses hingga')
        expect(text).toContain('Akses diberikan oleh operator.')
    })

    it('Simpan Draft mengirim responses', async () => {
        mountPage()

        button('Simpan Draf')?.click()
        await flushPromises()

        expect(router.post).toHaveBeenCalledWith(
            route('monev.reports.save', 9),
            {
                last_updated_at: '2026-07-01T10:00:00.000000Z',
                responses: [{ form_field_id: 51, value: 'Capaian awal' }],
            },
            expect.objectContaining({ forceFormData: false })
        )
    })

    it('Submit Laporan meminta konfirmasi', async () => {
        mountPage()

        button('Kirim Laporan')?.click()
        await flushPromises()

        expect(document.body.textContent).toContain('Kirim laporan Monev I?')
        expect(document.body.textContent).toContain(
            'Laporan yang sudah dikirim tidak bisa disunting lagi.'
        )
        expect(router.post).not.toHaveBeenCalled()

        const confirms = Array.from(document.querySelectorAll('button')).filter(
            (b) => b.textContent?.trim() === 'Kirim Laporan'
        )
        confirms[confirms.length - 1]?.click()
        await flushPromises()

        expect(router.post).toHaveBeenCalledWith(
            route('monev.reports.submit', 9),
            expect.objectContaining({ responses: [{ form_field_id: 51, value: 'Capaian awal' }] }),
            expect.anything()
        )
    })
    it('galat di luar field tampil sebagai banner (BR-IAM-04, BR-MON-08)', () => {
        page.props.errors = {
            verification_status:
                'Akun Anda belum terverifikasi. Pengajuan dapat disiapkan, tapi belum dapat dikirim.',
            field_51: 'Wajib diisi.',
        }
        const text = mountPage().text()

        expect(text).toContain('Akun Anda belum terverifikasi.')
        expect(text.match(/Wajib diisi\./g)?.length).toBe(1)
    })

    it('dialog konfirmasi tertutup saat submit ditolak', async () => {
        mountPage()
        button('Kirim Laporan')?.click()
        await flushPromises()
        const confirms = Array.from(document.querySelectorAll('button')).filter(
            (b) => b.textContent?.trim() === 'Kirim Laporan'
        )
        confirms[confirms.length - 1]?.click()
        await flushPromises()

        const options = router.post.mock.calls[0][2] as { onError?: () => void }
        options.onError?.()
        await flushPromises()

        expect(document.body.textContent).not.toContain('Kirim laporan Monev I?')
    })
})
