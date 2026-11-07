<script setup lang="ts">
import { Check } from 'lucide-vue-next'

import EmptyState from '@/Components/app/EmptyState.vue'
import { Badge } from '@/Components/ui/badge'
import { Input } from '@/Components/ui/input'
import type { ReviewerCandidate } from '@/types/submission-manage'

// Pilihan reviewer OPERATOR-03-01/03-02; daftarnya disaring server lewat `search`.
// `suggested` = saran BR-MON-07 (monev): hanya label, tidak menyaring.
defineProps<{
    candidates: ReviewerCandidate[]
    selected: number[]
    multiple: boolean
    suggested?: number[]
}>()
const emit = defineEmits<{ toggle: [reviewerId: number] }>()
const search = defineModel<string>('search', { required: true })

const typeLabel = (type: string) => (type === 'external' ? 'Eksternal' : 'Internal')
</script>

<template>
    <div class="flex flex-col gap-3">
        <Input
            v-model="search"
            type="search"
            placeholder="Cari nama / NIDN reviewer..."
            aria-label="Cari reviewer"
        />
        <EmptyState v-if="candidates.length === 0" title="Tidak ada reviewer yang cocok." />
        <ul
            v-else
            class="flex max-h-72 flex-col gap-2 overflow-y-auto"
            :aria-multiselectable="multiple"
        >
            <li v-for="candidate in candidates" :key="candidate.reviewerId">
                <!-- BR-REV-02, BR-REV-03 — yang pasti ditolak tidak bisa dipilih -->
                <button
                    type="button"
                    class="flex w-full items-start justify-between gap-3 rounded-md border p-3 text-left text-sm hover:bg-accent disabled:cursor-not-allowed disabled:opacity-60 aria-pressed:border-primary aria-pressed:bg-accent"
                    :aria-pressed="selected.includes(candidate.reviewerId)"
                    :disabled="candidate.blockedReason !== null"
                    @click="emit('toggle', candidate.reviewerId)"
                >
                    <span class="min-w-0">
                        <span class="block font-medium">{{ candidate.name }}</span>
                        <span class="block text-xs text-muted-foreground">
                            NIDN {{ candidate.nidn ?? '—' }} · {{ typeLabel(candidate.type) }} ·
                            Workload: {{ candidate.activeLoad }} aktif
                        </span>
                    </span>
                    <span class="flex shrink-0 items-center gap-2">
                        <Badge v-if="suggested?.includes(candidate.reviewerId)" variant="secondary">
                            Reviewer pengajuan awal
                        </Badge>
                        <Badge v-if="candidate.blockedReason" variant="outline">
                            {{ candidate.blockedReason }}
                        </Badge>
                        <!-- BR-REV-04 — beban berlebih hanya peringatan -->
                        <Badge v-else-if="candidate.overloaded" variant="secondary"
                            >Beban Tinggi</Badge
                        >
                        <Check
                            v-if="selected.includes(candidate.reviewerId)"
                            class="size-4 text-primary"
                            aria-hidden="true"
                        />
                    </span>
                </button>
            </li>
        </ul>
    </div>
</template>
