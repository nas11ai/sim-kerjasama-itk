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

    // E2E-01 — tanpa min-w-0 pada anak baris flex (SidebarInset, yang merender <main>), tabel
    // lebar mendorong seluruh halaman ke samping di 390px.
    it('satu-satunya main boleh menyusut di bawah lebar isinya', () => {
        const mains = mountLayout().findAll('main')

        expect(mains).toHaveLength(1)
        expect(mains[0].classes()).toContain('min-w-0')
    })

    it('memasang Toaster dan memanggil useFlashToasts sekali', () => {
        useFlashToasts.mockClear()
        const wrapper = mountLayout()

        expect(wrapper.findComponent(Toaster).exists()).toBe(true)
        expect(useFlashToasts).toHaveBeenCalledTimes(1)
    })
})
