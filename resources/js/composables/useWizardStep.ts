import { router, usePage } from '@inertiajs/vue3'
import { computed, reactive, ref } from 'vue'

import type { WizardField, WizardFieldValue, WizardSubmission } from '@/types/submission-wizard'

type Response = { form_field_id: number; value: WizardFieldValue }

// Satu POST per perpindahan langkah: jawaban langkah ini dikirim, lalu backend membuka langkah
// tujuan. Field non-berkas selalu dikirim (kosong = dikosongkan); berkas hanya bila ada yang baru.
export function useWizardStep(submission: WizardSubmission, step: number, fields: WizardField[]) {
    const values = reactive<Record<number, WizardFieldValue>>(
        Object.fromEntries(fields.map((f) => [f.id, f.type === 'file' ? null : f.value]))
    )
    const processing = ref(false)
    const sentFieldIds = ref<number[]>([])
    const page = usePage()

    const responses = (): Response[] =>
        fields.flatMap((f): Response[] => {
            const value = values[f.id] ?? null
            if (f.type === 'file')
                return value instanceof File ? [{ form_field_id: f.id, value }] : []
            return [{ form_field_id: f.id, value: value === '' ? null : value }]
        })

    const go = (goto: number) => {
        const sent = responses()
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

    // Error ukuran berkas datang sebagai `responses.{i}.value`; dibaca ulang per field.
    const errors = computed(() => {
        const keyed: Record<string, string> = {}
        for (const [key, message] of Object.entries(page.props.errors ?? {})) {
            const index = /^responses\.(\d+)\.value$/.exec(key)?.[1]
            const fieldId = index === undefined ? undefined : sentFieldIds.value[Number(index)]
            keyed[fieldId === undefined ? key : `field_${fieldId}`] = message
        }
        return keyed
    })

    return { values, go, processing, errors }
}
