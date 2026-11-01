import { useHttp } from '@inertiajs/vue3'
import { computed } from 'vue'

// fe-v2-design §4.7 K6a — gambar sisipan hanya lewat unggahan; `src` yang
// disisipkan selalu jawaban server, tidak pernah URL ketikan pengguna.
export function useInlineImageUpload(url: string) {
    const http = useHttp<{ image: File | null }, { url: string }>({ image: null })

    async function upload(file: File): Promise<string | null> {
        http.image = file
        try {
            const response = await http.post(url)
            return response.url
        } catch {
            // Galat validasi sudah terisi di `http.errors`; galat jaringan dibiarkan senyap.
            return null
        }
    }

    return {
        upload,
        uploading: computed(() => http.processing),
        error: computed(() => http.errors.image),
    }
}
