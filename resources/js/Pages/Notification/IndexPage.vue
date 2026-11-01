<script setup lang="ts">
// Frame: SHARED-02-05
import { Head, router } from '@inertiajs/vue3'
import { Bell } from 'lucide-vue-next'
import { ref } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import EmptyState from '@/Components/app/EmptyState.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import PaginationBar from '@/Components/app/PaginationBar.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import NotificationItem from '@/Components/Notification/NotificationItem.vue'
import { Button } from '@/Components/ui/button'
import { useFilters } from '@/composables/useFilters'
import AppLayout from '@/Layouts/AppLayout.vue'
import type {
    NotificationFilter,
    NotificationIndexPageProps,
    NotificationRow,
} from '@/types/notification'

const props = defineProps<NotificationIndexPageProps>()

const chips: { value: NotificationFilter; label: string }[] = [
    { value: 'all', label: 'Semua' },
    { value: 'unread', label: 'Belum dibaca' },
    { value: 'review', label: 'Review' },
    { value: 'monev', label: 'Monev' },
]

const { filters } = useFilters('notifications.index', { filter: props.filter })

// BR-NOT-04 — read_at hanya terisi lewat klik "Buka"; server lalu mengarahkan ke tujuannya.
const open = (notification: NotificationRow) =>
    router.post(route('notifications.read', notification.id))

const readAll = () => router.post(route('notifications.read-all'), {}, { preserveScroll: true })

const removing = ref<NotificationRow | null>(null)
const confirmOpen = ref(false)
const processing = ref(false)

function askRemove(notification: NotificationRow) {
    removing.value = notification
    confirmOpen.value = true
}

function remove() {
    if (removing.value === null) {
        return
    }
    router.delete(route('notifications.destroy', removing.value.id), {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => {
            processing.value = false
            confirmOpen.value = false
        },
    })
}
</script>

<template>
    <Head title="Notifikasi" />
    <AppLayout>
        <PageHeader
            title="Notifikasi"
            subtitle="Pemberitahuan tersimpan permanen sampai Anda menghapusnya sendiri."
        />
        <div class="flex max-w-4xl flex-col gap-6">
            <SectionCard title="Semua Notifikasi">
                <template #actions>
                    <Button v-if="unreadCount > 0" size="sm" variant="outline" @click="readAll">
                        Tandai Semua Dibaca
                    </Button>
                </template>
                <div class="flex flex-wrap gap-2" role="group" aria-label="Saring notifikasi">
                    <Button
                        v-for="chip in chips"
                        :key="chip.value"
                        size="sm"
                        :variant="filter === chip.value ? 'secondary' : 'ghost'"
                        :aria-pressed="filter === chip.value"
                        @click="filters.filter = chip.value"
                    >
                        {{ chip.label }}
                    </Button>
                </div>
                <div>
                    <NotificationItem
                        v-for="notification in notifications.data"
                        :key="notification.id"
                        :notification="notification"
                        @open="open"
                        @remove="askRemove"
                    />
                    <EmptyState
                        v-if="notifications.data.length === 0"
                        :icon="Bell"
                        title="Belum ada notifikasi"
                    />
                </div>
                <PaginationBar :page="notifications" />
            </SectionCard>
            <SectionCard title="Bagaimana Notifikasi Bekerja">
                <AppBanner tone="info">
                    Notifikasi ditandai sudah dibaca saat Anda membukanya — bukan saat halaman ini
                    dibuka. Penghitung "belum dibaca" karenanya tetap akurat.
                </AppBanner>
                <AppBanner tone="info">
                    Peristiwa yang butuh perhatian segera dikirim lewat email sekaligus. Peristiwa
                    ringan seperti diskusi revisi yang selesai cukup muncul di sini.
                </AppBanner>
            </SectionCard>
        </div>
        <ConfirmDialog
            v-model:open="confirmOpen"
            title="Hapus notifikasi"
            description="Notifikasi yang dihapus tidak bisa dikembalikan."
            confirm-label="Hapus"
            destructive
            :processing="processing"
            @confirm="remove"
        />
    </AppLayout>
</template>
