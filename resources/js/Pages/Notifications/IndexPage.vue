<script setup lang="ts">
/**
 * BR-NOT-01, BR-NOT-04 — daftar notifikasi in-app.
 *
 * Membuka halaman ini tidak menandai apa pun sebagai dibaca. Penandaan
 * terjadi saat sebuah notifikasi diklik — kalau tidak, penghitung
 * "belum dibaca" langsung nol pada kunjungan pertama dan yang belum sempat
 * dilihat ikut terkubur.
 *
 * Tidak ada tombol "tandai semua dibaca", dan itu alasan yang sama.
 */
import { computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Bell, ChevronLeft, ChevronRight, Inbox, Trash2 } from 'lucide-vue-next'

interface NotificationRow {
    id: string
    type: string
    data: Record<string, unknown>
    read_at: string | null
    created_at: string | null
}

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

const props = defineProps<{
    notifications: {
        data: NotificationRow[]
        links: PaginationLink[]
        from: number | null
        to: number | null
        total: number
    }
    unreadCount: number
}>()

const prevLink = computed(() => props.notifications.links[0]?.url ?? null)
const nextLink = computed(
    () => props.notifications.links[props.notifications.links.length - 1]?.url ?? null
)

const title = (row: NotificationRow) =>
    typeof row.data.title === 'string' ? row.data.title : row.type

const message = (row: NotificationRow) =>
    typeof row.data.message === 'string' ? row.data.message : ''

const formatTime = (value: string | null) => {
    if (!value) {
        return '—'
    }

    return new Date(value).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

function open(row: NotificationRow) {
    router.post(route('notifications.read', row.id), {}, { preserveScroll: true })
}

function destroy(row: NotificationRow) {
    router.delete(route('notifications.destroy', row.id), { preserveScroll: true })
}
</script>

<template>
    <Head title="Notifikasi" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Notifikasi</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between gap-4">
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Bell class="h-5 w-5 text-muted-foreground" />
                            Kotak Masuk
                            <Badge v-if="unreadCount > 0" variant="default">
                                {{ unreadCount }} belum dibaca
                            </Badge>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-xs text-muted-foreground">
                            Notifikasi ditandai sudah dibaca saat Anda membukanya — bukan saat
                            halaman ini dibuka. Notifikasi tersimpan permanen dan hanya hilang kalau
                            Anda menghapusnya sendiri.
                        </p>
                    </CardContent>
                </Card>

                <div
                    v-if="notifications.data.length === 0"
                    class="flex flex-col items-center gap-2 rounded-md border border-dashed p-10 text-center"
                >
                    <Inbox class="h-8 w-8 text-muted-foreground/50" />
                    <p class="text-sm text-muted-foreground">Belum ada notifikasi.</p>
                </div>

                <Card
                    v-for="row in notifications.data"
                    :key="row.id"
                    :class="row.read_at === null ? 'border-primary/40' : ''"
                >
                    <CardContent class="flex items-start justify-between gap-4 pt-6">
                        <button type="button" class="flex-1 text-left" @click="open(row)">
                            <div class="flex items-center gap-2">
                                <span class="font-medium">{{ title(row) }}</span>
                                <Badge v-if="row.read_at === null" variant="secondary">Baru</Badge>
                            </div>
                            <p v-if="message(row)" class="mt-1 text-sm text-muted-foreground">
                                {{ message(row) }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ formatTime(row.created_at) }}
                            </p>
                        </button>
                        <Button variant="ghost" size="sm" @click="destroy(row)">
                            <Trash2 class="h-4 w-4" />
                            <span class="sr-only">Hapus</span>
                        </Button>
                    </CardContent>
                </Card>

                <div v-if="notifications.data.length > 0" class="flex items-center justify-between">
                    <p class="text-xs text-muted-foreground">
                        Menampilkan {{ notifications.from }}–{{ notifications.to }} dari
                        {{ notifications.total }}
                    </p>
                    <div class="flex gap-2">
                        <Link v-if="prevLink" :href="prevLink">
                            <Button variant="outline" size="sm">
                                <ChevronLeft class="h-4 w-4" />
                                Sebelumnya
                            </Button>
                        </Link>
                        <Link v-if="nextLink" :href="nextLink">
                            <Button variant="outline" size="sm">
                                Berikutnya
                                <ChevronRight class="h-4 w-4" />
                            </Button>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
