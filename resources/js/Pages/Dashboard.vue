<script setup lang="ts">
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
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
            <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
                <div class="p-6">
                    <h3 class="text-2xl font-semibold leading-none tracking-tight mb-2">
                        Welcome back!
                    </h3>
                    <p class="text-sm text-muted-foreground">
                        You're successfully logged in to your dashboard.
                    </p>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
                    <div class="p-6 flex flex-row items-center justify-between space-y-0 pb-2">
                        <h3 class="tracking-tight text-sm font-medium">Total Users</h3>
                    </div>
                    <div class="p-6 pt-0">
                        <div class="text-2xl font-bold">1,234</div>
                        <p class="text-xs text-muted-foreground">+12% from last month</p>
                    </div>
                </div>

                <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
                    <div class="p-6 flex flex-row items-center justify-between space-y-0 pb-2">
                        <h3 class="tracking-tight text-sm font-medium">Revenue</h3>
                    </div>
                    <div class="p-6 pt-0">
                        <div class="text-2xl font-bold">$12,345</div>
                        <p class="text-xs text-muted-foreground">+8% from last month</p>
                    </div>
                </div>

                <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
                    <div class="p-6 flex flex-row items-center justify-between space-y-0 pb-2">
                        <h3 class="tracking-tight text-sm font-medium">Orders</h3>
                    </div>
                    <div class="p-6 pt-0">
                        <div class="text-2xl font-bold">567</div>
                        <p class="text-xs text-muted-foreground">+23% from last month</p>
                    </div>
                </div>

                <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
                    <div class="p-6 flex flex-row items-center justify-between space-y-0 pb-2">
                        <h3 class="tracking-tight text-sm font-medium">Active Users</h3>
                    </div>
                    <div class="p-6 pt-0">
                        <div class="text-2xl font-bold">890</div>
                        <p class="text-xs text-muted-foreground">+5% from last month</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
