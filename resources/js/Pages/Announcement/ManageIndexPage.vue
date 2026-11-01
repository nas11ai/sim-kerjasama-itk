<script setup lang="ts">
// Frame: — (fe-v2-design §4.7)
import { Head, Link, router } from '@inertiajs/vue3'
import { Plus } from 'lucide-vue-next'
import { computed, ref } from 'vue'

import ManagedAnnouncementTable from '@/Components/Announcement/ManagedAnnouncementTable.vue'
import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import PaginationBar from '@/Components/app/PaginationBar.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { useFilters } from '@/composables/useFilters'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { AnnouncementManageIndexPageProps, ManagedAnnouncementRow } from '@/types/announcement'

const props = defineProps<AnnouncementManageIndexPageProps>()

const { filters } = useFilters('admin.announcements.index', props.filters)
const filtered = computed(() => props.filters.search !== '')

const retracting = ref<ManagedAnnouncementRow | null>(null)
const confirmOpen = ref(false)
const processing = ref(false)

function askRetract(row: ManagedAnnouncementRow) {
    retracting.value = row
    confirmOpen.value = true
}

function retract() {
    if (retracting.value === null) {
        return
    }
    router.delete(route('admin.announcements.destroy', retracting.value.id), {
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
    <Head title="Kelola Pengumuman" />
    <AppLayout>
        <PageHeader
            title="Kelola Pengumuman"
            subtitle="Pengumuman yang diterbitkan untuk pengguna dan tamu."
        >
            <template #actions>
                <Button as-child>
                    <Link :href="route('admin.announcements.create')">
                        <Plus class="size-4" /> Buat Pengumuman
                    </Link>
                </Button>
            </template>
        </PageHeader>
        <div class="mb-4">
            <Input
                v-model="filters.search"
                type="search"
                placeholder="Cari judul..."
                aria-label="Cari judul pengumuman"
                class="sm:max-w-xs"
            />
        </div>
        <ManagedAnnouncementTable
            :rows="announcements.data"
            :filtered="filtered"
            @retract="askRetract"
        />
        <PaginationBar :page="announcements" />
        <ConfirmDialog
            v-model:open="confirmOpen"
            title="Hapus pengumuman"
            description="Tarik pengumuman ini? Lampirannya ikut dihapus."
            confirm-label="Hapus"
            destructive
            :processing="processing"
            @confirm="retract"
        />
    </AppLayout>
</template>
