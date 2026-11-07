import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { router } = vi.hoisted(() => ({
    router: { get: vi.fn(), post: vi.fn(), delete: vi.fn(), reload: vi.fn() },
}))

vi.mock('@inertiajs/vue3', () => ({ router }))

import OrganizationUnitPanel from '@/Components/SystemConfig/OrganizationUnitPanel.vue'
import type { OrganizationCensusData, OrganizationUnitData } from '@/types/system-config'

const census = (overrides: Partial<OrganizationCensusData> = {}): OrganizationCensusData => ({
    directProfiles: 0,
    directActiveUsers: 0,
    subtreeProfiles: 0,
    subtreeActiveUsers: 0,
    unitsWithActiveUsers: 0,
    pendingInvitations: 0,
    ...overrides,
})

const unit = (
    overrides: Partial<OrganizationUnitData> = {},
    counts: Partial<OrganizationCensusData> = {}
): OrganizationUnitData => ({
    id: 3,
    name: 'Teknik Mesin',
    type: 'study_program',
    typeLabel: 'Program Studi',
    isActive: true,
    parentId: 2,
    census: census(counts),
    canDeactivate: true,
    canDelete: true,
    canReactivate: false,
    revivableInvitations: 0,
    ...overrides,
})

const route = (name: string, id?: number) => (id === undefined ? `/${name}` : `/${name}/${id}`)

const mountPanel = async (value: OrganizationUnitData) => {
    const wrapper = mount(OrganizationUnitPanel, {
        props: { unit: value },
        global: { mocks: { route } },
        attachTo: document.body,
    })
    await flushPromises()
    return wrapper
}

const bodyText = () => document.body.textContent ?? ''
const bodyButton = (label: string) =>
    Array.from(document.querySelectorAll('button')).find((b) => b.textContent?.trim() === label)

beforeEach(() => {
    vi.stubGlobal('route', route)
    document.body.innerHTML = ''
    Object.values(router).forEach((fn) => fn.mockClear())
})

