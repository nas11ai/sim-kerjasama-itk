<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { ArrowRight, Megaphone, Paperclip } from 'lucide-vue-next'

import { Button } from '@/Components/ui/button'
import { formatDate } from '@/lib/format'
import type { AnnouncementSummary } from '@/types/announcement'

// K7 (pemilik 2026-11-13) — halaman pertama tamu: apa SIMPAS, cara mulai, dan pengumuman publik.
defineProps<{ canRegister: boolean; announcements: AnnouncementSummary[] }>()

const steps = [
    {
        title: 'Daftar akun',
        text: 'Gunakan email @itk.ac.id, lalu tunggu verifikasi NIDN oleh Operator.',
    },
    {
        title: 'Ajukan proposal',
        text: 'Pilih skema yang dibuka, isi formulir, dan kirim sebelum tenggat.',
    },
    {
        title: 'Pantau penilaian',
        text: 'Lihat status pengajuan dan catatan penilai dari satu tempat.',
    },
    {
        title: 'Laporkan hasil',
        text: 'Unggah laporan monev dan catat luaran setelah pengajuan diterima.',
    },
]

const year = new Date().getFullYear()
</script>

<template>
    <Head title="Selamat Datang" />
    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <header class="border-b">
            <div
                class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <img
                        src="/images/Logo-ITK.png"
                        alt="Logo ITK"
                        class="size-9 shrink-0 object-contain"
                    />
                    <div class="min-w-0 leading-tight">
                        <div class="font-semibold">SIMPAS</div>
                        <div class="truncate text-xs text-muted-foreground">
                            LPPM Institut Teknologi Kalimantan
                        </div>
                    </div>
                </div>
                <nav class="flex shrink-0 items-center gap-2" aria-label="Akun">
                    <Button
                        v-if="canRegister"
                        as-child
                        variant="ghost"
                        size="sm"
                        class="hidden sm:inline-flex"
                    >
                        <Link :href="route('register')">Daftar</Link>
                    </Button>
                    <Button as-child size="sm">
                        <Link :href="route('login')">Masuk</Link>
                    </Button>
                </nav>
            </div>
        </header>

        <main class="flex-1">
            <section
                class="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:px-6 lg:grid-cols-[1.1fr_1fr] lg:py-20"
            >
                <div class="flex flex-col justify-center gap-6">
                    <p class="text-sm font-medium text-primary">
                        Penelitian dan Pengabdian kepada Masyarakat
                    </p>
                    <h1 class="text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                        Satu tempat untuk mengajukan, menilai, dan melaporkan kegiatan LPPM ITK
                    </h1>
                    <p class="max-w-prose text-muted-foreground text-pretty">
                        SIMPAS dipakai dosen untuk mengajukan proposal hibah internal, dipakai
                        penilai untuk mengevaluasi, dan dipakai LPPM untuk memantau sampai luaran
                        tercatat.
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <Button as-child size="lg">
                            <Link :href="route('login')"
                                >Masuk ke SIMPAS<ArrowRight class="size-4"
                            /></Link>
                        </Button>
                        <Button v-if="canRegister" as-child size="lg" variant="outline">
                            <Link :href="route('register')">Daftar akun dosen</Link>
                        </Button>
                    </div>
                </div>

                <section aria-labelledby="welcome-announcements" class="rounded-lg border bg-card">
                    <h2
                        id="welcome-announcements"
                        class="flex items-center gap-2 border-b px-5 py-3 text-sm font-semibold"
                    >
                        <Megaphone
                            class="size-4 text-muted-foreground"
                            aria-hidden="true"
                        />Pengumuman
                    </h2>
                    <ul v-if="announcements.length > 0" class="divide-y">
                        <li v-for="announcement in announcements" :key="announcement.id">
                            <Link
                                :href="route('announcements.detail', announcement.id)"
                                class="block px-5 py-3 hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none"
                            >
                                <span class="block font-medium">{{ announcement.title }}</span>
                                <span
                                    v-if="announcement.excerpt"
                                    class="mt-0.5 line-clamp-2 block text-sm text-muted-foreground"
                                    >{{ announcement.excerpt }}</span
                                >
                                <span
                                    class="mt-1 flex items-center gap-3 text-xs text-muted-foreground"
                                >
                                    <span>{{ formatDate(announcement.publishedAt) }}</span>
                                    <span
                                        v-if="announcement.attachmentCount > 0"
                                        class="inline-flex items-center gap-1"
                                        ><Paperclip class="size-3" aria-hidden="true" />{{
                                            announcement.attachmentCount
                                        }}
                                        lampiran</span
                                    >
                                </span>
                            </Link>
                        </li>
                    </ul>
                    <p v-else class="px-5 py-6 text-sm text-muted-foreground">
                        Belum ada pengumuman publik.
                    </p>
                </section>
            </section>

            <section aria-labelledby="welcome-steps" class="border-t bg-muted/40">
                <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
                    <h2 id="welcome-steps" class="text-lg font-semibold">Cara mulai</h2>
                    <ol class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <li v-for="(step, index) in steps" :key="step.title" class="flex gap-3">
                            <span
                                class="flex size-7 shrink-0 items-center justify-center rounded-full border bg-background text-sm font-medium"
                                aria-hidden="true"
                                >{{ index + 1 }}</span
                            >
                            <div>
                                <h3 class="font-medium">{{ step.title }}</h3>
                                <p class="mt-1 text-sm text-muted-foreground">{{ step.text }}</p>
                            </div>
                        </li>
                    </ol>
                </div>
            </section>
        </main>

        <footer class="border-t">
            <div class="mx-auto max-w-6xl px-4 py-4 text-xs text-muted-foreground sm:px-6">
                &copy; {{ year }} LPPM Institut Teknologi Kalimantan
            </div>
        </footer>
    </div>
</template>
