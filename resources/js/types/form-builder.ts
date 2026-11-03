export interface FormType {
    id: number
    name: string
}

export interface FieldType {
    id: number
    name: string
}

export interface PhaseType {
    id: number
    name: string
}

export interface FormPhase {
    id: number
    title: string
    description?: string
    is_active: boolean
    form_phase_details?: Array<{ id: number }>
}

// Sumber: CompleteFormBuilderController::create — label diturunkan dari jenis × siklus (BR-FE-25).
export interface SubmissionDate {
    id: number
    label: string
    datetime: string
}

export interface SubmissionPeriod {
    id: number
    name: string
    submission_dates?: SubmissionDate[]
}
