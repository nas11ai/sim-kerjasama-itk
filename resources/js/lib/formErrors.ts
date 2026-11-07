// Pesan galat Inertia yang kuncinya diawali `prefix` (mis. `fields.2.` untuk baris ke-2).
export const errorsUnder = (errors: Record<string, string>, prefix: string): string[] =>
    Object.entries(errors).flatMap(([key, message]) => (key.startsWith(prefix) ? [message] : []))

// Error ukuran berkas datang sebagai `responses.{i}.value`; dipetakan ke `field_{id}` lewat urutan
// jawaban yang dikirim, supaya tampil di bawah field-nya seperti error kelengkapan.
export const responseErrorsToFields = (
    errors: Record<string, string>,
    sentFieldIds: number[]
): Record<string, string> => {
    const keyed: Record<string, string> = {}
    for (const [key, message] of Object.entries(errors)) {
        const index = /^responses\.(\d+)\.value$/.exec(key)?.[1]
        const fieldId = index === undefined ? undefined : sentFieldIds[Number(index)]
        keyed[fieldId === undefined ? key : `field_${fieldId}`] = message
    }
    return keyed
}