describe('OrganizationUnitPanel', () => {
    it('[IAM-ORG-04] nonaktifkan terhalang menyebut jumlah user aktif', async () => {
        await mountPanel(
            unit(
                { canDeactivate: false, canDelete: false },
                {
                    directProfiles: 14,
                    directActiveUsers: 14,
                    subtreeProfiles: 14,
                    subtreeActiveUsers: 14,
                }
            )
        )

        expect(bodyButton('Nonaktifkan')?.disabled).toBe(true)
        expect(bodyText()).toContain(
            'Terhalang 14 user aktif — pindahkan atau nonaktifkan orangnya lebih dulu.'
        )
    })

    it('[IAM-ORG-05] user aktif di unit turunan ikut disebut', async () => {
        await mountPanel(
            unit(
                { canDeactivate: false, canDelete: false },
                { subtreeProfiles: 52, subtreeActiveUsers: 38, unitsWithActiveUsers: 4 }
            )
        )

        expect(bodyText()).toContain('Terhalang 38 user aktif, tersebar di 4 unit turunan')
    })

    it('[IAM-ORG-01] hapus terhalang menyebut jumlah profil', async () => {
        await mountPanel(
            unit(
                { canDeactivate: false, canDelete: false },
                { subtreeProfiles: 14, subtreeActiveUsers: 14 }
            )
        )

        expect(bodyButton('Hapus Unit')?.disabled).toBe(true)
        expect(bodyText()).toContain(
            'Terhalang 14 profil user. Penghapusan menuntut subtree benar-benar kosong — termasuk profil yang user-nya sudah nonaktif.'
        )
    })

    it('[IAM-ORG-07] unit tanpa user aktif bisa dinonaktifkan tapi belum bisa dihapus', async () => {
        await mountPanel(unit({ canDeactivate: true, canDelete: false }, { subtreeProfiles: 11 }))

        expect(bodyButton('Nonaktifkan')?.disabled).toBe(false)
        expect(bodyButton('Hapus Unit')?.disabled).toBe(true)

        bodyButton('Nonaktifkan')?.click()
        await flushPromises()

        expect(bodyText()).toContain(
            'Unit ini akan dinonaktifkan, bukan dihapus. 11 profil dan seluruh riwayat pengajuannya tetap terbaca.'
        )
    })

    it('[IAM-ORG-08] konfirmasi nonaktifkan menyebut putusnya rantai akses Form', async () => {
        await mountPanel(unit({}, { pendingInvitations: 2 }))

        bodyButton('Nonaktifkan')?.click()
        await flushPromises()

        expect(bodyText()).toContain('Nonaktifkan unit?')
        expect(bodyText()).toContain(
            'Unit beserta turunannya berhenti ditawarkan dan memutus rantai akses Form bagi unit di bawahnya.'
        )
        expect(bodyText()).toContain('2 undangan registrasi ikut tidak berlaku.')

        const confirm = Array.from(document.querySelectorAll('[role="dialog"] button')).find(
            (b) => b.textContent?.trim() === 'Nonaktifkan'
        )
        ;(confirm as HTMLButtonElement | undefined)?.click()

        expect(router.post).toHaveBeenCalledWith(
            '/admin.organizations.deactivate/3',
            {},
            expect.objectContaining({ preserveScroll: true })
        )
    })

    it('[IAM-REG-10] konfirmasi hapus menyebut undangan yang tercabut', async () => {
        await mountPanel(unit({}, { pendingInvitations: 2 }))

        bodyButton('Hapus Unit')?.click()
        await flushPromises()

        expect(bodyText()).toContain('Hapus unit?')
        expect(bodyText()).toContain(
            'Menghapus unit ini akan mencabut 2 undangan registrasi yang belum terpakai. Siapa pun yang membuka tautannya akan melihat halaman undangan tidak berlaku.'
        )

        bodyButton('Hapus Unit & Cabut 2 Undangan')?.click()

        expect(router.delete).toHaveBeenCalledWith(
            '/admin.organizations.destroy/3',
            expect.objectContaining({ preserveScroll: true })
        )
    })

    it('unit nonaktif tidak menawarkan Nonaktifkan', async () => {
        await mountPanel(unit({ isActive: false, canDeactivate: false }))

        expect(bodyButton('Nonaktifkan')).toBeUndefined()
        expect(bodyButton('Hapus Unit')).toBeDefined()
    })

    it('[IAM-ORG-18] aktifkan kembali terhalang induk nonaktif', async () => {
        await mountPanel(unit({ isActive: false, canDeactivate: false, canReactivate: false }))

        expect(bodyButton('Aktifkan Kembali')?.disabled).toBe(true)
        expect(bodyText()).toContain('Induk unit ini masih nonaktif. Aktifkan induknya lebih dulu.')
    })

    it('[IAM-ORG-20] konfirmasi aktifkan kembali menyebut undangan yang berlaku lagi', async () => {
        await mountPanel(
            unit({
                isActive: false,
                canDeactivate: false,
                canReactivate: true,
                revivableInvitations: 2,
            })
        )

        expect(bodyText()).toContain(
            'Hanya unit ini yang aktif kembali; unit turunannya tetap nonaktif.'
        )
        bodyButton('Aktifkan Kembali')?.click()
        await flushPromises()

        expect(bodyText()).toContain('Aktifkan kembali unit?')
        expect(bodyText()).toContain(
            'Unit ini kembali ditawarkan dan rantai akses Form-nya tersambung lagi. Unit turunannya tetap nonaktif; aktifkan satu per satu bila perlu.'
        )
        expect(bodyText()).toContain('2 undangan registrasi yang belum kedaluwarsa berlaku lagi.')

        const confirm = Array.from(document.querySelectorAll('[role="dialog"] button')).find(
            (b) => b.textContent?.trim() === 'Aktifkan Kembali'
        )
        ;(confirm as HTMLButtonElement | undefined)?.click()

        expect(router.post).toHaveBeenCalledWith(
            '/admin.organizations.reactivate/3',
            {},
            expect.objectContaining({ preserveScroll: true })
        )
    })

    it('unit aktif tidak menawarkan Aktifkan Kembali', async () => {
        await mountPanel(unit())

        expect(bodyButton('Aktifkan Kembali')).toBeUndefined()
    })

    it('Pindahkan User hanya ditawarkan bila subtree berisi profil', async () => {
        await mountPanel(unit())
        expect(bodyButton('Pindahkan User')).toBeUndefined()

        document.body.innerHTML = ''
        await mountPanel(unit({}, { subtreeProfiles: 3 }))
        expect(bodyButton('Pindahkan User')).toBeDefined()
    })
})
