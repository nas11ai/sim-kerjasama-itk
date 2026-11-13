import type { ReviewRoundProgress } from '@/types/review'

// BR-REV-16 — satu kalimat kemajuan putaran, dipakai layar reviewer dan layar keputusan Operator.
export const roundProgressLabel = (progress: ReviewRoundProgress): string =>
    `Putaran ${progress.number}: ${progress.finished} dari ${progress.total} penilai selesai`
