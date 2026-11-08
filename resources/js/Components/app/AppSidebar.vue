<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import { computed, onUnmounted } from 'vue'

import AppSidebarGroup from '@/Components/app/AppSidebarGroup.vue'
import AppSidebarUser from '@/Components/app/AppSidebarUser.vue'
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarRail,
    useSidebar,
} from '@/Components/ui/sidebar'
import { navGroups, visibleNavGroups } from '@/lib/navigation'
import type { PageProps } from '@/types'

const page = usePage<PageProps>()
const { isMobile, setOpenMobile } = useSidebar()

// Layout persisten tidak dirender ulang, jadi drawer mobile ditutup tiap pindah halaman.
onUnmounted(router.on('navigate', () => setOpenMobile(false)))

// Mengetuk menu halaman yang sedang dibuka hanya replace tanpa 'navigate'.
const closeOnLinkTap = (event: MouseEvent) => {
    if (!isMobile.value || !(event.target instanceof Element)) return
    if (event.target.closest('a')) setOpenMobile(false)
}

// navigation null hanya untuk tamu; di dalam layout aplikasi selalu terisi.
const flags = computed(
    () =>
        page.props.navigation ?? {
            hasOutputSubmission: false,
            hasMonevSubmission: false,
            unreadAnnouncements: 0,
            unreadNotifications: 0,
        }
)
const groups = computed(() =>
    visibleNavGroups(navGroups, page.props.auth.user?.permissions ?? [], flags.value)
)
</script>

<template>
    <Sidebar collapsible="offcanvas">
        <SidebarHeader class="px-4 py-4">
            <div class="text-lg leading-tight font-semibold">SIMPAS v2</div>
            <div class="text-muted-foreground text-xs">LPPM ITK</div>
        </SidebarHeader>

        <SidebarContent @click="closeOnLinkTap">
            <AppSidebarGroup
                v-for="(group, index) in groups"
                :key="group.label ?? index"
                :group="group"
                :flags="flags"
            />
        </SidebarContent>

        <SidebarFooter @click="closeOnLinkTap">
            <AppSidebarUser />
        </SidebarFooter>
        <SidebarRail />
    </Sidebar>
</template>
