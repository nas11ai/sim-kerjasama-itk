<!-- resources/js/Pages/Announcement/Index.vue -->
<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card'
import { Paperclip, Check, Eye } from 'lucide-vue-next'
import { ref } from 'vue'

interface AnnouncementFile {
    id: number
    file_name: string
    file_path: string
}

interface Announcement {
    id: number
    title: string
    content: string
    type: 'public' | 'private'
    expired_at: string | null
    announcement_files: AnnouncementFile[]
    created_at: string
    updated_at: string
    is_read?: boolean // Add this field to track read status
}

interface Props {
    announcements: {
        data: Announcement[]
        links: any[]
        meta: any
    }
}

const props = defineProps<Props>()

// Track loading state for mark as read buttons
const markingAsRead = ref<Record<number, boolean>>({})

const capitalize = (s: string) => {
    return s.charAt(0).toUpperCase() + s.slice(1)
}

function stripHtml(html: string) {
    if (!html) return ''
    const doc = new DOMParser().parseFromString(html, 'text/html')
    return doc.body.textContent || ''
}

const markAsRead = async (announcementId: number) => {
    try {
        // Set loading state
        markingAsRead.value[announcementId] = true

        // Make API call using Inertia router with GET method
        await router.get(
            `/announcements/${announcementId}/markRead`,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    // Update the announcement's read status locally
                    const announcement = props.announcements.data.find(
                        (a) => a.id === announcementId
                    )
                    if (announcement) {
                        announcement.is_read = true
                    }
                },
                onError: (error) => {
                    console.error('Gagal menandai sebagai dibaca:', error)
                    // You can add toast notification here if you have one
                },
                onFinish: () => {
                    // Remove loading state
                    markingAsRead.value[announcementId] = false
                },
            }
        )
    } catch (error) {
        console.error('Kesalahan saat menandai pengumuman sebagai dibaca:', error)
        markingAsRead.value[announcementId] = false
    }
}
</script>

<template>
    <Head title="Pengumuman" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Pusat Pengumuman</h2>
            </div>
        </template>

        <div class="space-y-6 flex justify-center">
            <!-- Forms Grid -->
            <div class="flex w-full flex-col flex-wrap gap-4 max-w-5xl justify-center align-middle">
                <Card
                    v-for="announcement in props.announcements.data.filter(
                        (a) => a.type === 'private'
                    )"
                    :key="announcement.id"
                    class="group hover:shadow-lg transition-shadow rounded-xl relative"
                    :class="{
                        'border-l-4 border-l-green-500': announcement.is_read,
                        'border-l-4 border-l-blue-500': !announcement.is_read,
                    }"
                >
                    <!-- Read Status Indicator -->
                    <div v-if="announcement.is_read" class="absolute top-4 right-4">
                        <div
                            class="flex items-center gap-1 px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs font-medium"
                        >
                            <Check class="h-3 w-3" />
                            Sudah dibaca
                        </div>
                    </div>

                    <CardHeader class="pb-2" :class="{ 'pr-20': announcement.is_read }">
                        <span
                            class="w-fit px-2 py-1 text-[12px] rounded-full bg-blue-100 text-blue-700 font-medium"
                        >
                            {{
                                new Date(announcement.created_at).toLocaleDateString('id-ID', {
                                    day: '2-digit',
                                    month: 'long',
                                    year: 'numeric',
                                })
                            }}
                        </span>
                        <CardTitle
                            class="text-lg font-semibold text-gray-800"
                            :class="{
                                'font-bold': !announcement.is_read,
                                'font-medium text-gray-600': announcement.is_read,
                            }"
                        >
                            {{ capitalize(announcement.title) }}
                        </CardTitle>
                    </CardHeader>

                    <CardContent>
                        <CardDescription class="mt-1 text-gray-600 leading-relaxed">
                            {{ stripHtml(announcement.content) || 'Tidak ada deskripsi' }}
                        </CardDescription>

                        <!-- Attachments -->
                        <div v-if="announcement.announcement_files.length" class="mt-4 space-y-1">
                            <div class="flex items-center text-sm text-gray-500 font-medium">
                                <Paperclip class="h-4 w-4 mr-2" /> Lampiran:
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <a
                                    v-for="file in announcement.announcement_files"
                                    :key="file.id"
                                    :href="file.file_path"
                                    target="_blank"
                                    class="inline-flex items-center rounded-md border px-3 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50 transition-colors"
                                >
                                    {{ file.file_name }}
                                </a>
                            </div>
                        </div>

                        <!-- Mark as Read Button -->
                        <div v-if="!announcement.is_read" class="mt-4 flex justify-end">
                            <button
                                :disabled="markingAsRead[announcement.id]"
                                class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 disabled:cursor-not-allowed rounded-md transition-colors"
                                @click="markAsRead(announcement.id)"
                            >
                                <Eye class="h-4 w-4" />
                                <span v-if="markingAsRead[announcement.id]"> Menandai... </span>
                                <span v-else> Tandai Sudah Dibaca </span>
                            </button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Empty State -->
            <div
                v-if="props.announcements.data.filter((a) => a.type === 'private').length === 0"
                class="text-center py-12"
            >
                <div class="mx-auto max-w-md">
                    <div class="mx-auto h-12 w-12 text-gray-400">
                        <Paperclip class="h-12 w-12 mx-auto text-gray-400" />
                    </div>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">Belum ada pengumuman</h3>
                    <p class="mt-1 text-sm text-gray-500">Nantikan informasi terbaru dari kami.</p>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="props.announcements.links.length > 3" class="flex justify-center">
                <nav class="flex items-center space-x-1">
                    <Link
                        v-for="link in props.announcements.links"
                        :key="link.label"
                        :href="link.url"
                        :class="[
                            'px-3 py-2 text-sm font-medium rounded-md',
                            link.active
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-muted',
                            !link.url && 'opacity-50 cursor-not-allowed',
                        ]"
                        v-html="link.label"
                    />
                </nav>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
