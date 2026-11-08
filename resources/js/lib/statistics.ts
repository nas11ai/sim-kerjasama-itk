// Pemformatan angka statistik; angkanya sendiri dihitung backend (R1 Global Constraints).

export function formatShare(share: number): string {
    return `${Math.round(share * 100)}%`
}

// W152 — umur sebagai fakta, bukan peringatan berambang.
export function formatAge(days: number): string {
    return days === 0 ? 'hari ini' : `${days} hari`
}
