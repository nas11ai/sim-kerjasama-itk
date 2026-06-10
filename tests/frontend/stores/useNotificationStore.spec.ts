// tests/frontend/stores/useNotificationStore.spec.ts
import { setActivePinia, createPinia } from 'pinia'
import { useNotificationStore } from '@/stores/useNotificationStore'

beforeEach(() => setActivePinia(createPinia()))

it('unreadCount returns correct count', () => {
    const store = useNotificationStore()
    store.notifications = [
        {
            id: '1',
            read_at: null,
            title: '',
            message: '',
            action_url: '',
            type: 'info',
            created_at: '',
        },
        {
            id: '2',
            read_at: '2025-01-01',
            title: '',
            message: '',
            action_url: '',
            type: 'info',
            created_at: '',
        },
    ]
    expect(store.unreadCount).toBe(1)
})
