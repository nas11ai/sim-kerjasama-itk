import type { GlobalSearchResult } from '@/types/search'

/**
 * K2 (W171) — route JSON internal bersesi sama dengan halaman. Pengalihan (sesi habis, akun
 * nonaktif → login) dijadikan kegagalan, supaya HTML login tidak di-parse sebagai JSON.
 */
export async function fetchGlobalSearch(
    term: string,
    signal: AbortSignal
): Promise<GlobalSearchResult> {
    const response = await fetch(route('search', { q: term }), {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        redirect: 'error',
        signal,
    })

    if (!response.ok) {
        throw new Error(`Pencarian gagal (HTTP ${response.status})`)
    }

    const result: GlobalSearchResult = await response.json()

    return result
}
