import type { SubmissionStatusView } from '@/types/submission-status'

// Cermin App\Data\View\ResearchOutput\OutputWriteAccessData — dihitung backend (W146).
export interface OutputWriteAccess {
    canWrite: boolean
    blockedReason: string | null
    deadline: string | null
    overrideUntil: string | null
}

export interface OutputRow {
    id: number
    typeName: string
    headline: string
    meta: string | null
    fileCount: number
}

export interface OutputSubmissionGroup {
    submissionId: number
    title: string
    scheme: string | null
    status: SubmissionStatusView
    access: OutputWriteAccess
    outputs: OutputRow[]
}

export interface ResearchOutputIndexPageProps {
    groups: OutputSubmissionGroup[]
}

// Cermin App\Data\View\ResearchOutput\OutputFieldSpecData — satu field metadata tipe (BR-RO-09).
export type OutputFieldType = 'text' | 'textarea' | 'number' | 'date' | 'select' | 'url'

export interface OutputFieldSpec {
    key: string
    label: string
    type: OutputFieldType
    required: boolean
    options: { value: string; label: string }[]
    hint: string | null
    emptyLabel: string | null
}

export interface OutputType {
    code: string
    name: string
    requiresFiles: boolean
    fields: OutputFieldSpec[]
}

export interface OutputFile {
    id: number
    name: string
    size: number | null
    url: string
}

export type OutputMetadataValue = string | number | null

// Props ResearchOutput/FormPage — `outputId` null = tambah (RESEARCHER-06-02), selain itu sunting.
export interface ResearchOutputFormPageProps {
    submissionId: number
    submissionTitle: string
    scheme: string | null
    access: OutputWriteAccess
    types: OutputType[]
    outputId: number | null
    outputType: string | null
    metadata: Record<string, OutputMetadataValue>
    files: OutputFile[]
}
