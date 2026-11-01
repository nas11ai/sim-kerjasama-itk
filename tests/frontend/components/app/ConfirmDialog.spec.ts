import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'

import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'

const props = {
    open: true,
    title: 'Hapus pengajuan?',
    description: 'Tidak bisa dibatalkan.',
    confirmLabel: 'Hapus',
}

// Konten dialog dirender lewat portal ke body, jadi dicari dari document.
const button = (label: string) =>
    Array.from(document.querySelectorAll('button')).find((b) => b.textContent?.trim() === label)

let wrapper: ReturnType<typeof mount>

const mountDialog = async (extra: Record<string, unknown> = {}) => {
    wrapper = mount(ConfirmDialog, { props: { ...props, ...extra }, attachTo: document.body })
    await flushPromises()
}

afterEach(() => wrapper.unmount())

describe('ConfirmDialog', () => {
    it('menampilkan judul dan deskripsi', async () => {
        await mountDialog()

        expect(document.body.textContent).toContain('Hapus pengajuan?')
        expect(document.body.textContent).toContain('Tidak bisa dibatalkan.')
    })

    it('emit confirm sekali saat tombol konfirmasi diklik', async () => {
        await mountDialog()

        button('Hapus')?.click()

        expect(wrapper.emitted('confirm')).toHaveLength(1)
    })

    it('emit update:open false tanpa confirm saat batal diklik', async () => {
        await mountDialog()

        button('Batal')?.click()

        expect(wrapper.emitted('update:open')).toEqual([[false]])
        expect(wrapper.emitted('confirm')).toBeUndefined()
    })

    it('menonaktifkan tombol konfirmasi selama processing', async () => {
        await mountDialog({ processing: true })

        expect(button('Hapus')?.disabled).toBe(true)
    })

    it('tidak bisa ditutup selama proses', async () => {
        await mountDialog({ processing: true })

        expect(button('Batal')?.disabled).toBe(true)
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
        await flushPromises()

        expect(wrapper.emitted('update:open')).toBeUndefined()
    })
})
