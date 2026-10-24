import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { Toaster } from '@/Components/ui/toast'
import AppLayout from '@/Layouts/AppLayout.vue'

const { useFlashToasts } = vi.hoisted(() => ({ useFlashToasts: vi.fn() }))

vi.mock('@/composables/useFlashToasts', () => ({ useFlashToasts }))

// Sidebar dan topbar punya spec sendiri; di sini cukup kerangka layout.
const mountLayout = () =>
    mount(AppLayout, {
        slots: { default: '<p>Isi halaman</p>' },
        global: { stubs: { AppSidebar: true, AppTopbar: true } },
    })

describe('AppLayout', () => {
    it('merender isi halaman di dalam main', () => {
        expect(mountLayout().get('main').text()).toBe('Isi halaman')
    })

    it('memasang Toaster dan memanggil useFlashToasts sekali', () => {
        useFlashToasts.mockClear()
        const wrapper = mountLayout()

        expect(wrapper.findComponent(Toaster).exists()).toBe(true)
        expect(useFlashToasts).toHaveBeenCalledTimes(1)
    })
})
