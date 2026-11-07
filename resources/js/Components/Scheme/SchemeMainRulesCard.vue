<script setup lang="ts">
import AppBanner from '@/Components/app/AppBanner.vue'
import FormField from '@/Components/app/FormField.vue'
import OptionSelect from '@/Components/app/OptionSelect.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import { Input } from '@/Components/ui/input'
import type { SchemeFormOptions, SchemeMainFields } from '@/types/scheme'

defineProps<{ options: SchemeFormOptions; errors: Partial<Record<string, string>> }>()
const fields = defineModel<SchemeMainFields>({ required: true })

type TextKey = Exclude<keyof SchemeMainFields, 'scheme_type_id' | 'submission_type_id'>

const text = (key: TextKey, value: string | number) =>
    (fields.value = { ...fields.value, [key]: String(value) })
const select = (key: 'scheme_type_id' | 'submission_type_id', value: string | null) =>
    (fields.value = { ...fields.value, [key]: value })

const limits: { key: TextKey; id: string; label: string }[] = [
    { key: 'max_budget', id: 'scheme-budget', label: 'Anggaran Maks. (Rp)' },
    { key: 'max_members', id: 'scheme-members', label: 'Anggota Maks.' },
    { key: 'duration_months', id: 'scheme-duration', label: 'Durasi (bulan)' },
]
</script>

<template>
    <SectionCard title="Aturan Utama">
        <!-- BR-SCH-02/03 — galat tampil di field yang benar; banner frame merangkumnya -->
        <AppBanner v-if="errors.code" tone="denial">Kode skema harus unik.</AppBanner>
        <AppBanner v-if="errors.max_budget || errors.max_members" tone="denial">
            Batas anggaran dan jumlah anggota harus lebih dari nol.
        </AppBanner>
        <div class="grid gap-4 sm:grid-cols-2">
            <FormField id="scheme-name" label="Nama Skema" required :error="errors.name">
                <Input
                    id="scheme-name"
                    :model-value="fields.name"
                    maxlength="255"
                    @update:model-value="(value) => text('name', value)"
                />
            </FormField>
            <FormField
                id="scheme-code"
                v-slot="{ describedBy }"
                label="Kode"
                required
                hint="Kode harus unik antar skema."
                :error="errors.code"
            >
                <Input
                    id="scheme-code"
                    :model-value="fields.code"
                    maxlength="64"
                    :aria-describedby="describedBy"
                    @update:model-value="(value) => text('code', value)"
                />
            </FormField>
            <FormField id="scheme-type" label="Tipe Skema" :error="errors.scheme_type_id">
                <OptionSelect
                    id="scheme-type"
                    :model-value="fields.scheme_type_id"
                    :options="options.schemeTypes"
                    @update:model-value="(value) => select('scheme_type_id', value)"
                />
            </FormField>
            <FormField
                id="scheme-submission-type"
                label="Tipe Submission"
                :error="errors.submission_type_id"
            >
                <OptionSelect
                    id="scheme-submission-type"
                    :model-value="fields.submission_type_id"
                    :options="options.submissionTypes"
                    @update:model-value="(value) => select('submission_type_id', value)"
                />
            </FormField>
            <FormField
                v-for="limit in limits"
                :id="limit.id"
                :key="limit.key"
                v-slot="{ describedBy }"
                :label="limit.label"
                required
                :error="errors[limit.key]"
            >
                <Input
                    :id="limit.id"
                    type="number"
                    min="1"
                    :model-value="fields[limit.key]"
                    :aria-describedby="describedBy"
                    @update:model-value="(value) => text(limit.key, value)"
                />
            </FormField>
        </div>
    </SectionCard>
</template>
