<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { Link2, Pencil, Power, Trash2 } from 'lucide-vue-next'

import AppBanner from '@/Components/app/AppBanner.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { phaseDraftBannerText } from '@/lib/phaseDefinition'
import type { PhaseCardData } from '@/types/form-engine'

// `returnForm` — id Form tapis pustaka; editor kembali ke pustaka bertapis itu (B5).
const props = defineProps<{
    phase: PhaseCardData
    canLinkPeriod: boolean
    returnForm?: number | null
}>()
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
                <!-- BR-FE-33 — tahap tanpa versi belum bisa dipakai periode -->
                <Badge variant="outline">{{ phase.versionLabel ?? 'Belum terbit' }}</Badge>
            </div>
            <!-- BR-FE-28 — satu Form pengajuan per tahap -->
            <p v-if="phase.submissionFormTitle" class="text-sm">
                Formulir pengajuan: {{ phase.submissionFormTitle }}
            </p>
            <p v-if="phase.description" class="text-sm text-muted-foreground">
                {{ phase.description }}
            </p>
            <p class="text-sm text-muted-foreground">{{ summary }}</p>
            <AppBanner v-if="phase.hasDraft" tone="info">
                {{ phaseDraftBannerText(phase.versionLabel) }}
            </AppBanner>
            <!-- BR-FE-26 / ADR 0019 — celah jadwal: tanggal yang belum ada di kalender periode -->
            <AppBanner v-for="gap in phase.deadlineGaps" :key="gap.period" tone="warning">
                Periode {{ gap.period }} belum punya {{ gap.count }} tanggal yang dibutuhkan tahap
                ini.
            </AppBanner>
        </div>
        <div class="flex flex-wrap justify-end gap-2">
            <Button
                v-if="canLinkPeriod && phase.linkablePeriods.length > 0"
                size="sm"
                variant="outline"
                @click="emit('link-period', phase)"
                ><Link2 class="size-4" />Hubungkan ke Periode</Button
            >
            <Button as-child size="sm" variant="outline">
                <Link
                    :href="
                        returnForm
                            ? route('admin.phases.edit', { formPhase: phase.id, form: returnForm })
                            : route('admin.phases.edit', phase.id)
                    "
                    ><Pencil class="size-4" />Ubah</Link
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
