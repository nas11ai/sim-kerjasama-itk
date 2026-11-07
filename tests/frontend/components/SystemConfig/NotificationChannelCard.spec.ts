import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { router } = vi.hoisted(() => ({ router: { put: vi.fn() } }))

vi.mock('@inertiajs/vue3', () => ({ router }))

import NotificationChannelCard from '@/Components/SystemConfig/NotificationChannelCard.vue'
import type { NotificationChannelData } from '@/types/system-config'

const options = [
    { value: 'database', label: 'In-app' },
    { value: 'mail', label: 'Email' },
]

const row = (overrides: Partial<NotificationChannelData> = {}): NotificationChannelData => ({
    eventType: 'RevisionResolved',
    channels: ['database'],
    declared: true,
    ...overrides,
})

const route = (name: string, param?: string) =>
    param === undefined ? `/${name}` : `/${name}/${param}`

const mountCard = (rows: NotificationChannelData[]) =>
    mount(NotificationChannelCard, { props: { rows, options }, global: { mocks: { route } } })

const checkbox = (wrapper: ReturnType<typeof mountCard>, id: string) => wrapper.find(`#${id}`)

beforeEach(() => {
    vi.stubGlobal('route', route)
    router.put.mockClear()
})

describe('NotificationChannelCard', () => {
    it('[NOT-CHANNEL-03] mencentang email mengirim deklarasi baru', async () => {
        const wrapper = mountCard([row()])

        await checkbox(wrapper, 'channel-RevisionResolved-mail').trigger('click')
        await flushPromises()

        expect(router.put).toHaveBeenCalledWith(
            '/admin.reference.notification-channels.update/RevisionResolved',
            { channels: ['database', 'mail'] },
            expect.objectContaining({ preserveScroll: true })
        )
    })

    it('[NOT-CHANNEL-07] checkbox terakhir tidak bisa dilepas', async () => {
        const wrapper = mountCard([row()])

        expect(
            checkbox(wrapper, 'channel-RevisionResolved-database').attributes('disabled')
        ).toBeDefined()
        expect(
            checkbox(wrapper, 'channel-RevisionResolved-mail').attributes('disabled')
        ).toBeUndefined()

        await checkbox(wrapper, 'channel-RevisionResolved-database').trigger('click')
        await flushPromises()
        expect(router.put).not.toHaveBeenCalled()
    })

    it('[NOT-CHANNEL-04] event tanpa deklarasi menampilkan banner dan Tetapkan Channel', async () => {
        const wrapper = mountCard([
            row({ eventType: 'FormChanged', channels: [], declared: false }),
        ])

        expect(wrapper.text()).toContain(
            'Event ini belum punya deklarasi channel. Tetapkan in-app, email, atau keduanya sebelum event dipakai.'
        )
        const assign = () =>
            wrapper.findAll('button').find((b) => b.text().includes('Tetapkan Channel'))
        expect(assign()?.attributes('disabled')).toBeDefined()

        // Event belum berdeklarasi: centang hanya memilih, belum mengirim.
        await checkbox(wrapper, 'channel-FormChanged-database').trigger('click')
        await flushPromises()
        expect(router.put).not.toHaveBeenCalled()

        await assign()?.trigger('click')
        await flushPromises()
        expect(router.put).toHaveBeenCalledWith(
            '/admin.reference.notification-channels.update/FormChanged',
            { channels: ['database'] },
            expect.anything()
        )
    })
})
