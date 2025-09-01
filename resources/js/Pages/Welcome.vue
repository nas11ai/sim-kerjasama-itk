<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';

const page = usePage()
const user = page.props.auth.user as { roles: string[] }

defineProps<{
    canLogin?: boolean;
    canRegister?: boolean;
    laravelVersion: string;
    phpVersion: string;
    announcements: Array<any>;
}>();

function handleImageError() {
    document.getElementById('screenshot-container')?.classList.add('!hidden');
    document.getElementById('docs-card')?.classList.add('!row-span-1');
    document.getElementById('docs-card-content')?.classList.add('!flex-row');
    document.getElementById('background')?.classList.add('!hidden');
}
</script>

<template>

    <Head title="Welcome" />
    <div class="bg-gray-50 text-black/50 dark:bg-black dark:text-white/50">
        <div
            class="relative flex min-h-screen flex-col items-center selection:bg-[#FF2D20] selection:text-white">
            <div class="relative w-full">
                <header class="grid grid-cols-2 items-center gap-2 px-6 py-10 lg:grid-cols-3 max-w-7xl mx-auto">
                    <div class="flex lg:col-start-2 lg:justify-center">
                        <h1 class="text-xl font-semibold text-gray-800 dark:text-white">
                            SIM KERJASAMA ITK
                        </h1>
                    </div>
                    <nav v-if="canLogin" class="-mx-3 flex flex-1 justify-end">
                        <Link v-if="$page.props.auth.user" :href="user.roles.includes('Super Admin') || user.roles.includes('Admin')
                            ? route('admin.dashboard')
                            : route('user.dashboard')"
                            class="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white">
                        Dashboard
                        </Link>

                        <template v-else>
                            <Link :href="route('login')"
                                class="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white">
                            Log in
                            </Link>

                            <Link v-if="canRegister" :href="route('register')"
                                class="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white">
                            Register
                            </Link>
                        </template>
                    </nav>
                </header>

                <main>
                    <section class="bg-[#191976] py-12 text-white">
                        <div class="max-w-7xl mx-auto px-6">
                            <p class="text-lg">Sistem Informasi Kerja Sama Institut Teknologi Kalimantan</p>
                        </div>
                    </section>

                    <section class="py-12">
                        <div class="max-w-4xl mx-auto px-6">
                            <div class="bg-white rounded-lg shadow-md overflow-hidden divide-y divide-gray-200">

                                <div v-for="announcement in announcements.filter(a => a.type === 'public')" :key="announcement.id" class="p-6">
                                    <div class="flex flex-col sm:flex-row items-center gap-6">

                                        <img :src="announcement.image_url" :alt="announcement.title" class="w-full sm:w-48 h-32 object-cover rounded-md flex-shrink-0">

                                        <div class="flex-grow">
                                            <h3 class="text-lg font-semibold text-gray-800">    
                                                {{ announcement.title }}
                                            </h3>

                                            <a :href="route('announcements.detail', announcement.id)" class="inline-block mt-2 text-blue-600 hover:underline">
                                                Lihat Selengkapnya
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="announcements.length === 0" class="p-6 text-center text-gray-500">
                                    Belum ada pengumuman saat ini.
                                </div>

                            </div>
                        </div>
                    </section>
                </main>

                <footer class="py-16 text-center text-sm text-black dark:text-white/70">
                    © Institut Teknologi Kalimantan 2025. All Rights Reserved.
                </footer>
            </div>
        </div>
    </div>
</template>
