import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'

import { Toaster, toast, useToast } from '@/Components/ui/toast'

// Store toast memakai shallowRef: hanya penggantian `.value` utuh yang memicu render.
// Menyunting properti di dalamnya membuat Toaster tak pernah menggambar apa pun.
let wrapper: ReturnType<typeof mount>

afterEach(() => {
    useToast().dismiss()
    wrapper.unmount()
})

describe('Toaster', () => {
    it('menggambar toast yang dipanggil setelah Toaster terpasang', async () => {
        wrapper = mount(Toaster, { attachTo: document.body })

        toast({ title: 'Berhasil memperbarui profil.' })
        await flushPromises()

        expect(document.body.textContent).toContain('Berhasil memperbarui profil.')
    })

    it('menyembunyikan toast yang ditutup', async () => {
        wrapper = mount(Toaster, { attachTo: document.body })
        const { dismiss } = toast({ title: 'Akan ditutup' })
        await flushPromises()

        dismiss()
        await flushPromises()

        expect(useToast().toasts.value.every((t) => t.open === false)).toBe(true)
    })
})
