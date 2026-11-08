import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({
    Link: defineComponent({
        props: { href: { type: String, required: true } },
        setup:
            (props, { slots }) =>
            () =>
                h('a', { href: props.href }, slots.default?.()),
    }),
}))

import ExportHistoryTable from '@/Components/Reporting/ExportHistoryTable.vue'
import type { ExportJobRow } from '@/types/reporting'

import { exportJob } from '../../fixtures/exportJob'

const mountTable = (jobs: ExportJobRow[]) =>
    mount(ExportHistoryTable, { props: { jobs, maxAttempts: 3 } })
const button = (wrapper: ReturnType<typeof mountTable>, label: string) =>
    wrapper.findAll('button').find((b) => b.text().includes(label))

beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-07-03T09:00:00+08:00'))
})

afterEach(() => {
    vi.useRealTimers()
})

describe('ExportHistoryTable', () => {
    it('kolom mengikuti frame dan baris memuat jenis serta parameter', () => {
        const wrapper = mountTable([exportJob()])

        expect(wrapper.findAll('th').map((th) => th.text())).toEqual([
            'Jenis',
            'Parameter',
            'Status',
            'Waktu',
            'Aksi',
        ])
        expect(wrapper.text()).toContain('Rekap Submission')
        expect(wrapper.text()).toContain('Sejak 2026 · Semua skema')
        expect(wrapper.text()).toContain('Selesai')
    })

    it('[RPT-EXPORT-02] ekspor yang dijadwalkan menampilkan banner latar belakang', () => {
        const wrapper = mountTable([
            exportJob({ status: 'queued', file_is_gone: true, download_url: null }),
        ])

        expect(wrapper.text()).toContain('Antrian')
        expect(wrapper.text()).toContain(
            'Ekspor dijadwalkan. Anda akan diberi tahu saat berkasnya siap — layar ini boleh ditutup.'
        )
    })

    it('sisa masa simpan ditampilkan dalam jam', () => {
        const wrapper = mountTable([exportJob({ file_expires_at: '2026-07-03T12:00:00+08:00' })])

        expect(
            wrapper.find('a[href^="https://simpas.test/reporting/export/1/download"]').text()
        ).toContain('Unduh')
        expect(wrapper.text()).toContain('Kadaluarsa dalam 3 jam')
    })

    it('[RPT-EXPORT-04] tautan lewat masa berlaku diganti Minta Tautan Baru', async () => {
        const wrapper = mountTable([exportJob({ link_expires_at: '2026-07-03T09:00:30+08:00' })])

        expect(button(wrapper, 'Minta Tautan Baru')).toBeUndefined()

        // Layar dibiarkan terbuka: tautan basi digambar ulang tanpa menunggu klik.
        vi.advanceTimersByTime(60_000)
        await flushPromises()

        expect(wrapper.find('a[href^="https://simpas.test"]').exists()).toBe(false)
        expect(wrapper.text()).toContain(
            'Tautan unduhan sudah kedaluwarsa. Minta tautan baru — berkasnya belum terhapus.'
        )
        await button(wrapper, 'Minta Tautan Baru')?.trigger('click')
        expect(wrapper.emitted('refreshLinks')).toHaveLength(1)
    })

    it('[RPT-EXPORT-06] gagal setelah 3 percobaan menawarkan Coba Lagi', async () => {
        const job = exportJob({
            status: 'failed',
            attempts: 3,
            failure_reason: 'Disk penuh',
            download_url: null,
        })
        const wrapper = mountTable([job])

        expect(wrapper.text()).toContain('Gagal')
        expect(wrapper.text()).toContain('3 dari 3')
        expect(wrapper.text()).toContain(
            'Export gagal — coba lagi. Sistem tidak akan mengulang sendiri; jalankan ulang secara manual.'
        )
        await button(wrapper, 'Coba Lagi')?.trigger('click')
        expect(wrapper.emitted('rerun')?.[0]).toEqual([job])
    })

    it('[RPT-EXPORT-10] berkas terhapus menawarkan Ekspor Ulang', async () => {
        const job = exportJob({ file_is_gone: true, download_url: null })
        const wrapper = mountTable([job])

        expect(wrapper.text()).toContain('Kadaluarsa')
        expect(wrapper.text()).toContain(
            'Berkas ekspor sudah dihapus setelah 24 jam. Jalankan ekspor ulang untuk mendapatkannya kembali.'
        )
        await button(wrapper, 'Ekspor Ulang')?.trigger('click')
        expect(wrapper.emitted('rerun')?.[0]).toEqual([job])
    })

    it('baris cetak yang gagal tidak menawarkan Coba Lagi', () => {
        const wrapper = mountTable([
            exportJob({
                kind: 'print',
                type: 'Surat Keterangan Diterima',
                status: 'failed',
                download_url: null,
                submission_href: '/admin/submissions/7',
            }),
        ])

        expect(button(wrapper, 'Coba Lagi')).toBeUndefined()
        expect(button(wrapper, 'Ekspor Ulang')).toBeUndefined()
        expect(wrapper.find('a[href="/admin/submissions/7"]').text()).toContain(
            'Cetak ulang dari halaman pengajuan.'
        )
    })

    it('riwayat kosong', () => {
        expect(mountTable([]).text()).toContain('Belum ada ekspor.')
    })
})
