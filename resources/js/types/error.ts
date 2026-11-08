// Sumber: App\Http\ErrorPage::respond (fe-v2-design W10/W163).
import type { Auth } from '@/types'

export type ErrorStatus = 403 | 404 | 500 | 503

export interface ErrorPageProps {
    status: ErrorStatus
    // Hanya 403 dengan pesan tulisan manusia; selain itu null.
    message: string | null
    // Props bersama bisa tidak ada: galat yang terjadi sebelum middleware `web`
    // berjalan dirender tanpa HandleInertiaRequests::share.
    auth?: Auth
}
