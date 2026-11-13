<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { Plus } from 'lucide-vue-next'
import { computed, ref } from 'vue'

import FormField from '@/Components/app/FormField.vue'
import OptionSelect from '@/Components/app/OptionSelect.vue'
import StepEvaluationFormRow from '@/Components/FormEngine/StepEvaluationFormRow.vue'
import { Button } from '@/Components/ui/button'
import { Label } from '@/Components/ui/label'
import { Switch } from '@/Components/ui/switch'
import type { EvaluationOption, StepEvaluationFormData } from '@/types/form-engine'

// BR-FE-30 + BR-FE-33 — Formulir Evaluasi satu langkah ber-needs_review, bagian isi draf: pasang, ubah,
// dan lepas hanya mengubah daftar ini; semuanya tersimpan lewat Simpan draf, berlaku sejak terbit.
const props = defineProps<{
    stepKey: string
    label: string
    evaluationOptions: EvaluationOption[]
}>()
const attached = defineModel<StepEvaluationFormData[]>({ required: true })

const available = computed(() => {
    const taken = new Set(attached.value.map((step) => String(step.formId)))
    return props.evaluationOptions.filter((option) => !taken.has(option.value))
})

const chosen = ref<string | null>(null)
const required = ref(true)
const prefix = `attach-${props.stepKey}`

const attach = () => {
    const option = available.value.find((o) => o.value === chosen.value)
    if (option === undefined) return
    attached.value = [
        ...attached.value,
        {
            formId: Number(option.value),
            title: option.label,
            versionLabel: option.versionLabel,
            isRequired: required.value,
            isActive: true,
        },
    ]
    chosen.value = null
    required.value = true
}

const replace = (index: number, step: StepEvaluationFormData) =>
    (attached.value = attached.value.map((s, i) => (i === index ? step : s)))
const remove = (index: number) => (attached.value = attached.value.filter((_, i) => i !== index))
</script>

<template>
    <section class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm font-semibold">Formulir evaluasi untuk {{ label }}</h3>
            <Link class="text-sm underline" :href="route('admin.evaluation-forms.index')"
                >Kelola di Formulir Evaluasi</Link
            >
        </div>
        <ul v-if="attached.length > 0" class="space-y-2">
            <StepEvaluationFormRow
                v-for="(step, index) in attached"
                :key="step.formId"
                :step="step"
                @update="(next) => replace(index, next)"
                @remove="remove(index)"
            />
        </ul>
        <p v-else class="text-sm text-muted-foreground">Belum ada formulir evaluasi terpasang.</p>
        <div
            v-if="available.length > 0"
            class="flex flex-wrap items-end gap-3 rounded-lg border border-dashed p-3"
        >
            <FormField
                :id="`${prefix}-form`"
                label="Pasang Formulir Evaluasi"
                class="min-w-56 flex-1"
            >
                <template #default="{ describedBy }">
                    <OptionSelect
                        :id="`${prefix}-form`"
                        v-model="chosen"
                        :options="available"
                        placeholder="Pilih Formulir Evaluasi terbit"
                        :described-by="describedBy"
                    />
                </template>
            </FormField>
            <div class="flex items-center gap-2 pb-2">
                <Switch :id="`${prefix}-required`" v-model="required" />
                <Label :for="`${prefix}-required`" class="font-normal">Wajib diisi penilai</Label>
            </div>
            <Button type="button" :disabled="chosen === null" @click="attach"
                ><Plus class="size-4" />Pasang</Button
            >
        </div>
        <p v-else-if="evaluationOptions.length === 0" class="text-sm text-muted-foreground">
            Belum ada Formulir Evaluasi yang terbit. Susun dan terbitkan dulu di pustaka Formulir
            Evaluasi.
        </p>
    </section>
</template>
