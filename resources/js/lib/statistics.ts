// Pemformatan angka statistik; angkanya sendiri dihitung backend (R1 Global Constraints).

export function formatShare(share: number): string {
    return `${Math.round(share * 100)}%`
}

// W152 — umur sebagai fakta, bukan peringatan berambang.
export function formatAge(days: number): string {
    return days === 0 ? 'hari ini' : `${days} hari`
}

// Umur item Tindak Lanjut (W152): "12 hari sejak submit" / "disubmit hari ini".
export function formatSinceSubmit(days: number): string {
    return days === 0 ? 'dikirim hari ini' : `${formatAge(days)} sejak dikirim`
}
