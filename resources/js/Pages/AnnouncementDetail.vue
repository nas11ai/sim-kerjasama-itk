<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { ArrowLeft } from "lucide-vue-next";
import { computed } from "vue";

interface AnnouncementFile {
    id: number;
    file_name: string;
    file_path: string;
    mime_type: string;
    file_size: number;
}

interface AnnouncementDetail {
    id: number;
    title: string;
    content: string;
    type: string;
    expired_at: string | null;
    created_at: string;
    announcement_files: AnnouncementFile[];
    announcement_creator: {
        name: string;
    };
}

const props = defineProps<{ announcement: AnnouncementDetail }>();
const { announcement } = props;

const goBack = () => window.history.back();

const formattedDate = computed(() =>
    new Intl.DateTimeFormat("id-ID", {
        day: "2-digit",
        month: "long",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
        hour12: false,
        timeZone: "Asia/Makassar", // biar jadi WITA
    }).format(new Date(announcement.created_at))
);
</script>

<template>
    <Head :title="announcement.title" />

    <div class="bg-gray-50 text-black/50 dark:bg-black dark:text-white/50">
        <div
            class="relative flex min-h-screen flex-col items-center selection:bg-[#FF2D20] selection:text-white"
        >
            <div class="relative w-full">
                <!-- Header -->
                <header
                    class="flex justify-center items-center gap-2 px-6 py-10 max-w-7xl mx-auto"
                >
                    <div class="flex lg:col-start-2 lg:justify-center">
                        <h1
                            class="text-xl font-semibold text-gray-800 dark:text-white"
                        >
                            SIM KERJASAMA ITK
                        </h1>
                    </div>
                </header>

                <!-- Main -->
                <main>
                    <!-- Hero Section -->
                    <section
                        class="bg-gradient-to-r from-blue-600 to-blue-400 py-10 text-white"
                    >
                        <div class="max-w-7xl mx-auto px-6">
                            <p class="text-4xl font-bold">Pengumuman</p>
                        </div>
                    </section>

                    <!-- Content Section -->
                    <section>
                        <div
                            class="max-w-4xl mx-auto px-8 sm:px-4 py-8 space-y-8"
                        >
                            <!-- Back Button -->
                            <button
                                @click="goBack"
                                class="flex items-center gap-2 text-gray-700 hover:text-gray-500 transition"
                            >
                                <ArrowLeft class="w-5 h-5" />
                                <span>Kembali</span>
                            </button>

                            <!-- Title & Meta -->
                            <div class="border-b pb-4 mb-4">
                                <h1
                                    class="font-bold text-black break-words text-3xl sm:text-3xl md:text-4xl lg:text-5xl"
                                >
                                    {{ announcement.title }}
                                </h1>
                                <div
                                    class="text-sm text-gray-400 flex flex-wrap gap-x-2 gap-y-1 mt-2"
                                >
                                    <span>
                                        Dipublish oleh
                                        {{
                                            announcement.announcement_creator
                                                ?.name
                                        }}
                                    </span>
                                    <span>•</span>
                                    <time :datetime="announcement.created_at">
                                        {{ formattedDate }} WITA
                                    </time>
                                </div>
                            </div>

                            <!-- Content -->
                            <div
                                class="prose max-w-none text-gray-700 leading-relaxed text-sm sm:text-sm md:text-lg pb-4 break-words"
                                v-html="announcement.content"
                            ></div>

                            <!-- Attachments -->
                            <div v-if="announcement.announcement_files?.length">
                                <h2 class="font-semibold mb-2 text-lg">
                                    Lampiran
                                </h2>
                                <ul class="flex flex-col divide-y">
                                    <li
                                        v-for="file in announcement.announcement_files"
                                        :key="file.id"
                                        class="flex flex-col sm:flex-row sm:items-center sm:justify-between py-2"
                                    >
                                        <a
                                            :href="file.file_path"
                                            target="_blank"
                                            class="text-blue-600 underline truncate"
                                        >
                                            {{ file.file_name }}
                                        </a>
                                        <span
                                            class="text-xs text-gray-500 mt-1 sm:mt-0"
                                        >
                                            {{ file.mime_type }} •
                                            {{
                                                (file.file_size / 1024).toFixed(
                                                    1
                                                )
                                            }}
                                            KB
                                        </span>
                                    </li>
                                </ul>
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
