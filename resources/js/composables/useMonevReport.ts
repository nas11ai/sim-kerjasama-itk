import { router, usePage } from '@inertiajs/vue3'
import { computed, reactive, ref } from 'vue'

import { fieldResponses, initialFieldValues } from '@/lib/fieldResponses'
import { responseErrorsToFields } from '@/lib/formErrors'
import type { MonevReportHeader } from '@/types/monev'
import type { WizardField, WizardFieldValue } from '@/types/submission-wizard'

// Isian laporan monev (RESEARCHER-05-02): simpan draft dan submit mengirim payload yang sama.
export function useMonevReport(report: MonevReportHeader, fields: WizardField[]) {
    const values = reactive<Record<number, WizardFieldValue>>(initialFieldValues(fields))
    const processing = ref(false)
    const sentFieldIds = ref<number[]>([])
    const page = usePage()

    const update = (fieldId: number, value: WizardFieldValue) => (values[fieldId] = value)

    // `settled` dipanggil saat berhasil maupun ditolak (mis. menutup dialog konfirmasi).
    const send = (name: 'monev.reports.save' | 'monev.reports.submit', settled?: () => void) => {
        const responses = fieldResponses(fields, values)
        sentFieldIds.value = responses.map((r) => r.form_field_id)
        router.post(
            route(name, report.id),
            { last_updated_at: report.lastUpdatedAt, responses },
            {
                forceFormData: responses.some((r) => r.value instanceof File),
                preserveScroll: true,
                onStart: () => (processing.value = true),
                onFinish: () => (processing.value = false),
                onSuccess: settled,
                onError: settled,
            }
        )
    }

    const errors = computed(() =>
        responseErrorsToFields(page.props.errors ?? {}, sentFieldIds.value)
    )
    // BR-IAM-04, BR-MON-05/08 — penolakan yang bukan milik satu field (akun, pembekuan, tenggat).
    const generalErrors = computed(() =>
        Object.entries(errors.value).flatMap(([key, message]) =>
            key.startsWith('field_') ? [] : [message]
        )
    )

    return {
        values,
        errors,
        generalErrors,
        processing,
        update,
        save: () => send('monev.reports.save'),
        submit: (settled?: () => void) => send('monev.reports.submit', settled),
    }
}
