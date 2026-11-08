<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { Download, Play, RefreshCw } from 'lucide-vue-next'
import { computed } from 'vue'

import { Button } from '@/Components/ui/button'
import type { ExportJobState } from '@/lib/exportJob'
import type { ExportJobRow } from '@/types/reporting'

const props = defineProps<{
    job: ExportJobRow
    state: ExportJobState
    hoursLeft: number
    maxAttempts: number
}>()
const emit = defineEmits<{ rerun: [job: ExportJobRow]; refreshLinks: [] }>()

// BR-RPT-11 — baris cetak tidak bisa dikirim ulang ke ekspor tabular (422); cetak ulangnya
// dari halaman pengajuan.
const reprint = computed(
    () => props.job.kind === 'print' && (props.state === 'gone' || props.state === 'failed')
)
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <template v-if="state === 'ready' && job.download_url">
            <Button as-child variant="outline" size="sm">
                <a :href="job.download_url"><Download class="size-4" aria-hidden="true" /> Unduh</a>
            </Button>
            <span class="text-xs text-muted-foreground">Kadaluarsa dalam {{ hoursLeft }} jam</span>
        </template>
        <Button
            v-else-if="state === 'linkExpired'"
            variant="outline"
            size="sm"
            @click="emit('refreshLinks')"
        >
            <RefreshCw class="size-4" aria-hidden="true" /> Minta Tautan Baru
        </Button>
        <template v-else-if="reprint">
            <Link v-if="job.submission_href" :href="job.submission_href" class="text-xs underline">
                Cetak ulang dari halaman pengajuan.
            </Link>
        </template>
        <Button v-else-if="state === 'gone'" size="sm" @click="emit('rerun', job)">
            <Download class="size-4" aria-hidden="true" /> Ekspor Ulang
        </Button>
        <template v-else-if="state === 'failed'">
            <Button variant="outline" size="sm" @click="emit('rerun', job)">
                <Play class="size-4" aria-hidden="true" /> Coba Lagi
            </Button>
            <span class="text-xs text-muted-foreground"
                >Percobaan {{ job.attempts }} dari {{ maxAttempts }}</span
            >
        </template>
    </div>
</template>
