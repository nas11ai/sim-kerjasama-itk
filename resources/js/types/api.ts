export interface FormPhaseFaculty {
    id: number
    title: string
    faculty_id: number
    faculty_name: string
    total_forms: number
    total_submissions: number
}

export interface FormPhaseProdi {
    id: number
    title: string
    faculty_id: number
    faculty_name: string
    study_program_id: number
    study_program_name: string
    total_forms: number
    total_submissions: number
}

export interface FormPhaseStatus {
    id: number
    title: string
    total_submissions: number
    pending: number
    under_review: number
    approved: number
    rejected: number
    revision: number
}

export interface FormPhaseTotal {
    id: number
    title: string
    total_forms: number
    total_submissions: number
}

export interface FormPhaseByPeriod {
    submission_period_id: number
    submission_period_name: string
    total_forms: number
    total_submissions: number
}

export interface RecentSubmission {
    id: number
    name: string
    total_forms: number
    total_submissions: number
}

export interface TotalSubmission {
    id: number
    name: string
    total_forms: number
    total_submissions: number
}

export interface TotalByStatus {
    status: string
    total: number
}

export interface TotalByFaculty {
    id: number
    name: string
    total: number
}

export interface TotalByProdi {
    id: number
    name: string
    total: number
}

export interface UserRecent {
    id: number
    name: string
    email: string
    created_at: string
}

export interface ReviewerRecent {
    id: number
    user_id: number
    users_name: string
    reviewer_role_id: number
}

export interface EvaluationStatus {
    evaluation_status: string
    total: number
}

export interface ReviewerByYear {
    year: number
    total: number
}

export interface ReviewerByFaculty {
    id: number
    name: string
    total: number
}

export interface ReviewerByProdi {
    id: number
    name: string
    total: number
}

export interface ReviewerActiveStatus {
    user_id: number
    reviewer_role_id: number
}
