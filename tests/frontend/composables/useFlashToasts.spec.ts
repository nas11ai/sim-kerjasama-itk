import { effectScope, nextTick, reactive } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const { toast, page, handlers } = vi.hoisted(() => ({
    toast: vi.fn(),
    page: { props: {} as Record<string, unknown> },
    handlers: new Map<string, Set<() => void>>(),
}))

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
    router: {
        on: (type: string, callback: () => void) => {
            const set = handlers.get(type) ?? new Set()
            set.add(callback)
            handlers.set(type, set)
            return () => set.delete(callback)
        },
    },
}))
vi.mock('@/Components/ui/toast', () => ({ toast }))

import { useFlashToasts } from '@/composables/useFlashToasts'

const empty = { message: null, error: null, success: null, workloadWarnings: null }

// props reaktif seperti usePage() asli; tiap respons menimpa objek flash.
const props = () => page.props as { flash: Record<string, unknown> }
const setFlash = (flash: Record<string, unknown>) => {
    props().flash = { ...empty, ...flash }
}

// Meniru Inertia: respons server menimpa props lalu memicu 'finish';
// pemulihan riwayat (popstate) hanya menukar page dan memicu 'navigate'.
const emit = (type: string) => handlers.get(type)?.forEach((callback) => callback())

const serverResponse = (flash: Record<string, unknown>) => {
    setFlash(flash)
    emit('finish')
}

let scope: ReturnType<typeof effectScope>

const start = () => {
    scope = effectScope()
    scope.run(() => useFlashToasts())
}

beforeEach(() => {
    page.props = reactive({ flash: { ...empty } })
})

afterEach(() => {
    scope.stop()
    toast.mockClear()
    handlers.clear()
})

describe('useFlashToasts', () => {
    it('menampilkan flash halaman awal sekali', () => {
        setFlash({ success: 'Tersimpan' })
        start()
        emit('finish')

        expect(toast).toHaveBeenCalledTimes(1)
        expect(toast).toHaveBeenCalledWith({ title: 'Tersimpan' })
    })

    it('menampilkan flash dari respons server', () => {
        start()
        serverResponse({ success: 'Tersimpan' })

        expect(toast).toHaveBeenCalledWith({ title: 'Tersimpan' })
    })

    it('memakai varian destructive untuk flash error', () => {
        start()
        serverResponse({ error: 'Gagal' })

        expect(toast).toHaveBeenCalledWith({ title: 'Gagal', variant: 'destructive' })
    })

    it('menampilkan toast untuk flash message', () => {
        start()
        serverResponse({ message: 'Info' })

        expect(toast).toHaveBeenCalledWith({ title: 'Info' })
    })

    it('teks sama pada dua respons server berturut-turut tampil dua kali', () => {
        start()
        serverResponse({ success: 'Tersimpan' })
        serverResponse({ success: 'Tersimpan' })

        expect(toast).toHaveBeenCalledTimes(2)
    })

    it('mengosongkan flash yang sudah tampil supaya respons berikutnya tidak dianggap sama', () => {
        start()
        serverResponse({ success: 'Tersimpan', workloadWarnings: ['x'] })

        expect(props().flash).toEqual({ ...empty, workloadWarnings: ['x'] })
    })

    it('partial reload tanpa flash baru tidak menampilkan ulang', () => {
        start()
        serverResponse({ success: 'Tersimpan' })
        emit('finish')

        expect(toast).toHaveBeenCalledTimes(1)
    })

    it('pemulihan riwayat lewat Back/Forward tidak menampilkan ulang', async () => {
        start()
        setFlash({ success: 'Tersimpan' })
        emit('navigate')
        await nextTick()

        expect(toast).not.toHaveBeenCalled()
    })

    it('mengabaikan nilai null dan string kosong serta workloadWarnings', () => {
        start()
        serverResponse({ success: '', workloadWarnings: ['x'] })

        expect(toast).not.toHaveBeenCalled()
    })

    it('berhenti mendengarkan saat layout dilepas', () => {
        start()
        scope.stop()
        serverResponse({ success: 'Tersimpan' })

        expect(toast).not.toHaveBeenCalled()
    })
})
