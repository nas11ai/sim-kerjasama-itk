<script setup lang="ts">
// Frame: chip Tahun / Periode / Skema. Tahun memakai pilihan backend yang sudah memuat
// "Semua tahun" (year=all); tanpa param berarti tahun berjalan (W149).
import FilterSelect from '@/Components/app/FilterSelect.vue'
import OptionSelect from '@/Components/app/OptionSelect.vue'
import { useFilters } from '@/composables/useFilters'
import type { StatisticsFilterKey, StatisticsFilters, StatisticsTab } from '@/types/reporting'
import type { FilterOption, FilterSelectSpec } from '@/types/submission-page'

const props = defineProps<{
    tab: StatisticsTab
    filters: StatisticsFilters
    filterKeys: StatisticsFilterKey[]
    yearOptions: FilterOption[]
    periodOptions: FilterOption[]
    schemeOptions: FilterOption[]
}>()

const asText = (key: StatisticsFilterKey): string => {
    const value = props.filters[key]
    if (value !== null) return String(value)
    return key === 'year' ? 'all' : ''
}

const { filters } = useFilters(
    'reporting.stats',
    Object.fromEntries(props.filterKeys.map((key) => [key, asText(key)])),
    props.tab === 'overview' ? undefined : { tab: props.tab }
)

const specs: FilterSelectSpec<'period' | 'scheme'>[] = [
    { key: 'period', label: 'Periode', allLabel: 'Semua periode', options: props.periodOptions },
    { key: 'scheme', label: 'Skema', allLabel: 'Semua skema', options: props.schemeOptions },
]
const selects = specs.filter((select) => props.filterKeys.includes(select.key))
</script>

<template>
    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
        <div v-if="filterKeys.includes('year')" class="w-full sm:w-40">
            <label for="stats-year" class="sr-only">Tahun</label>
            <OptionSelect
                id="stats-year"
                :model-value="filters.year ?? null"
                :options="yearOptions"
                @update:model-value="filters.year = $event ?? 'all'"
            />
        </div>
        <FilterSelect
            v-for="select in selects"
            :key="select.key"
            :model-value="filters[select.key] ?? ''"
            :options="select.options"
            :all-label="select.allLabel"
            :label="select.label"
            @update:model-value="filters[select.key] = $event"
        />
    </div>
</template>
