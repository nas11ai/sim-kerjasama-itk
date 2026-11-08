<script setup lang="ts">
// Frame: OPERATOR-07-01 — tab Ringkasan: KPI + empat panel frame (W150, W151).
import { computed } from 'vue'

import KpiGrid from '@/Components/app/KpiGrid.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import ColumnChartCard from '@/Components/Reporting/ColumnChartCard.vue'
import RankedBarList from '@/Components/Reporting/RankedBarList.vue'
import StatusBreakdownCard from '@/Components/Reporting/StatusBreakdownCard.vue'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs'
import type { StatisticsOverview } from '@/types/reporting'

const props = defineProps<{ overview: StatisticsOverview }>()

const allSubmissionsHref = computed(
    () => props.overview.kpis.find((kpi) => kpi.key === 'submissions')?.href ?? null
)

// Bentuk ulang untuk BarChart saja; angkanya dari backend.
const schemeYear = computed(() => ({
    categories: props.overview.bySchemeYear.years.map(String),
    data: props.overview.bySchemeYear.rows.map((row) => ({ scheme: row.scheme, ...row.counts })),
}))
</script>

<template>
    <div class="flex flex-col gap-4">
        <KpiGrid :cards="overview.kpis" :columns="5" />
        <div class="grid gap-4 lg:grid-cols-2">
            <StatusBreakdownCard :rows="overview.byStatus" :all-href="allSubmissionsHref" />
            <ColumnChartCard
                title="Submission per Skema per Tahun"
                subtitle="Tiga tahun terakhir; tidak mengikuti filter Tahun."
                index="scheme"
                index-label="Skema"
                :categories="schemeYear.categories"
                :data="schemeYear.data"
            />
        </div>
        <div class="grid gap-4 lg:grid-cols-2">
            <SectionCard title="Submission per Fakultas / Prodi">
                <Tabs default-value="faculty">
                    <TabsList>
                        <TabsTrigger value="faculty">Fakultas</TabsTrigger>
                        <TabsTrigger value="program">Prodi</TabsTrigger>
                    </TabsList>
                    <TabsContent value="faculty" class="mt-3">
                        <RankedBarList :rows="overview.byFaculty" />
                    </TabsContent>
                    <TabsContent value="program" class="mt-3">
                        <RankedBarList :rows="overview.byStudyProgram" />
                    </TabsContent>
                </Tabs>
            </SectionCard>
            <SectionCard title="Luaran per Tipe">
                <RankedBarList :rows="overview.byOutputType" />
            </SectionCard>
        </div>
    </div>
</template>
