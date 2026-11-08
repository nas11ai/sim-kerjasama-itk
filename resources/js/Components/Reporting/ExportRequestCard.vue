<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Download } from 'lucide-vue-next'
import { computed } from 'vue'

import FilterSelect from '@/Components/app/FilterSelect.vue'
import FormField from '@/Components/app/FormField.vue'
import OptionSelect from '@/Components/app/OptionSelect.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import { Button } from '@/Components/ui/button'
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group'
import type { ExportFilterKey, ExportFormOptions } from '@/types/reporting'

const props = defineProps<ExportFormOptions & { threshold: number; initialType: string | null }>()

const form = useForm({
    type: props.initialType ?? props.types[0]?.value ?? '',
    format: props.formats[0]?.value ?? 'xlsx',
    year_from: '',
    year_to: '',
    scheme: '',
    status: '',
})

const keys = computed<ExportFilterKey[]>(
    () => props.types.find((t) => t.value === form.type)?.filterKeys ?? []
)
const accepts = (key: ExportFilterKey) => keys.value.includes(key)
const filterOf: Record<string, ExportFilterKey> = {
    year_from: 'year',
    year_to: 'year',
    scheme: 'scheme',
    status: 'status',
}

// W154 — hanya filter yang berlaku bagi jenisnya dan yang terisi ikut terkirim.
const submit = () =>
    form
        .transform((data) =>
            Object.fromEntries(
                Object.entries(data).filter(([key, value]) => {
                    const filter = filterOf[key]
                    return filter === undefined || (value !== '' && accepts(filter))
                })
            )
        )
        .post(route('reporting.exports.store'), { preserveScroll: true })
</script>

<template>
    <SectionCard title="Request Export Baru">
        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField
                    id="export-type"
                    label="Jenis Export"
                    required
                    :hint="`Lebih dari ${threshold} baris diproses di latar belakang.`"
                    :error="form.errors.type"
                >
                    <template #default="{ describedBy }">
                        <OptionSelect
                            id="export-type"
                            :model-value="form.type"
                            :options="types"
                            :described-by="describedBy"
                            @update:model-value="(type) => (form.type = type ?? form.type)"
                        />
                    </template>
                </FormField>
                <FormField
                    id="export-years"
                    label="Rentang Tahun"
                    :error="form.errors.year_to ?? form.errors.year_from"
                >
                    <div class="flex items-center gap-2">
                        <FilterSelect
                            v-model="form.year_from"
                            :options="yearOptions"
                            all-label="Semua"
                            label="Dari tahun"
                        />
                        <span aria-hidden="true">–</span>
                        <FilterSelect
                            v-model="form.year_to"
                            :options="yearOptions"
                            all-label="Semua"
                            label="Sampai tahun"
                        />
                    </div>
                </FormField>
                <FormField
                    v-if="accepts('scheme')"
                    id="export-scheme"
                    label="Skema"
                    :error="form.errors.scheme"
                >
                    <FilterSelect
                        v-model="form.scheme"
                        :options="schemeOptions"
                        all-label="Semua skema"
                        label="Skema"
                    />
                </FormField>
                <FormField
                    v-if="accepts('status')"
                    id="export-status"
                    label="Status"
                    :error="form.errors.status"
                >
                    <FilterSelect
                        v-model="form.status"
                        :options="statusOptions"
                        all-label="Semua status"
                        label="Status"
                    />
                </FormField>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <RadioGroup
                    v-model="form.format"
                    class="inline-flex gap-0 rounded-md border p-0.5"
                    aria-label="Format berkas"
                >
                    <label
                        v-for="format in formats"
                        :key="format.value"
                        class="flex cursor-pointer items-center gap-2 rounded px-3 py-1 text-sm has-[[data-state=checked]]:bg-muted"
                    >
                        <RadioGroupItem :value="format.value" /> {{ format.label }}
                    </label>
                </RadioGroup>
                <Button type="submit" :disabled="form.processing">
                    <Download class="size-4" aria-hidden="true" /> Request Export
                </Button>
            </div>
        </form>
    </SectionCard>
</template>
