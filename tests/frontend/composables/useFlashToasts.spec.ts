import { nextTick, reactive } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { toast, page } = vi.hoisted(() => ({
    toast: vi.fn(),
    page: { props: {} as Record<string, unknown> },
}))

vi.mock('@inertiajs/vue3', () => ({ usePage: () => page }))
vi.mock('@/Components/ui/toast', () => ({ toast }))

import { useFlashToasts } from '@/composables/useFlashToasts'

const empty = { message: null, error: null, success: null, workloadWarnings: null }

const setFlash = (flash: Record<string, unknown>) => {
    page.props = reactive({ flash: { ...empty, ...flash } })
}

beforeEach(() => toast.mockClear())

describe('useFlashToasts', () => {
    it('menampilkan toast untuk flash success', () => {
        setFlash({ success: 'Tersimpan' })
        useFlashToasts()

        expect(toast).toHaveBeenCalledWith({ title: 'Tersimpan' })
    })

    it('memakai varian destructive untuk flash error', () => {
        setFlash({ error: 'Gagal' })
        useFlashToasts()

        expect(toast).toHaveBeenCalledWith({ title: 'Gagal', variant: 'destructive' })
    })

    it('menampilkan toast untuk flash message', () => {
        setFlash({ message: 'Info' })
        useFlashToasts()

        expect(toast).toHaveBeenCalledWith({ title: 'Info' })
    })

    it('mengabaikan nilai null dan string kosong serta workloadWarnings', () => {
        setFlash({ success: '', workloadWarnings: ['x'] })
        useFlashToasts()

        expect(toast).not.toHaveBeenCalled()
    })

    it('menampilkan toast lagi untuk objek flash baru dengan teks sama', async () => {
        setFlash({ success: 'Tersimpan' })
        useFlashToasts()
        const props = page.props as { flash: unknown }

        props.flash = { ...empty, success: 'Tersimpan' }
        await nextTick()

        expect(toast).toHaveBeenCalledTimes(2)
    })
})
