<script setup lang="ts">
// Frame: — (W10, tanpa frame)
// W163 — 403/404 selalu, 500/503 hanya saat debug mati (App\Http\ErrorPage).
import { Head, Link } from '@inertiajs/vue3'
import { CircleAlert, Construction, SearchX, ShieldX, type LucideIcon } from 'lucide-vue-next'
import { computed } from 'vue'

import EmptyState from '@/Components/app/EmptyState.vue'
import { Button } from '@/Components/ui/button'
import AppLayout from '@/Layouts/AppLayout.vue'
import AuthLayout from '@/Layouts/AuthLayout.vue'
import type { ErrorPageProps, ErrorStatus } from '@/types/error'

const props = defineProps<ErrorPageProps>()

const copy: Record<ErrorStatus, { title: string; description: string; icon: LucideIcon }> = {
    403: {
        title: 'Akses ditolak',
        description: 'Anda tidak memiliki akses ke halaman ini.',
        icon: ShieldX,
    },
    404: {
        title: 'Halaman tidak ditemukan',
        description: 'Alamat yang Anda buka tidak ada atau sudah dipindahkan.',
        icon: SearchX,
    },
    500: {
        title: 'Terjadi kesalahan',
        description: 'Coba lagi beberapa saat lagi.',
        icon: CircleAlert,
    },
    503: {
        title: 'Sedang dalam pemeliharaan',
        description: 'Sistem akan kembali sebentar lagi.',
        icon: Construction,
    },
}

const signedIn = computed(() => Boolean(props.auth?.user))
const content = computed(() => copy[props.status])
const description = computed(() => props.message ?? content.value.description)
</script>

<template>
    <Head :title="content.title" />
    <component :is="signedIn ? AppLayout : AuthLayout">
        <div class="flex flex-col items-center gap-6">
            <EmptyState
                :title="content.title"
                :description="description"
                :icon="content.icon"
                class="p-0 md:p-0"
            />
            <Button v-if="signedIn" as-child>
                <Link :href="route('dashboard')">Kembali ke Dasbor</Link>
            </Button>
            <Button v-else as-child variant="outline" class="w-full">
                <Link :href="route('login')">Masuk</Link>
            </Button>
        </div>
    </component>
</template>
