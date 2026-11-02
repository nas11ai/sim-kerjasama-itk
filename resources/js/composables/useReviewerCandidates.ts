import { usePartialSearch } from '@/composables/usePartialSearch'

// Kandidat reviewer penugasan lewat partial reload prop `reviewerCandidates`.
export function useReviewerCandidates() {
    return usePartialSearch('reviewerCandidates', 'reviewer_search')
}
