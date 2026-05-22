import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

interface Notification {
    id: string
    title: string
    message: string
    action_url: string
    type: 'success' | 'info' | 'warning' | 'error'
    read_at: string | null
    created_at: string
}

export const useNotificationStore = defineStore('notification', () => {
    const notifications = ref<Notification[]>([])
    const unreadCount = computed(() =>
        notifications.value.filter(n => !n.read_at).length
    )

    async function fetchNotifications() {
        const res = await fetch('/api/notifications')
        notifications.value = await res.json()
    }

    function markAsRead(id: string) {
        const notif = notifications.value.find(n => n.id === id)
        if (notif) notif.read_at = new Date().toISOString()
    }

    return { notifications, unreadCount, fetchNotifications, markAsRead }
})
