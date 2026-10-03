import { describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({
    router: { post: vi.fn(), visit: vi.fn() },
    usePage: () => ({ props: {} }),
}))

vi.mock('ziggy-js', () => ({
    route: (name: string, id?: number) => `${name}:${id}`,
}))

import { isOwnOpenThread } from '@/Components/ReviewSystem.vue'

const thread = (status: string, reviewerUserId?: number) => ({
    status,
    reviewer: reviewerUserId === undefined ? undefined : { user: { id: reviewerUserId } },
})

describe('isOwnOpenThread', () => {
    it('menawarkan penutupan ulir terbuka milik pengguna sendiri', () => {
        expect(isOwnOpenThread(thread('open', 5), 5)).toBe(true)
    })

    it('tidak menawarkan penutupan ulir reviewer lain', () => {
        expect(isOwnOpenThread(thread('open', 9), 5)).toBe(false)
    })

    it('tidak menawarkan apa pun untuk ulir yang sudah ditutup', () => {
        expect(isOwnOpenThread(thread('resolved', 5), 5)).toBe(false)
    })

    it('tidak menawarkan apa pun tanpa pengguna atau tanpa reviewer ulir', () => {
        expect(isOwnOpenThread(thread('open', 5), null)).toBe(false)
        expect(isOwnOpenThread(thread('open'), 5)).toBe(false)
    })
})
