<!-- resources/js/Layouts/AuthenticatedLayout.vue -->
<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { SidebarProvider, SidebarTrigger, SidebarInset } from "@/Components/ui/sidebar";
import AppSidebar from "@/Components/AppSidebar.vue";

// Reactive state for sidebar
const isSidebarOpen = ref(true);
const isMobile = ref(false);

// Check if mobile on mount
onMounted(() => {
    const checkMobile = () => {
        isMobile.value = window.innerWidth < 768;
        if (isMobile.value) {
            isSidebarOpen.value = false;
        }
    };

    checkMobile();
    window.addEventListener('resize', checkMobile);

    onUnmounted(() => {
        window.removeEventListener('resize', checkMobile);
    });
});

const toggleSidebar = () => {
    isSidebarOpen.value = !isSidebarOpen.value;
};
</script>

<template>
    <SidebarProvider :defaultOpen="isSidebarOpen">
        <div class="flex min-h-screen w-full bg-background">
            <!-- Sidebar -->
            <AppSidebar />

            <!-- Main content with SidebarInset -->
            <SidebarInset class="flex flex-col flex-1 min-w-0">
                <!-- Header -->
                <header class="sticky top-0 z-40 flex h-16 shrink-0 items-center gap-2 border-b bg-background px-4"
                    v-if="$slots.header">
                    <SidebarTrigger class="-ml-1" />
                    <div class="h-4 w-px bg-border mx-2" />
                    <div class="flex-1">
                        <slot name="header" />
                    </div>
                </header>

                <!-- Header fallback -->
                <header class="sticky top-0 z-40 flex h-16 shrink-0 items-center gap-2 border-b bg-background px-4"
                    v-else>
                    <SidebarTrigger class="-ml-1" />
                </header>

                <!-- Page content -->
                <main class="flex-1 p-4 md:p-6">
                    <slot />
                </main>
            </SidebarInset>
        </div>
    </SidebarProvider>
</template>
