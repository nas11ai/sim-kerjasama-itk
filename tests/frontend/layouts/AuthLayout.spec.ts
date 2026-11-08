import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { Toaster } from '@/Components/ui/toast'
import AuthLayout from '@/Layouts/AuthLayout.vue'

const { useFlashToasts } = vi.hoisted(() => ({ useFlashToasts: vi.fn() }))

vi.mock('@/composables/useFlashToasts', () => ({ useFlashToasts }))

describe('AuthLayout', () => {
    it('merender isi halaman di dalam kartu', () => {
        const wrapper = mount(AuthLayout, { slots: { default: '<p>Isi halaman</p>' } })

        expect(wrapper.text()).toContain('Isi halaman')
    })

    // W163 — toast "Sesi kedaluwarsa" dari 419 di form tamu (login, daftar).
    it('memasang Toaster dan memanggil useFlashToasts sekali', () => {
        useFlashToasts.mockClear()
        const wrapper = mount(AuthLayout)

        expect(wrapper.findComponent(Toaster).exists()).toBe(true)
        expect(useFlashToasts).toHaveBeenCalledTimes(1)
    })
})
