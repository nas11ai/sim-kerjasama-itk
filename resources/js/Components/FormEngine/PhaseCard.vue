<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { Link2, Pencil, Power, Trash2 } from 'lucide-vue-next'

import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import type { PhaseCardData } from '@/types/form-engine'

const props = defineProps<{ phase: PhaseCardData; formId: number; canLinkPeriod: boolean }>()
const emit = defineEmits<{
    'toggle-active': [phase: PhaseCardData]
    delete: [phase: PhaseCardData]
    'link-period': [phase: PhaseCardData]
}>()

// W101 — "{n} tahapan" = jumlah detail; periode dipisah koma atau "Belum terhubung".
const summary = computed(() => {
    const periods =
        props.phase.periodNames.length > 0
            ? `Periode: ${props.phase.periodNames.join(', ')}`
            : 'Belum terhubung'
    return `${props.phase.detailCount} tahapan · ${periods}`
})
</script>

<template>
    <article class="flex flex-wrap items-start justify-between gap-4 rounded-lg border p-4">
        <div class="min-w-0 space-y-1">
            <div class="flex flex-wrap items-center gap-2">
                <h3 class="font-medium">{{ phase.title }}</h3>
                <Badge v-if="!phase.isActive" variant="outline">Nonaktif</Badge>
            </div>
            <p v-if="phase.description" class="text-sm text-muted-foreground">
                {{ phase.description }}
            </p>
            <p class="text-sm text-muted-foreground">{{ summary }}</p>
        </div>
        <div class="flex flex-wrap justify-end gap-2">
            <Button
                v-if="canLinkPeriod"
                size="sm"
                variant="outline"
                @click="emit('link-period', phase)"
                ><Link2 class="size-4" />Hubungkan ke Periode</Button
            >
            <!-- Sementara menaut ke layar legacy; editor v2 menggantinya di Task 4 -->
            <Button as-child size="sm" variant="outline">
                <Link :href="route('admin.form-phases.edit', phase.id)"
                    ><Pencil class="size-4" />Edit</Link
                >
            </Button>
            <Button size="sm" variant="outline" @click="emit('toggle-active', phase)"
                ><Power class="size-4" />{{ phase.isActive ? 'Nonaktifkan' : 'Aktifkan' }}</Button
            >
            <Button size="sm" variant="ghost" @click="emit('delete', phase)"
                ><Trash2 class="size-4" />Hapus</Button
            >
        </div>
    </article>
</template>
