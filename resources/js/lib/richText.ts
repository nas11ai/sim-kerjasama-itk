// fe-v2-design §4.7 K6a — cermin daftar izin AnnouncementHtml di server. Pemeriksaan
// di sini hanya kenyamanan penyunting; server tetap menyaring ulang setiap isi.

export const INLINE_IMAGE_PREFIX = '/storage/announcements/inline/'

const ALLOWED_LINK = /^(https?:\/\/|mailto:)/i

export function isAllowedLink(url: string): boolean {
    return ALLOWED_LINK.test(url.trim())
}
