<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { useEventListener } from '@vueuse/core'
import { Search } from 'lucide-vue-next'
import { ref } from 'vue'

import CommandPalette from '@/Components/app/CommandPalette.vue'
import { Button } from '@/Components/ui/button'
import { SidebarTrigger } from '@/Components/ui/sidebar'

const paletteOpen = ref(false)
const shortcutLabel = navigator.userAgent.includes('Mac') ? '⌘K' : 'Ctrl K'

// topbar.md — Ctrl+K / Cmd+K dari halaman mana pun: topbar dirender di setiap halaman AppLayout.
useEventListener(window, 'keydown', (event: KeyboardEvent) => {
    if (event.key.toLowerCase() !== 'k' || !(event.ctrlKey || event.metaKey)) return
    event.preventDefault()
    paletteOpen.value = true
})
</script>

<template>
    <header class="flex h-14 shrink-0 items-center gap-2 border-b px-4">
        <SidebarTrigger />
        <!-- topbar.md — Logo ITK kembali ke Dashboard; di ponsel sidebar tertutup (E2E-18). -->
        <Link :href="route('dashboard')" class="shrink-0 rounded-sm">
            <img
                src="/images/Logo-ITK.png"
                alt="Logo ITK"
                width="947"
                height="1172"
                class="h-8 w-auto"
            />
        </Link>
        <Button
            variant="outline"
            class="text-muted-foreground w-full max-w-md min-w-0 justify-start font-normal"
            @click="paletteOpen = true"
        >
            <Search />
            <span class="truncate">Cari halaman, pengajuan, pengguna...</span>
            <kbd
                class="bg-muted ml-auto hidden rounded border px-1.5 font-mono text-[10px] font-medium sm:inline-flex"
            >
                {{ shortcutLabel }}
            </kbd>
        </Button>
        <CommandPalette v-model:open="paletteOpen" />
    </header>
</template>
