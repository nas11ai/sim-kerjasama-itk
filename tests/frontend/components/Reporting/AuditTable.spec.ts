import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import AuditTable from '@/Components/Reporting/AuditTable.vue'
import type { AuditEntryRow } from '@/types/reporting'

import { auditEntry } from '../../fixtures/auditEntry'

const mountTable = (rows: AuditEntryRow[], seesEverything = true) =>
    mount(AuditTable, { props: { rows, seesEverything } })
const headers = (wrapper: ReturnType<typeof mountTable>) =>
    wrapper.findAll('th').map((th) => th.text())
const buttons = (wrapper: ReturnType<typeof mountTable>) =>
    wrapper.findAll('button').map((b) => b.text())

describe('AuditTable', () => {
    it('kolom frame dan IP kosong sebagai tanda pisah', () => {
        const wrapper = mountTable([auditEntry(), auditEntry({ id: 2, ipAddress: null })])

        expect(headers(wrapper).filter(Boolean)).toEqual([
            'Waktu',
            'Subjek',
            'Aksi',
            'Pelaku',
            'IP Address',
        ])
        expect(wrapper.text()).toContain('03 Jul 2026 09.41')
        expect(wrapper.text()).toContain('Submission — Biochar Kaltim')
        expect(wrapper.findAll('tbody tr')[1].text()).toContain('—')
    })

    it('tanpa cakupan penuh tidak ada kolom IP', () => {
        const wrapper = mountTable([auditEntry({ ipAddress: null })], false)

        expect(headers(wrapper)).not.toContain('IP Address')
    })

    it('daftar kosong', () => {
        expect(mountTable([]).text()).toContain('Tidak ada aktivitas di periode ini')
    })

    it('[RPT-AUDIT-02] baris audit tidak punya aksi hapus', () => {
        const labels = buttons(mountTable([auditEntry()]))

        expect(labels).toEqual(['Lihat'])
        expect(labels.some((label) => /hapus/i.test(label))).toBe(false)
    })

    it('[RPT-AUDIT-03] baris audit tidak punya aksi sunting', () => {
        const labels = buttons(mountTable([auditEntry()]))

        expect(labels.some((label) => /sunting|edit/i.test(label))).toBe(false)
    })

    it('Lihat memancarkan baris yang dipilih', async () => {
        const row = auditEntry()
        const wrapper = mountTable([row])

        await wrapper.find('button').trigger('click')

        expect(wrapper.emitted('view')).toEqual([[row]])
    })
})
