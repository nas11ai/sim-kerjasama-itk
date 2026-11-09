/** K2 — satu hasil grup server command palette (`App\Data\View\SearchHitData`). */
export interface SearchHit {
    id: number
    label: string
    sublabel: string
    url: string
}

/** Respons `GET /cari` (route `search`); grup tanpa izin selalu kosong, bukan 403. */
export interface GlobalSearchResult {
    submissions: SearchHit[]
    users: SearchHit[]
    reviewers: SearchHit[]
}
