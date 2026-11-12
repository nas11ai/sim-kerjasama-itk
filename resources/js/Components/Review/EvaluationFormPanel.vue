<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import { Check, ClipboardList } from 'lucide-vue-next'
import { computed, ref } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import FormField from '@/Components/app/FormField.vue'
import InfoGrid from '@/Components/app/InfoGrid.vue'
import WizardFieldInput from '@/Components/Submission/Wizard/WizardFieldInput.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Textarea } from '@/Components/ui/textarea'
import { formatDate } from '@/lib/format'
import type { EvaluationForm } from '@/types/review'
import type { WizardField } from '@/types/submission-wizard'

// `preview` — Pratinjau editor pustaka (§4.5): field tampil seperti lembar reviewer, terkunci, tanpa aksi.
const props = defineProps<{ submissionId: number; form: EvaluationForm; preview?: boolean }>()

const saved: Record<string, string | null> = Object.fromEntries(
    props.form.fields.map((field) => [String(field.id), field.value])
)
const answers = useForm({ responses: saved, final_notes: props.form.finalNotes ?? '' })
const confirming = ref(false)

const closed = computed(() => props.form.state === 'submitted' || props.form.state === 'locked')
const editing = computed(
    () => props.form.state === 'draft' && (props.form.canEdit || props.preview === true)
)
const assignment = computed(() => String(props.form.assignmentId))

const shown = (field: WizardField) =>
    field.options.find((option) => option.value === field.value)?.label ?? field.value ?? '—'

const sheet = computed(() => [
    ...props.form.fields.map((field) => ({ label: field.label, value: shown(field) })),
    { label: 'Catatan Kualitatif', value: props.form.finalNotes ?? '—' },
    { label: 'Disubmit', value: formatDate(props.form.submittedAt) },
])

// Evaluasi tidak punya field berkas; nilai selain teks dianggap kosong.
const setAnswer = (fieldId: number, value: string | File | null) =>
    (answers.responses[String(fieldId)] = typeof value === 'string' ? value : null)

const start = () =>
    router.post(
        route('reviewer.evaluation-form.start'),
        { submission_id: props.submissionId, review_evaluation_form_id: props.form.formId },
        { preserveScroll: true }
    )

const save = () =>
    answers.post(route('reviewer.evaluation-form.save-draft', assignment.value), {
        preserveScroll: true,
    })

const submit = () =>
    answers.post(route('reviewer.evaluation-form.submit', assignment.value), {
        preserveScroll: true,
        onFinish: () => (confirming.value = false),
    })
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- BR-REV-18 — reviewer tahu versi lembar yang diisinya -->
        <Badge variant="outline" class="self-start" data-version>{{ form.versionLabel }}</Badge>
        <p v-if="form.description" class="text-sm text-muted-foreground">{{ form.description }}</p>
        <p v-if="form.dueDate && !closed" class="text-xs text-muted-foreground">
            Batas evaluasi: {{ formatDate(form.dueDate) }}
        </p>

        <!-- BR-FE-17 — tenggat evaluasi milik reviewer sendiri, bukan batas submit peneliti -->
        <AppBanner v-if="form.state === 'overdue'" tone="lock">
            Batas waktu evaluasi sudah lewat. Hubungi Operator LPPM bila membutuhkan perpanjangan.
        </AppBanner>

        <template v-else-if="closed">
            <!-- BR-REV-08 — penilaian terkirim adalah dasar keputusan; koreksi lewat ulir -->
            <AppBanner tone="lock">
                Penilaian sudah dikirim dan tidak dapat diubah. Sampaikan koreksi lewat catatan
                diskusi.
            </AppBanner>
            <InfoGrid :items="sheet" />
        </template>

        <div v-else-if="form.state === 'not_started'" class="flex flex-col items-start gap-2">
            <p class="text-sm text-muted-foreground">Form ini belum Anda mulai.</p>
            <Button v-if="form.canEdit" data-action="start" @click="start">
                <ClipboardList class="size-4" /> Mulai Evaluasi
            </Button>
        </div>

        <form
            v-else-if="editing"
            class="flex flex-col gap-4"
            @submit.prevent="confirming = !preview"
        >
            <AppBanner v-if="answers.errors.responses" tone="denial">
                {{ answers.errors.responses }}
            </AppBanner>
            <fieldset :disabled="preview" class="m-0 flex min-w-0 flex-col gap-4 border-0 p-0">
                <WizardFieldInput
                    v-for="field in form.fields"
                    :key="field.id"
                    :field="field"
                    :model-value="answers.responses[String(field.id)] ?? null"
                    @update:model-value="(value) => setAnswer(field.id, value)"
                />
                <FormField
                    :id="`final-notes-${form.formId}`"
                    label="Catatan Kualitatif"
                    :error="answers.errors.final_notes"
                >
                    <template #default="{ describedBy }">
                        <Textarea
                            :id="`final-notes-${form.formId}`"
                            v-model="answers.final_notes"
                            placeholder="Masukan perbaikan untuk peneliti..."
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>
            </fieldset>
            <div v-if="!preview" class="flex flex-wrap justify-between gap-2">
                <Button
                    type="button"
                    variant="outline"
                    data-action="save"
                    :disabled="answers.processing"
                    @click="save"
                >
                    Simpan Draft
                </Button>
                <Button type="submit" data-action="submit" :disabled="answers.processing">
                    <Check class="size-4" /> Submit Evaluasi
                </Button>
            </div>
            <p v-if="!preview" class="text-xs text-muted-foreground">
                Setelah evaluasi disubmit, isian tidak dapat diubah lagi — pastikan skor dan catatan
                sudah final.
            </p>
        </form>

        <ConfirmDialog
            v-model:open="confirming"
            title="Kirim evaluasi?"
            description="Setelah dikirim, penilaian tidak dapat diubah lagi."
            confirm-label="Submit Evaluasi"
            :processing="answers.processing"
            @confirm="submit"
        />
    </div>
</template>
