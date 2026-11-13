import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import NotificationItem from '@/Components/Notification/NotificationItem.vue'
import type { NotificationRow } from '@/types/notification'

const row: NotificationRow = {
    id: 'a1',
    title: 'Penugasan penilaian baru',
    message: 'Anda ditugaskan untuk mengevaluasi sebuah pengajuan.',
    category: 'review',
    categoryLabel: 'Penilaian',
    createdAt: '2026-07-03T10:00:00',
    isRead: false,
}

const mountItem = (notification: NotificationRow) =>
    mount(NotificationItem, { props: { notification } })

beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-07-03T12:00:00'))
})

afterEach(() => {
    vi.useRealTimers()
})

describe('NotificationItem', () => {
    it('menampilkan meta "<kategori> · <waktu relatif>"', () => {
        expect(mountItem(row).text()).toContain('Penilaian · 2 jam lalu')
    })

    it('badge mengikuti isRead', () => {
        expect(mountItem(row).text()).toContain('Belum dibaca')
        const read = mountItem({ ...row, isRead: true }).text()
        expect(read).toContain('Dibaca')
        expect(read).not.toContain('Belum dibaca')
    })

    it('Buka dan Hapus memancarkan event dengan notifikasinya', async () => {
        const wrapper = mountItem(row)
        const button = (label: string) => wrapper.findAll('button').find((b) => b.text() === label)

        await button('Buka')?.trigger('click')
        await button('Hapus')?.trigger('click')

        expect(wrapper.emitted('open')).toEqual([[row]])
        expect(wrapper.emitted('remove')).toEqual([[row]])
    })
})
