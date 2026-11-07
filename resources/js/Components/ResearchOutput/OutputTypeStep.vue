<script setup lang="ts">
import { computed } from 'vue'

import FormField from '@/Components/app/FormField.vue'
import OptionSelect from '@/Components/app/OptionSelect.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import type { OutputType } from '@/types/research-output'

const props = defineProps<{ types: OutputType[]; code: string; error?: string }>()
const emit = defineEmits<{ change: [code: string] }>()

// BR-RO-02/05 — pilihan sudah disaring backend: taksonomi aktif ∩ jenis pengajuan.
const options = computed(() => props.types.map((t) => ({ value: t.code, label: t.name })))
const pick = (value: string | null) => emit('change', value ?? '')
</script>

<template>
    <SectionCard title="Langkah 1 — Pilih Tipe Luaran">
        <FormField
            id="output-type"
            label="Tipe Luaran"
            required
            hint="Artikel / Buku / HKI / Prototipe / PKS / Seminar. Tipe PKS hanya tersedia untuk pengajuan Pengabdian kepada Masyarakat."
            :error="error"
        >
            <template #default="{ describedBy }">
                <OptionSelect
                    id="output-type"
                    :model-value="code === '' ? null : code"
                    :options="options"
                    placeholder="Pilih tipe luaran"
                    :described-by="describedBy"
                    @update:model-value="pick"
                />
            </template>
        </FormField>
    </SectionCard>
</template>
