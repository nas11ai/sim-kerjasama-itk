import { mount, type VueWrapper } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({
    Head: defineComponent({ setup: () => () => null }),
    Link: defineComponent({
        props: { href: { type: String, required: true } },
        setup:
            (props, { slots, attrs }) =>
            () =>
                h('a', { ...attrs, href: props.href }, slots.default?.()),
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

import PeriodReadinessPage from '@/Pages/FormEngine/PeriodReadinessPage.vue'
import type { CohortReadiness, ReadinessCheck } from '@/types/form-engine'

const check = (overrides: Partial<ReadinessCheck> = {}): ReadinessCheck => ({
    check: 'missing_date',
    title: 'Tanggal setiap langkah sudah diatur',
    issues: [],
    state: 'passed',
    ...overrides,
})

const cohort = (overrides: Partial<CohortReadiness> = {}): CohortReadiness => ({
    periodId: 5,
    phaseId: 2,
    phaseTitle: 'Hibah Internal',
    versionLabel: 'Versi 1',
    running: false,
    status: 'ready',
    checks: [
        check(),
        check({ check: 'date_order', title: 'Urutan tanggal masuk akal' }),
        check({
            check: 'required_evaluation_form',
            title: 'Langkah yang direview punya Form Evaluasi wajib',
        }),
    ],
    ...overrides,
})

const route = (name: string, params?: number) =>
    params === undefined ? `/${name}` : `/${name}/${params}`

let wrapper: VueWrapper

const mountPage = (cohorts: CohortReadiness[]) => {
    wrapper = mount(PeriodReadinessPage, {
        props: { period: { id: 5, name: '2026' }, cohorts },
        global: { mocks: { route } },
    })
    return wrapper
}

beforeEach(() => vi.stubGlobal('route', route))
afterEach(() => wrapper.unmount())

describe('FormEngine/PeriodReadinessPage', () => {
    it('[FE-READY-06] judul, subjudul, dan tautan kembali ke daftar periode', () => {
        mountPage([cohort()])

        expect(wrapper.text()).toContain('Kesiapan Angkatan')
        expect(wrapper.text()).toContain(
            'Periode 2026 — daftar cek tiap tahap sebelum menerima pengajuan baru.'
        )
        expect(wrapper.find('a[href="/admin.submission-periods.index"]').exists()).toBe(true)
    })

    it('[FE-READY-06] empat lencana status dengan keterangannya; Siap tanpa keterangan', () => {
        mountPage([
            cohort({ phaseId: 1, status: 'ready' }),
            cohort({ phaseId: 2, status: 'pending' }),
            cohort({ phaseId: 3, status: 'blocked' }),
            cohort({ phaseId: 4, status: 'needs_fix', running: true }),
        ])
        const cards = wrapper.findAll('[data-cohort]')
        const badge = (i: number) => cards[i].find('[data-status-badge]').text()
        const note = (i: number) => cards[i].find('[data-status-note]')

        expect(badge(0)).toBe('Siap')
        expect(note(0).exists()).toBe(false)
        expect(badge(1)).toBe('Siap — ada yang menyusul')
        expect(note(1).text()).toBe('Pengajuan baru diterima. Lengkapi sebelum langkahnya dimulai.')
        expect(badge(2)).toBe('Belum siap')
        expect(note(2).text()).toBe('Pengajuan baru di tahap ini ditolak sampai semua cek lolos.')
        expect(badge(3)).toBe('Perlu perbaikan')
        expect(note(3).text()).toBe('Angkatan ini sudah punya pengajuan, jadi tidak diblokir.')
    })

    it('[FE-READY-07] baris gagal dan peringatan menampilkan pesannya dengan ikon berbeda; baris lolos tanpa pesan', () => {
        mountPage([
            cohort({
                status: 'blocked',
                checks: [
                    check({
                        state: 'failed',
                        issues: [{ message: 'Batas Evaluasi belum diatur.', blocking: true }],
                    }),
                    check({
                        check: 'date_order',
                        title: 'Urutan tanggal masuk akal',
                        state: 'warning',
                        issues: [
                            {
                                message:
                                    'Batas Laporan Monev I belum diatur — langkah Laporan Monev I tertutup sampai tanggalnya diatur.',
                                blocking: false,
                            },
                        ],
                    }),
                    check({
                        check: 'required_evaluation_form',
                        title: 'Langkah yang direview punya Form Evaluasi wajib',
                    }),
                ],
            }),
        ])
        const rows = wrapper.findAll('[data-check]')
        const icon = (i: number) => rows[i].find('[data-check-icon]').attributes('data-check-icon')

        expect(rows).toHaveLength(3)
        expect(icon(0)).toBe('failed')
        expect(icon(1)).toBe('warning')
        expect(icon(2)).toBe('passed')
        expect(rows[0].find('[data-issue="blocking"]').text()).toBe(
            'Batas Evaluasi belum diatur. (memblokir)'
        )
        expect(rows[1].find('[data-issue="warning"]').text()).toContain(
            'Batas Laporan Monev I belum diatur'
        )
        expect(rows[2].find('[data-issue]').exists()).toBe(false)
        expect(rows[2].text()).toBe('Langkah yang direview punya Form Evaluasi wajib Lolos')
        // Keadaan baris terbaca pembaca layar, bukan hanya lewat ikon dan warna.
        expect(rows[0].find('.sr-only').text()).toBe('Gagal')
        expect(rows[1].find('.sr-only').text()).toBe('Peringatan')
    })

    it('[FE-READY-06] judul tahap dan label versinya tampil', () => {
        mountPage([cohort({ phaseTitle: 'Hibah Mandiri', versionLabel: 'Versi 3' })])

        expect(wrapper.text()).toContain('Hibah Mandiri')
        expect(wrapper.text()).toContain('Versi 3')
    })

    it('tanpa tahap → keadaan kosong', () => {
        mountPage([])

        expect(wrapper.text()).toContain('Belum ada tahap di periode ini')
    })
})
