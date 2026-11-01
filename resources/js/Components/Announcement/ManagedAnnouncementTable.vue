<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { Megaphone } from 'lucide-vue-next'

import DataTable from '@/Components/app/DataTable.vue'
import EmptyState from '@/Components/app/EmptyState.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { formatDate } from '@/lib/format'
import type { ManagedAnnouncementRow } from '@/types/announcement'

defineProps<{ rows: ManagedAnnouncementRow[]; filtered: boolean }>()
const emit = defineEmits<{ retract: [row: ManagedAnnouncementRow] }>()

const columns = [
    { key: 'title', label: 'Judul' },
    { key: 'audienceLabel', label: 'Sasaran' },
    { key: 'expiresAt', label: 'Berlaku sampai' },
    { key: 'createdAt', label: 'Dibuat' },
    { key: 'action', label: '' },
]
</script>

<template>
    <DataTable :rows="rows" :columns="columns">
        <template #cell-title="{ row }">
            <span class="font-medium">{{ row.title }}</span>
        </template>
        <template #cell-audienceLabel="{ row }">{{ row.audienceLabel }}</template>
        <template #cell-expiresAt="{ row }">
            <span class="inline-flex items-center gap-2">
                {{ row.expiresAt === null ? 'Tanpa batas' : formatDate(row.expiresAt) }}
                <Badge v-if="row.expired" variant="secondary">Kedaluwarsa</Badge>
            </span>
        </template>
        <template #cell-createdAt="{ row }">
            {{ formatDate(row.createdAt) }}
            <span v-if="row.author" class="block text-xs text-muted-foreground">
                {{ row.author }}
            </span>
        </template>
        <template #cell-action="{ row }">
            <div class="flex justify-end gap-1">
                <Button as-child variant="ghost" size="sm">
                    <Link :href="route('admin.announcements.edit', row.id)">Sunting</Link>
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    class="text-destructive"
                    type="button"
                    @click="emit('retract', row)"
                >
                    Hapus
                </Button>
            </div>
        </template>
        <template #empty>
            <EmptyState
                :icon="Megaphone"
                :title="filtered ? 'Tidak ada pengumuman yang cocok.' : 'Belum ada pengumuman.'"
            />
        </template>
    </DataTable>
</template>
