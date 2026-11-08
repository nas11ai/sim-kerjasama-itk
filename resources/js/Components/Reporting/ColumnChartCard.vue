<script setup lang="ts">
// W151 — grafik kolom (berkelompok bila ≥ 2 seri) dengan tooltip bawaan dan tampilan tabel.
import { ref } from 'vue'

import SectionCard from '@/Components/app/SectionCard.vue'
import ChartTableToggle from '@/Components/Reporting/ChartTableToggle.vue'
import { BarChart } from '@/Components/ui/chart-bar'
import { SERIES } from '@/lib/chartPalette'

defineProps<{
    title: string
    subtitle?: string
    index: string
    indexLabel: string
    categories: string[]
    data: Record<string, string | number>[]
}>()

const showTable = ref(false)
</script>

<template>
    <SectionCard :title="title" :description="subtitle">
        <template v-if="data.length > 0" #actions>
            <ChartTableToggle v-model="showTable" />
        </template>
        <p v-if="data.length === 0" class="text-sm text-muted-foreground">Tidak ada data</p>
        <table v-else-if="showTable" class="w-full text-sm">
            <thead>
                <tr class="border-b text-left text-muted-foreground">
                    <th class="py-1.5 font-medium" scope="col">{{ indexLabel }}</th>
                    <th
                        v-for="category in categories"
                        :key="category"
                        class="py-1.5 text-right font-medium"
                        scope="col"
                    >
                        {{ category }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in data" :key="String(row[index])" class="border-b last:border-0">
                    <th class="py-1.5 text-left font-normal" scope="row">{{ row[index] }}</th>
                    <td
                        v-for="category in categories"
                        :key="category"
                        class="py-1.5 text-right tabular-nums"
                    >
                        {{ row[category] ?? 0 }}
                    </td>
                </tr>
            </tbody>
        </table>
        <BarChart
            v-else
            class="h-64"
            :data="data"
            :index="index"
            :categories="categories"
            :colors="SERIES.slice(0, categories.length)"
            :show-legend="categories.length >= 2"
            :rounded-corners="4"
        />
    </SectionCard>
</template>
