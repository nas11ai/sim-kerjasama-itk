import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'

import AuditEntryDialog from '@/Components/Reporting/AuditEntryDialog.vue'
import type { AuditEntryRow } from '@/types/reporting'

import { auditEntry } from '../../fixtures/auditEntry'

let wrapper: VueWrapper | undefined
const text = () => document.body.textContent ?? ''

const open = async (entry: AuditEntryRow, seesEverything = true) => {
    wrapper = mount(AuditEntryDialog, {
        props: { entry, open: true, seesEverything },
        attachTo: document.body,
    })
    await flushPromises()
}

afterEach(() => {
    wrapper?.unmount()
    wrapper = undefined
})

describe('AuditEntryDialog', () => {
    it('[BUD-AUDIT-02] detail menyebut pelaku dan waktu', async () => {
        await open(auditEntry({ action: 'Line item anggaran diubah', actor: 'Ir. Dewi Lestari' }))

        expect(text()).toContain('Line item anggaran diubah')
        expect(text()).toContain('Ir. Dewi Lestari')
        expect(text()).toContain('03 Jul 2026 09.41')
        expect(text()).toContain('10.10.2.14')
    })

    it('[BUD-ITEM-06] nilai lama → baru per kunci', async () => {
        await open(
            auditEntry({
                old: { total: 4_000_000, item_name: 'Sewa alat' },
                new: { total: 6_500_000, item_name: 'Sewa peralatan', volume: 2 },
            })
        )

        expect(text()).toMatch(/Rp\s4\.000\.000 → Rp\s6\.500\.000/)
        expect(text()).toContain('Sewa alat → Sewa peralatan')
        // Kunci yang baru muncul tidak punya nilai lama.
        expect(text()).toContain('— → 2')
    })

    it('entri tanpa nilai lama/baru', async () => {
        await open(auditEntry())

        expect(text()).toContain('Tidak ada perubahan nilai yang tercatat.')
    })

    it('entri tanpa IP tampil tanda pisah bagi Operator', async () => {
        await open(auditEntry({ ipAddress: null }))

        expect(text()).toMatch(/IP Address\s*—/)
    })

    it('tanpa cakupan penuh baris IP tidak digambar', async () => {
        await open(auditEntry({ ipAddress: null }), false)

        expect(text()).not.toContain('IP Address')
    })
})
