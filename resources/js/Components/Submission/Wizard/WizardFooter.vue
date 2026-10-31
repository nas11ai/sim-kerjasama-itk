<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { ArrowLeft, ArrowRight } from 'lucide-vue-next'
import { computed } from 'vue'

import { Button } from '@/Components/ui/button'

const props = defineProps<{
    step: number
    savedAt: string | null
    processing: boolean
    showNext: boolean
}>()

const emit = defineEmits<{ back: []; next: [] }>()

const cancelHref = computed(() => route('user.submissions.index'))

// Wizard menyimpan setiap berpindah langkah, bukan autosave (fe-v2-design W16).
const savedLabel = computed(() => {
    if (props.savedAt === null) return null
    const time = new Date(props.savedAt).toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
    })
    return `Tersimpan ${time}`
})
</script>

<template>
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <Button v-if="step === 1" as-child variant="ghost">
            <Link :href="cancelHref">Batalkan</Link>
        </Button>
        <Button v-else variant="ghost" :disabled="processing" @click="emit('back')">
            <ArrowLeft class="size-4" /> Sebelumnya
        </Button>
        <div class="flex items-center gap-3">
            <span v-if="savedLabel" class="text-xs text-muted-foreground">{{ savedLabel }}</span>
            <Button v-if="showNext" :disabled="processing" @click="emit('next')">
                Lanjut <ArrowRight class="size-4" />
            </Button>
        </div>
    </div>
</template>
