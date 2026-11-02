const dateFormat = new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
})

const rupiahFormat = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
})

export function formatDate(iso: string | null): string {
    return iso === null ? '—' : dateFormat.format(new Date(iso))
}

// Tenggat dibaca di zona aplikasi (config app.timezone), bukan zona peramban.
const dateTimeFormat = new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    timeZone: 'Asia/Makassar',
})

export function formatDateTime(iso: string): string {
    return dateTimeFormat.format(new Date(iso)).replace(',', '')
}

export function formatRupiah(amount: number): string {
    return rupiahFormat.format(amount)
}

const RELATIVE_UNITS: [label: string, seconds: number][] = [
    ['minggu', 7 * 24 * 3600],
    ['hari', 24 * 3600],
    ['jam', 3600],
    ['menit', 60],
]
const RELATIVE_LIMIT_SECONDS = 4 * 7 * 24 * 3600

/** "2 jam lalu" ala wireframe; lewat empat minggu jarak relatif tak lagi berguna, jadi tanggal. */
export function formatRelative(iso: string, now: Date = new Date()): string {
    const elapsed = Math.floor((now.getTime() - new Date(iso).getTime()) / 1000)

    if (elapsed >= RELATIVE_LIMIT_SECONDS) return formatDate(iso)

    const unit = RELATIVE_UNITS.find(([, seconds]) => elapsed >= seconds)

    return unit === undefined ? 'Baru saja' : `${Math.floor(elapsed / unit[1])} ${unit[0]} lalu`
}
