import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import OperatorActions from '@/Components/Submission/Manage/OperatorActions.vue'
import type { OperatorAbilities } from '@/types/submission-manage'

const none: OperatorAbilities = {
    assignReviewers: false,
    replaceReviewer: false,
    grantOverride: false,
    transferOwnership: false,
    reject: false,
    withdraw: false,
}

const mountActions = (can: Partial<OperatorAbilities>, hasOwnershipCandidates = true) =>
    mount(OperatorActions, { props: { can: { ...none, ...can }, hasOwnershipCandidates } })

const button = (wrapper: ReturnType<typeof mountActions>, label: string) => {
    const found = wrapper.findAll('button').find((b) => b.text().includes(label))
    if (found === undefined) {
        throw new Error(`tombol "${label}" tidak ada`)
    }
    return found
}

describe('OperatorActions', () => {
    it('[SM-WITHDRAW-03] tarik tidak tersedia sebelum disetujui', () => {
        const wrapper = mountActions({ reject: true })

        expect(button(wrapper, 'Tarik Pengajuan').attributes('disabled')).toBeDefined()
        expect(wrapper.text()).toContain('Tersedia saat status Disetujui.')
    })

    it('alihkan tidak tersedia tanpa co-investigator aktif', () => {
        const wrapper = mountActions({ transferOwnership: true }, false)

        expect(button(wrapper, 'Alihkan Kepemilikan').attributes('disabled')).toBeDefined()
        expect(wrapper.text()).toContain('Perlu anggota tim inti yang aktif.')
    })

    it('aksi tersedia memancarkan kuncinya', async () => {
        const wrapper = mountActions({
            assignReviewers: true,
            grantOverride: true,
            transferOwnership: true,
            reject: true,
            withdraw: true,
        })

        for (const label of [
            'Tugaskan Reviewer',
            'Perpanjang Tenggat',
            'Alihkan Kepemilikan',
            'Tolak Manual',
            'Tarik Pengajuan',
        ]) {
            await button(wrapper, label).trigger('click')
        }

        expect(wrapper.emitted('select')).toEqual([
            ['assign'],
            ['extend'],
            ['transfer'],
            ['reject'],
            ['withdraw'],
        ])
    })

    it('menyatakan kunci status terminal', () => {
        expect(mountActions({}).text()).toContain(
            'Pengajuan yang sudah Disetujui, Ditolak, atau Ditarik tidak dapat diubah lagi.'
        )
    })
})
