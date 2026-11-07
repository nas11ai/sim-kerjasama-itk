<script setup lang="ts">
import { Download } from 'lucide-vue-next'

import SectionCard from '@/Components/app/SectionCard.vue'
import { formatFileSize } from '@/lib/format'
import type { OutputFile } from '@/types/research-output'

// W141 — unduh lewat route ber-scope; nama asli unggahan, bukan hash disk.
defineProps<{ files: OutputFile[] }>()
</script>

<template>
    <SectionCard title="Berkas Lampiran">
        <p v-if="files.length === 0" class="text-sm text-muted-foreground">
            Belum ada berkas lampiran.
        </p>
        <ul v-else class="flex flex-col gap-2 text-sm">
            <li
                v-for="file in files"
                :key="file.id"
                class="flex items-center justify-between gap-3"
            >
                <a
                    :href="file.url"
                    class="inline-flex min-w-0 items-center gap-2 text-primary underline-offset-4 hover:underline"
                >
                    <Download class="size-4 shrink-0" />
                    <span class="truncate">{{ file.name }}</span>
                </a>
                <span v-if="file.size !== null" class="shrink-0 text-muted-foreground">
                    {{ formatFileSize(file.size) }}
                </span>
            </li>
        </ul>
    </SectionCard>
</template>
