import { router, usePage } from '@inertiajs/vue3'
import { computed, reactive, ref } from 'vue'

import { fieldResponses, initialFieldValues } from '@/lib/fieldResponses'
import { responseErrorsToFields } from '@/lib/formErrors'
import type { WizardField, WizardFieldValue, WizardSubmission } from '@/types/submission-wizard'

// Satu POST per perpindahan langkah: jawaban langkah ini dikirim, lalu backend membuka langkah
// tujuan. Field non-berkas selalu dikirim (kosong = dikosongkan); berkas hanya bila ada yang baru.
export function useWizardStep(submission: WizardSubmission, step: number, fields: WizardField[]) {
    const values = reactive<Record<number, WizardFieldValue>>(initialFieldValues(fields))
    const processing = ref(false)
    const sentFieldIds = ref<number[]>([])
    const page = usePage()

    const go = (goto: number) => {
        const sent = fieldResponses(fields, values)
        sentFieldIds.value = sent.map((r) => r.form_field_id)
        router.post(
            route('user.submissions.wizard.save', [String(submission.id), String(step)]),
            { goto, last_updated_at: submission.lastUpdatedAt, responses: sent },
            {
                forceFormData: sent.some((r) => r.value instanceof File),
                preserveScroll: true,
                preserveState: 'errors',
                onStart: () => (processing.value = true),
                onFinish: () => (processing.value = false),
            }
        )
    }

    const errors = computed(() =>
        responseErrorsToFields(page.props.errors ?? {}, sentFieldIds.value)
    )

    return { values, go, processing, errors }
}
