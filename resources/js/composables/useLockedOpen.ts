import type { Ref } from 'vue'

// Dialog yang sedang memproses tak bisa ditutup (Batal, Esc, klik luar) supaya hasilnya tetap terbaca.
export function useLockedOpen(open: Ref<boolean>, processing: () => boolean) {
    return (next: boolean) => {
        if (!next && processing()) {
            return
        }
        open.value = next
    }
}
