import { effectScope } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const { toast, page, handlers } = vi.hoisted(() => ({
    toast: vi.fn(),
    page: { props: {} as Record<string, unknown> },
    handlers: new Map<string, Set<(event: unknown) => void>>(),
}))

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
    router: {
        on: (type: string, callback: (event: unknown) => void) => {
            const set = handlers.get(type) ?? new Set()
            set.add(callback)
            handlers.set(type, set)
            return () => set.delete(callback)
        },
    },
}))
vi.mock('@/Components/ui/toast', () => ({ toast }))

import { useFlashToasts } from '@/composables/useFlashToasts'

// Meniru Inertia: event 'flash' muncul untuk halaman awal dan respons server,
// tidak untuk pemulihan riwayat. 'finish' muncul untuk semua request.
const emit = (type: string, detail: unknown = {}) =>
    handlers.get(type)?.forEach((callback) => callback({ detail }))

const flash = (data: Record<string, unknown>) => emit('flash', { flash: data })

let scope: ReturnType<typeof effectScope>

const start = () => {
    scope = effectScope()
    scope.run(() => useFlashToasts())
}

beforeEach(() => {
    page.props = { flash: { success: 'Lama', error: null, message: null, workloadWarnings: null } }
})

afterEach(() => {
    scope.stop()
    toast.mockClear()
    handlers.clear()
})

describe('useFlashToasts', () => {
    it('menampilkan flash dari event flash', () => {
        start()
        flash({ success: 'Tersimpan' })

        expect(toast).toHaveBeenCalledExactlyOnceWith({ title: 'Tersimpan' })
    })

    it('memakai varian destructive untuk flash error', () => {
        start()
        flash({ error: 'Gagal' })

        expect(toast).toHaveBeenCalledWith({ title: 'Gagal', variant: 'destructive' })
    })

    it('menampilkan toast untuk flash message', () => {
        start()
        flash({ message: 'Info' })

        expect(toast).toHaveBeenCalledWith({ title: 'Info' })
    })

    it('tidak menoast saat setup walau props.flash legacy terisi', () => {
        start()

        expect(toast).not.toHaveBeenCalled()
    })

    it('tidak menoast saat finish, mis. partial reload sesudah Back', () => {
        start()
        emit('finish')

        expect(toast).not.toHaveBeenCalled()
    })

    it('flash identik dua kali berturut-turut tetap dua toast', () => {
        start()
        flash({ success: 'Tersimpan' })
        flash({ success: 'Tersimpan' })

        expect(toast).toHaveBeenCalledTimes(2)
    })

    it('mengabaikan nilai kosong', () => {
        start()
        flash({ success: '', error: undefined })

        expect(toast).not.toHaveBeenCalled()
    })

    it('berhenti mendengarkan saat scope dihentikan', () => {
        start()
        scope.stop()
        flash({ success: 'Tersimpan' })

        expect(toast).not.toHaveBeenCalled()
    })
})
