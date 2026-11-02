<script setup lang="ts">
// Frame: SHARED-01-05
import { Head, Link } from '@inertiajs/vue3'
import { CircleAlert, Clock, LogOut } from 'lucide-vue-next'
import { computed } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import InfoGrid from '@/Components/app/InfoGrid.vue'
import { Button } from '@/Components/ui/button'
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/Components/ui/empty'
import AuthLayout from '@/Layouts/AuthLayout.vue'
import { formatDate } from '@/lib/format'
import type { VerificationStatusPageProps } from '@/types/auth'

const props = defineProps<VerificationStatusPageProps>()

const rejected = computed(() => props.verification.status === 'rejected')

const details = computed(() => [
    {
        label: rejected.value ? 'NIDN diajukan' : 'NIDN terdaftar',
        value: props.verification.nidn ?? '—',
    },
    { label: 'Email', value: props.verification.email },
    { label: 'Status', value: props.verification.statusLabel },
    // W5 — kapan penolakan terjadi, dari jejak audit (W53).
    ...(rejected.value
        ? [{ label: 'Ditolak pada', value: formatDate(props.verification.rejectedAt) }]
        : []),
])
</script>

<template>
    <Head :title="rejected ? 'Verifikasi Ditolak' : 'Menunggu Verifikasi'" />
    <AuthLayout>
        <!-- BR-IAM-04 — verifikasi NIDN menggerbang submit, bukan login -->
        <Empty class="p-0 md:p-0">
            <EmptyHeader>
                <EmptyMedia variant="icon">
                    <CircleAlert v-if="rejected" />
                    <Clock v-else />
                </EmptyMedia>
                <template v-if="rejected">
                    <EmptyTitle>Verifikasi Ditolak</EmptyTitle>
                    <EmptyDescription>
                        Operator LPPM belum dapat memverifikasi NIDN Anda. Alasannya di bawah —
                        hubungi LPPM untuk koreksi, akun Anda tidak dihapus.
                    </EmptyDescription>
                </template>
                <template v-else>
                    <EmptyTitle>Menunggu Verifikasi</EmptyTitle>
                    <EmptyDescription>
                        Akun Anda sedang menunggu verifikasi NIDN oleh Operator LPPM. Anda sudah
                        dapat masuk, namun fitur pengajuan terbuka setelah akun terverifikasi.
                    </EmptyDescription>
                </template>
            </EmptyHeader>
        </Empty>

        <div class="mt-6 space-y-3">
            <InfoGrid :items="details" />

            <!-- BR-IAM-13 — penolakan tidak terminal; alasannya wajib terbaca -->
            <template v-if="rejected">
                <AppBanner tone="warning"
                    >Alasan: {{ verification.rejectionReason ?? '—' }}</AppBanner
                >
                <AppBanner tone="info">
                    Draft yang sudah Anda siapkan tetap tersimpan dan tetap dapat disunting. Yang
                    tertutup hanya tombol Submit.
                </AppBanner>
            </template>
            <AppBanner v-else tone="info">
                Ada kesalahan input NIDN? Hubungi lppm@itk.ac.id sebelum diverifikasi.
            </AppBanner>
        </div>

        <div class="mt-6 space-y-2">
            <Button as-child class="w-full">
                <Link :href="route('dashboard')">Lanjut ke Dashboard</Link>
            </Button>
            <Button as-child variant="outline" class="w-full">
                <Link :href="route('logout')" method="post" as="button">
                    <LogOut />
                    Keluar
                </Link>
            </Button>
        </div>
    </AuthLayout>
</template>
