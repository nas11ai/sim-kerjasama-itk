<script setup lang="ts">
import { Head, Link, usePage } from "@inertiajs/vue3";
import { onMounted } from "vue";
import { Paperclip } from "lucide-vue-next";

const page = usePage();
const user = page.props.auth.user as { roles: string[] };

const props = defineProps<{
    canLogin?: boolean;
    canRegister?: boolean;
    laravelVersion: string;
    phpVersion: string;
    announcements: Array<any>;
}>();

function handleImageError() {
    document.getElementById("screenshot-container")?.classList.add("!hidden");
    document.getElementById("docs-card")?.classList.add("!row-span-1");
    document.getElementById("docs-card-content")?.classList.add("!flex-row");
    document.getElementById("background")?.classList.add("!hidden");
}
</script>

<template>
    <Head title="Welcome" />
    
    <div class="bg-gray-50 text-black/50 dark:bg-black dark:text-white/50">
        <div
            class="relative flex min-h-screen flex-col items-center selection:bg-[#FF2D20] selection:text-white"
        >
            <div class="relative w-full">
                <header
                    class="grid grid-cols-2 items-center gap-2 px-6 py-10 lg:grid-cols-3 max-w-7xl mx-auto"
                >
                    <div class="flex lg:col-start-2 lg:justify-center">
                        <h1
                            class="text-xl font-semibold text-gray-800 dark:text-white"
                        >
                            SIM KERJASAMA ITK
                        </h1>
                    </div>
                    <nav v-if="canLogin" class="-mx-3 flex flex-1 justify-end">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="
                                user.roles.includes('Super Admin') ||
                                user.roles.includes('Admin')
                                    ? route('admin.dashboard')
                                    : route('user.dashboard')
                            "
                            class="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
                        >
                            Dashboard
                        </Link>

                        <template v-else>
                            <Link
                                :href="route('login')"
                                class="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
                            >
                                Log in
                            </Link>

                            <Link
                                v-if="canRegister"
                                :href="route('register')"
                                class="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
                            >
                                Register
                            </Link>
                        </template>
                    </nav>
                </header>

                <main>
                    <section
                        class="bg-gradient-to-r from-blue-600 to-blue-400 py-10 text-white"
                    >
                        <div class="max-w-7xl mx-auto px-6">
                            <p class="text-4xl font-bold">Pengumuman</p>
                        </div>
                    </section>

                    <section class="flex justify-center py-12">
                        <div
                            class="bg-white rounded-2xl border shadow-lg max-w-4xl w-full flex flex-col justify-center px-8 divide-y divide-gray-300"
                        >
                            <div
                                v-for="announcement in announcements.filter(
                                    (a) => a.type === 'public'
                                )"
                                :key="announcement.id"
                                class="py-8"
                            >
                                <div class="flex flex-col sm:flex-row gap-6">
                                    <!-- Konten -->
                                    <div class="flex-grow space-y-3">
                                        <!-- Header kecil -->
                                        <div
                                            class="flex items-center gap-2 text-xs text-gray-500"
                                        >
                                            <span
                                                class="px-2 py-1 text-[11px] rounded-full bg-blue-100 text-blue-700 font-medium"
                                            >
                                                {{
                                                    new Date(
                                                        announcement.created_at
                                                    ).toLocaleDateString(
                                                        "id-ID",
                                                        {
                                                            day: "2-digit",
                                                            month: "long",
                                                            year: "numeric",
                                                        }
                                                    )
                                                }}
                                            </span>
                                        </div>

                                        <!-- Judul -->
                                        <h3
                                            class="text-2xl font-semibold text-gray-800 line-clamp-3"
                                        >
                                            {{ announcement.title }}
                                        </h3>

                                        <!-- Deskripsi singkat -->
                                        <p
                                            class="text-sm text-gray-600 line-clamp-2"
                                        >
                                            {{
                                                announcement.content
                                                    ? announcement.content.replace(
                                                          /<[^>]+>/g,
                                                          ""
                                                      )
                                                    : "Tidak ada deskripsi."
                                            }}
                                        </p>

                                        <!-- Attachments -->
                                        <div
                                            v-if="
                                                announcement.announcement_files
                                                    .length
                                            "
                                            class="mt-4 space-y-1"
                                        >
                                            <div
                                                class="flex items-center text-sm text-gray-500 font-medium"
                                            >
                                                <Paperclip
                                                    class="h-4 w-4 mr-2"
                                                />
                                                Lampiran:
                                            </div>
                                            <div class="flex flex-wrap gap-2">
                                                <a
                                                    v-for="file in announcement.announcement_files"
                                                    :key="file.id"
                                                    :href="file.file_path"
                                                    target="_blank"
                                                    class="inline-flex items-center rounded-md border px-3 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50 transition-colors"
                                                >
                                                    {{ file.file_name }}
                                                </a>
                                            </div>
                                        </div>

                                        <!-- Link -->
                                        <a
                                            :href="
                                                route(
                                                    'announcements.detail',
                                                    announcement.id
                                                )
                                            "
                                            class="inline-flex items-center text-blue-600 font-medium text-sm hover:underline"
                                        >
                                            Lihat Selengkapnya →
                                        </a>
                                    </div>
                                </div>

                                <div
                                    v-if="announcements.length === 0"
                                    class="p-6 text-center text-gray-500"
                                >
                                    Belum ada pengumuman saat ini.
                                </div>
                            </div>
                        </div>
                    </section>
                </main>

                <footer
                    class="py-16 text-center text-sm text-black dark:text-white/70"
                >
                    © Institut Teknologi Kalimantan 2025. All Rights Reserved.
                </footer>
            </div>
        </div>
    </div>
</template>
