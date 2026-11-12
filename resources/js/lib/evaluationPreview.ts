import { typeOf, type EditableEvaluationField } from '@/lib/evaluationDefinition'
import type { FieldTypeOption } from '@/types/form-engine'
import type { EvaluationForm } from '@/types/review'

// §4.5 — Pratinjau: isi draf dalam bentuk yang dibaca EvaluationFormPanel, persis seperti lembar
// reviewer (ReviewerEvaluation). Opsi tanpa `value` memakai label sebagai nilainya, seperti backend.
export function toPreviewForm(
    title: string,
    description: string | null,
    fields: EditableEvaluationField[],
    types: FieldTypeOption[]
): EvaluationForm {
    return {
        formId: 0,
        assignmentId: null,
        title,
        description,
        required: false,
        state: 'draft',
        dueDate: null,
        submittedAt: null,
        finalNotes: null,
        canEdit: false,
        versionLabel: 'Draf',
        fields: fields.map((field, index) => ({
            id: index + 1,
            label: field.label,
            type: typeOf(types, field.fieldTypeId)?.name ?? 'text',
            required: field.isRequired,
            value: null,
            options: field.options.map((option) => ({
                value: option.value ?? option.label,
                label: option.label,
            })),
            fileName: null,
            downloadUrl: null,
        })),
    }
}
