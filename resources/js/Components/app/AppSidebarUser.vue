<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3'
import { LogOut, Settings } from 'lucide-vue-next'
import { computed, ref } from 'vue'

import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import type { PageProps } from '@/types'

const page = usePage<PageProps>()
const user = computed(() => page.props.auth.user)
const initial = computed(() => user.value.name.charAt(0).toUpperCase())

const confirmingLogout = ref(false)

const logout = () => router.post(route('logout'))
</script>

<template>
    <div class="flex items-center gap-2 rounded-md p-2">
        <Link :href="route('profile.edit')" class="flex min-w-0 flex-1 items-center gap-2">
            <span
                class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-medium text-primary-foreground"
            >
                {{ initial }}
            </span>
            <span class="min-w-0">
                <span class="block truncate text-sm font-medium">{{ user.name }}</span>
                <span class="block truncate text-xs text-muted-foreground">{{ user.email }}</span>
            </span>
        </Link>
        <Link
            :href="route('profile.edit')"
            aria-label="Pengaturan"
            class="rounded-md p-1.5 text-muted-foreground hover:bg-sidebar-accent"
        >
            <Settings class="size-4" />
        </Link>
        <button
            type="button"
            aria-label="Keluar"
            class="rounded-md p-1.5 text-muted-foreground hover:bg-sidebar-accent"
            @click="confirmingLogout = true"
        >
            <LogOut class="size-4" />
        </button>
    </div>

    <ConfirmDialog
        v-model:open="confirmingLogout"
        title="Keluar dari SIMPAS?"
        description="Sesi Anda akan diakhiri dan Anda perlu masuk kembali."
        confirm-label="Keluar"
        @confirm="logout"
    />
</template>
