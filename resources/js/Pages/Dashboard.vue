<script setup lang="ts">
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/vue3";
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <h2 class="text-xl font-semibold leading-tight text-foreground">
                    Dashboard
                </h2>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Welcome Card -->
            <div
                class="rounded-lg border bg-card text-card-foreground shadow-sm"
            >
                <div class="p-6">
                    <h3
                        class="text-2xl font-semibold leading-none tracking-tight mb-2"
                    >
                        Welcome back!
                    </h3>
                    <p class="text-sm text-muted-foreground">
                        You're successfully logged in to your dashboard.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
