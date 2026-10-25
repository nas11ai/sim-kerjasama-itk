<script setup lang="ts">
import { computed } from 'vue'

import EmptyState from '@/Components/app/EmptyState.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import type { SubmissionAnswer } from '@/types/submission-page'

const props = defineProps<{ answers: SubmissionAnswer[] }>()

const files = computed(() => props.answers.filter((answer) => answer.isFile))
</script>

<template>
    <!-- Unduhan menyusul di S2b: belum ada route unduh berkas jawaban (fe-v2-design §9 W8). -->
    <SectionCard title="Berkas Lampiran">
        <dl v-if="files.length > 0" class="divide-y rounded-md border text-sm">
            <div v-for="file in files" :key="file.label" class="flex justify-between gap-2 p-3">
                <dt class="font-medium">{{ file.label }}</dt>
                <dd class="break-all text-muted-foreground">
                    {{ file.value ?? 'Belum diunggah' }}
                </dd>
            </div>
        </dl>
        <EmptyState v-else title="Tidak ada berkas lampiran" />
    </SectionCard>
</template>
