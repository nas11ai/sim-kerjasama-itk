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
        <SidebarInset class="min-w-0">
            <AppTopbar />
            <div class="min-w-0 flex-1 p-4 md:p-6">
                <slot />
            </div>
        </SidebarInset>
        <Toaster />
    </SidebarProvider>
</template>
