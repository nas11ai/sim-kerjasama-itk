export enum SubmissionStatus {
    PENDING = 'pending',
    UNDER_REVIEW = 'under_review',
    NEEDS_REVISION = 'needs_revision',
    APPROVED = 'approved',
    REJECTED = 'rejected',
}

export const SubmissionStatusLabels: Record<SubmissionStatus, string> = {
    [SubmissionStatus.PENDING]: 'Menunggu Review',
    [SubmissionStatus.UNDER_REVIEW]: 'Sedang Direview',
    [SubmissionStatus.NEEDS_REVISION]: 'Perlu Revisi',
    [SubmissionStatus.APPROVED]: 'Disetujui',
    [SubmissionStatus.REJECTED]: 'Ditolak',
}

export const SubmissionStatusColors: Record<SubmissionStatus, string> = {
    [SubmissionStatus.PENDING]: 'yellow',
    [SubmissionStatus.UNDER_REVIEW]: 'blue',
    [SubmissionStatus.NEEDS_REVISION]: 'orange',
    [SubmissionStatus.APPROVED]: 'green',
    [SubmissionStatus.REJECTED]: 'red',
}

export function getSubmissionStatusLabel(status: SubmissionStatus): string {
    return SubmissionStatusLabels[status] ?? status
}

export function getSubmissionStatusColor(status: SubmissionStatus): string {
    return SubmissionStatusColors[status] ?? 'gray'
}

export function getSubmissionStatusOptions() {
    return (Object.values(SubmissionStatus) as SubmissionStatus[]).map((status) => ({
        value: status,
        label: getSubmissionStatusLabel(status),
        color: getSubmissionStatusColor(status),
    }))
}
