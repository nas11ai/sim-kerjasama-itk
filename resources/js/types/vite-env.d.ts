/// <reference types="vite/client" />

interface ImportMetaEnv {
    // RT1 — kosong di build tanpa Reverb (CI, test); echo() lalu tidak membuka koneksi.
    readonly VITE_REVERB_APP_KEY: string | undefined
    readonly VITE_REVERB_HOST: string | undefined
    readonly VITE_REVERB_PORT: string | undefined
    readonly VITE_REVERB_SCHEME: string | undefined
}

interface ImportMeta {
    readonly env: ImportMetaEnv
}
