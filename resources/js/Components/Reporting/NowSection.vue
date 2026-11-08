<script setup lang="ts">
// Frame: OPERATOR-07-01 — tab Saat Ini (W152): apa yang sedang terjadi sekarang.
import SectionCard from '@/Components/app/SectionCard.vue'
import RankedBarList from '@/Components/Reporting/RankedBarList.vue'
import StageBacklogList from '@/Components/Reporting/StageBacklogList.vue'
import type { StatisticsNow } from '@/types/reporting'

defineProps<{ now: StatisticsNow }>()
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="grid gap-4 lg:grid-cols-2">
            <SectionCard
                title="Pengajuan Berjalan"
                description="Umur dihitung sejak submit pertama; tidak mengikuti filter Tahun."
            >
                <StageBacklogList :rows="now.backlog" />
            </SectionCard>
            <SectionCard
                title="Siklus Monev"
                description="Status siklus berjalan tiap pengajuan disetujui."
            >
                <RankedBarList :rows="now.monev" />
            </SectionCard>
        </div>
        <div class="grid gap-4 lg:grid-cols-2">
            <SectionCard
                title="Cakupan Reviewer"
                description="Pengajuan berjalan menurut jumlah reviewer aktif."
            >
                <RankedBarList :rows="now.coverage" />
            </SectionCard>
            <SectionCard
                title="Beban Reviewer"
                description="Evaluasi yang belum selesai per reviewer aktif."
            >
                <RankedBarList :rows="now.workload.rows" />
                <p v-if="now.workload.idleCount > 0" class="text-sm text-muted-foreground">
                    {{ now.workload.idleCount }} reviewer aktif belum punya tugas evaluasi.
                </p>
            </SectionCard>
        </div>
    </div>
</template>
