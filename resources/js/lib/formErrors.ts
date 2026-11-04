// Pesan galat Inertia yang kuncinya diawali `prefix` (mis. `fields.2.` untuk baris ke-2).
export const errorsUnder = (errors: Record<string, string>, prefix: string): string[] =>
    Object.entries(errors).flatMap(([key, message]) => (key.startsWith(prefix) ? [message] : []))
