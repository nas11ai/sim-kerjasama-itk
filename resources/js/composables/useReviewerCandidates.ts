import { usePartialSearch } from '@/composables/usePartialSearch'

// Kandidat reviewer penugasan lewat partial reload prop `reviewerCandidates`.
export function useReviewerCandidates(extra: Record<string, string | number> = {}) {
    return usePartialSearch('reviewerCandidates', 'reviewer_search', extra)
}
