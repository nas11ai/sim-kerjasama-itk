import { beforeEach, describe, expect, it, vi } from 'vitest'

type Http = {
    image: File | null
    processing: boolean
    errors: Record<string, string>
    post: (url: string) => Promise<{ url: string }>
}
type Reply = (http: Http, url: string) => Promise<{ url: string }>

// Balasan palsu berupa fungsi biasa, bukan vi.fn: spy yang menolak promise
// dilaporkan Vitest sebagai galat walau pemanggilnya menangkapnya.
const { state } = vi.hoisted(() => ({
    state: { calls: [] as Array<[string, File | null]>, reply: null as Reply | null },
}))

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue')
    return {
        useHttp: (data: { image: File | null }) => {
            const http: Http = reactive({
                ...data,
                processing: false,
                errors: {},
                post: (url: string) => {
                    state.calls.push([url, http.image])
                    return state.reply === null
                        ? Promise.reject(new Error('tanpa balasan'))
                        : state.reply(http, url)
                },
            })
            return http
        },
    }
})

import { useInlineImageUpload } from '@/composables/useInlineImageUpload'

const file = new File(['x'], 'poster.png', { type: 'image/png' })

beforeEach(() => {
    state.calls = []
    state.reply = null
})

describe('useInlineImageUpload', () => {
    it('unggahan sukses mengembalikan url dari server', async () => {
        state.reply = async () => ({ url: '/storage/announcements/inline/abc.png' })
        const { upload } = useInlineImageUpload('/admin/announcements/images')

        const url = await upload(file)

        expect(url).toBe('/storage/announcements/inline/abc.png')
        expect(state.calls).toEqual([['/admin/announcements/images', file]])
    })

    it('galat validasi image ditampilkan dan hasilnya null', async () => {
        state.reply = async (http) => {
            http.errors = { image: 'Berkas harus berupa gambar.' }
            throw new Error('422')
        }
        const { upload, error } = useInlineImageUpload('/admin/announcements/images')

        expect(await upload(file)).toBeNull()
        expect(error.value).toBe('Berkas harus berupa gambar.')
    })
})
