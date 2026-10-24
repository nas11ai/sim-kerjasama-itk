<script setup lang="ts">
import AppSidebar from '@/Components/app/AppSidebar.vue'
import AppTopbar from '@/Components/app/AppTopbar.vue'
import { SidebarInset, SidebarProvider } from '@/Components/ui/sidebar'
import { Toaster } from '@/Components/ui/toast'
import { useFlashToasts } from '@/composables/useFlashToasts'

useFlashToasts()
</script>

<template>
    <SidebarProvider>
        <AppSidebar />
        <SidebarInset>
            <AppTopbar />
            <main class="flex-1 p-4 md:p-6">
                <slot />
            </main>
        </SidebarInset>
        <Toaster />
    </SidebarProvider>
</template>
