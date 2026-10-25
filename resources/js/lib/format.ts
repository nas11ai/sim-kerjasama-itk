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

export function formatRupiah(amount: number): string {
    return rupiahFormat.format(amount)
}
