import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { router } = vi.hoisted(() => ({
    router: { post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

vi.mock('@inertiajs/vue3', () => ({ router }))

import ReferenceItemsDialog from '@/Components/SystemConfig/ReferenceItemsDialog.vue'
import type { ReferenceCategoryData, ReferenceItemData } from '@/types/system-config'

const item = (overrides: Partial<ReferenceItemData> = {}): ReferenceItemData => ({
    id: 11,
    name: 'Penelitian Dasar',
    isActive: true,
    usageCount: 0,
    ...overrides,
})

const category = (overrides: Partial<ReferenceCategoryData> = {}): ReferenceCategoryData => ({
    key: 'scheme-types',
    label: 'Tipe Skema',
    group: 'scheme',
    usedBy: 'Skema',
    editable: true,
    deactivatable: true,
    items: [item()],
    ...overrides,
})

const route = (name: string, params?: Record<string, string | number>) =>
    params === undefined ? `/${name}` : `/${name}/${Object.values(params).join('/')}`

const mountDialog = (value: ReferenceCategoryData) =>
    mount(ReferenceItemsDialog, {
        props: { category: value, open: true },
        global: { mocks: { route } },
        attachTo: document.body,
    })

const bodyText = () => document.body.textContent ?? ''
const bodyButton = (label: string) =>
    Array.from(document.querySelectorAll('button')).find((b) => b.textContent?.trim() === label)

beforeEach(() => {
    vi.stubGlobal('route', route)
    document.body.innerHTML = ''
    Object.values(router).forEach((fn) => fn.mockClear())
})

describe('ReferenceItemsDialog', () => {
    it('[SC-CONFIG-01] hapus terkunci dan menyebut jumlah pemakainya', async () => {
        mountDialog(category({ items: [item({ usageCount: 7 })] }))
        await flushPromises()

        expect(bodyText()).toContain('dipakai 7')
        expect(bodyButton('Hapus')?.disabled).toBe(true)
        expect(bodyText()).toContain(
            'Tidak dapat dihapus: masih dipakai 7 Skema. Nonaktifkan saja agar tidak lagi ditawarkan.'
        )
        expect(bodyButton('Nonaktifkan')?.disabled).toBe(false)
    })

    it('[SC-CONFIG-02] item tanpa pemakai bisa dihapus lewat konfirmasi', async () => {
        mountDialog(category())
        await flushPromises()

        bodyButton('Hapus')?.click()
        await flushPromises()
        expect(router.delete).not.toHaveBeenCalled()
        expect(bodyText()).toContain('Hapus item?')

        bodyButton('Hapus Item')?.click()
        await flushPromises()
        expect(router.delete).toHaveBeenCalledWith(
            '/admin.reference.destroy/scheme-types/11',
            expect.anything()
        )
    })

    it('[SC-CONFIG-03] tipe submission tidak punya aksi', async () => {
        mountDialog(
            category({
                key: 'submission-types',
                label: 'Tipe Submission',
                editable: false,
                items: [item({ name: 'Penelitian', usageCount: 4 })],
            })
        )
        await flushPromises()

        expect(bodyText()).toContain(
            'Tipe Submission hanya dapat diubah lewat seeder. Bukan kekurangan antarmuka — nilainya mengikat empat context sekaligus.'
        )
        for (const label of ['Ganti Nama', 'Nonaktifkan', 'Hapus']) {
            expect(bodyButton(label)).toBeUndefined()
        }
    })

    it('[SC-CONFIG-08] menonaktifkan memanggil toggle dengan is_active false', async () => {
        mountDialog(category({ items: [item({ usageCount: 3 })] }))
        await flushPromises()

        bodyButton('Nonaktifkan')?.click()
        await flushPromises()
        expect(router.post).toHaveBeenCalledWith(
            '/admin.reference.toggle/scheme-types/11',
            { is_active: false },
            expect.anything()
        )
    })

    it('kategori tanpa is_active tidak menawarkan Nonaktifkan', async () => {
        mountDialog(
            category({ key: 'trls', deactivatable: false, items: [item({ isActive: null })] })
        )
        await flushPromises()

        expect(bodyButton('Nonaktifkan')).toBeUndefined()
        expect(bodyButton('Aktifkan')).toBeUndefined()
    })

    it('[SC-CONFIG-04] ganti nama menampilkan peringatan laporan lama', async () => {
        mountDialog(category())
        await flushPromises()

        bodyButton('Ganti Nama')?.click()
        await flushPromises()
        expect(bodyText()).toContain(
            'Nama diperbarui di seluruh laporan, termasuk yang lama. Pakai ini hanya kalau memang satu hal yang sama dengan sebutan yang diperbaiki.'
        )

        const input = document.querySelector<HTMLInputElement>('#reference-rename-11')
        expect(input?.value).toBe('Penelitian Dasar')
        if (input) {
            input.value = 'Riset Dasar'
            input.dispatchEvent(new Event('input', { bubbles: true }))
        }
        await flushPromises()
        bodyButton('Simpan Nama')?.click()
        await flushPromises()
        expect(router.put).toHaveBeenCalledWith(
            '/admin.reference.update/scheme-types/11',
            { name: 'Riset Dasar' },
            expect.anything()
        )
    })
})
