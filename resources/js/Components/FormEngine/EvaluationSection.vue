<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3'
import { Plus } from 'lucide-vue-next'
import { computed } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import FormField from '@/Components/app/FormField.vue'
import OptionSelect from '@/Components/app/OptionSelect.vue'
import StepEvaluationFormRow from '@/Components/FormEngine/StepEvaluationFormRow.vue'
import { Button } from '@/Components/ui/button'
import { Label } from '@/Components/ui/label'
import { Switch } from '@/Components/ui/switch'
import type { EvaluationSectionData } from '@/types/form-engine'
import type { FilterOption } from '@/types/submission-page'

// BR-FE-30 — bagian evaluasi satu detail ber-needs_review: memasang Form Evaluasi pustaka yang sudah
// terbit, sekali per langkah. Isinya disusun di pustaka, bukan di sini.
const props = defineProps<{ section: EvaluationSectionData; evaluationOptions: FilterOption[] }>()

const available = computed(() => {
    const attached = new Set(props.section.attached.map((step) => String(step.formId)))
    return props.evaluationOptions.filter((option) => !attached.has(option.value))
})

const attach = useForm<{ review_evaluation_form_id: string | null; is_required: boolean }>({
    review_evaluation_form_id: null,
    is_required: true,
})
const prefix = `attach-${props.section.detailId}`
// AssertReviewedPhaseDetail menolak lewat `form_phase_detail_id`, kunci di luar isian form ini.
const errors = computed<Record<string, string>>(() => ({ ...attach.errors }))

const submit = () =>
    attach.post(route('admin.phase-details.evaluation-forms.store', props.section.detailId), {
        preserveScroll: true,
        onSuccess: () => attach.reset(),
    })
</script>

<template>
    <section class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-base font-semibold">Form evaluasi untuk {{ section.detailLabel }}</h2>
            <Link class="text-sm underline" :href="route('admin.evaluation-forms.index')"
                >Kelola di Form Evaluasi</Link
            >
        </div>
        <!-- W158 — form ditugaskan otomatis hanya saat reviewer ditugaskan (AssignReviewers); fakta, bukan aturan baru. -->
        <AppBanner v-if="section.reviewsInProgress > 0" tone="info">
            Form baru tidak otomatis ditugaskan ke reviewer
            {{ section.reviewsInProgress }} pengajuan yang sedang direview; reviewernya tetap bisa
            memulainya dari halaman evaluasi.
        </AppBanner>
        <ul v-if="section.attached.length > 0" class="space-y-2">
            <StepEvaluationFormRow v-for="step in section.attached" :key="step.id" :step="step" />
        </ul>
        <p v-else class="text-sm text-muted-foreground">Belum ada form evaluasi terpasang.</p>
        <form
            v-if="available.length > 0"
            class="flex flex-wrap items-end gap-3 rounded-lg border border-dashed p-3"
            @submit.prevent="submit"
        >
            <FormField
                :id="`${prefix}-form`"
                label="Pasang Form Evaluasi"
                class="min-w-56 flex-1"
                :error="attach.errors.review_evaluation_form_id"
            >
                <template #default="{ describedBy }">
                    <OptionSelect
                        :id="`${prefix}-form`"
                        v-model="attach.review_evaluation_form_id"
                        :options="available"
                        placeholder="Pilih Form Evaluasi terbit"
                        :described-by="describedBy"
                    />
                </template>
            </FormField>
            <div class="flex items-center gap-2 pb-2">
                <Switch :id="`${prefix}-required`" v-model="attach.is_required" />
                <Label :for="`${prefix}-required`" class="font-normal">Wajib diisi reviewer</Label>
            </div>
            <Button
                type="submit"
                :disabled="attach.processing || attach.review_evaluation_form_id === null"
                ><Plus class="size-4" />Pasang</Button
            >
        </form>
        <p v-else-if="evaluationOptions.length === 0" class="text-sm text-muted-foreground">
            Belum ada Form Evaluasi yang terbit. Susun dan terbitkan dulu di pustaka Form Evaluasi.
        </p>
        <AppBanner v-if="errors.form_phase_detail_id" tone="denial">
            {{ errors.form_phase_detail_id }}
        </AppBanner>
    </section>
</template>
