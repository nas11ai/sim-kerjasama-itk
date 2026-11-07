import { useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

import type { OutputMetadataValue, ResearchOutputFormPageProps } from '@/types/research-output'

interface OutputFormFields {
    output_type: string
    metadata: Record<string, OutputMetadataValue>
    files: File[]
}

const filled = (value: OutputMetadataValue | undefined) =>
    value !== null && value !== undefined && String(value).trim() !== ''

// Isian luaran (RESEARCHER-06-02/03). Kelayakan menulis milik backend (W146); yang dihitung di
// sini hanya kelengkapan isian sebelum Simpan aktif — field wajib (BR-RO-09) dan berkas (BR-RO-04).
export function useOutputForm(props: ResearchOutputFormPageProps) {
    const form = useForm<OutputFormFields>({
        output_type: props.outputType ?? '',
        metadata: { ...props.metadata },
        files: [],
    })

    const definition = computed(() => props.types.find((t) => t.code === form.output_type) ?? null)

    const canSave = computed(() => {
        const type = definition.value
        if (type === null || form.processing) return false
        if (type.fields.some((f) => f.required && !filled(form.metadata[f.key]))) return false

        return !type.requiresFiles || props.files.length + form.files.length > 0
    })

    // Isian tipe lama tidak berlaku untuk tipe lain.
    const changeType = (code: string) => {
        form.output_type = code
        form.metadata = {}
        form.files = []
    }

    // Berkas hanya terkirim lewat multipart; sunting memakai POST + `_method=put`.
    const submit = () => {
        const options = { forceFormData: true, preserveScroll: true }
        const outputId = props.outputId

        if (outputId === null) {
            form.post(route('research-outputs.store', props.submissionId), options)
            return
        }

        form.transform((data) => ({ ...data, _method: 'put' })).post(
            route('research-outputs.update', outputId),
            options
        )
    }

    return { form, definition, canSave, changeType, submit }
}
