<script setup lang="ts">
// Frame: OPERATOR-07-01 — tab Riwayat (W152): apa yang sudah terjadi.
import { computed } from 'vue'

import SectionCard from '@/Components/app/SectionCard.vue'
import ColumnChartCard from '@/Components/Reporting/ColumnChartCard.vue'
import SchemePerformanceTable from '@/Components/Reporting/SchemePerformanceTable.vue'
import type { StatisticsHistory, Trend } from '@/types/reporting'

const props = defineProps<{ history: StatisticsHistory }>()

const SERIES = 'Jumlah'

// Bentuk ulang untuk BarChart saja; tren tanpa satu kejadian pun digambar sebagai kosong.
const chart = (trend: Trend) => ({
    per: trend.unit === 'month' ? 'Bulan' : 'Tahun',
    data: trend.points.some((point) => point.count > 0)
        ? trend.points.map((point) => ({ period: point.label, [SERIES]: point.count }))
        : [],
})

const submitted = computed(() => chart(props.history.submitted))
const outputs = computed(() => chart(props.history.outputs))
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="grid gap-4 lg:grid-cols-2">
            <ColumnChartCard
                :title="`Pengajuan Dikirim per ${submitted.per}`"
                subtitle="Menurut tanggal pengiriman pertama."
                index="period"
                :index-label="submitted.per"
                :categories="[SERIES]"
                :data="submitted.data"
            />
            <ColumnChartCard
                :title="`Luaran Terdaftar per ${outputs.per}`"
                subtitle="Menurut tanggal luaran didaftarkan."
                index="period"
                :index-label="outputs.per"
                :categories="[SERIES]"
                :data="outputs.data"
            />
        </div>
        <SectionCard title="Kinerja per Skema">
            <SchemePerformanceTable :rows="history.schemes" />
        </SectionCard>
    </div>
</template>
