<!-- resources\js\Pages\AnnouncementDetail.vue -->
<script setup lang="ts">
import { Head, Link , router} from "@inertiajs/vue3";
import { Button } from "@/Components/ui/button";
import { Card, CardContent } from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import { Separator } from "@/Components/ui/separator";
import {
    ArrowLeft,
    Calendar,
    User,
    Paperclip,
    FileText,
    ExternalLink,
    Mail,
    Phone,
    MapPin,
} from "lucide-vue-next";
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

const props = defineProps<{
    announcement: AnnouncementDetail;
    canLogin?: boolean;
    canRegister?: boolean;
}>();

const { announcement } = props;

const goBack = () => {
    router.visit('/#announcements');
};

const formattedDate = computed(() =>
    new Intl.DateTimeFormat("id-ID", {
        day: "2-digit",
        month: "long",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
        hour12: false,
        timeZone: "Asia/Makassar",
    }).format(new Date(announcement.created_at))
);

const formatFileSize = (bytes: number) => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
};

const getFileIcon = (mimeType: string) => {
    if (mimeType.includes('pdf')) return 'text-red-600';
    if (mimeType.includes('word')) return 'text-blue-600';
    if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'text-green-600';
    if (mimeType.includes('image')) return 'text-purple-600';
    return 'text-gray-600';
};
</script>

<template>
    <Head :title="announcement.title" />

    <div class="bg-linear-to-b from-gray-50 to-white min-h-screen">
        <header class="sticky top-0 z-50 bg-white/80 backdrop-blur-md border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-16">
                    <Link href="/" class="flex items-center gap-3 hover:opacity-80 transition-opacity">
                        <img
                            src="/images/Logo-ITK.png"
                            alt="Logo ITK"
                            class="h-10 w-auto object-contain"
                        />
                        <div>
                            <h1 class="text-lg font-bold text-gray-900">
                                SIM Kerjasama ITK
                            </h1>
                            <p class="text-xs text-gray-500">
                                Institut Teknologi Kalimantan
                            </p>
                        </div>
                    </Link>

                    <!-- Nav -->
                    <nav v-if="canLogin" class="flex items-center gap-2">
                        <Button
                            v-if="$page.props.auth.user"
                            as-child
                            size="sm"
                        >
                            <Link :href="route('user.dashboard')">
                                Dashboard
                                <ArrowLeft class="ml-2 h-4 w-4 rotate-180" />
                            </Link>
                        </Button>

                        <template v-else>
                            <Button as-child variant="ghost" size="sm">
                                <Link :href="route('login')">
                                    Masuk
                                </Link>
                            </Button>

                            <Button
                                v-if="canRegister"
                                as-child
                                size="sm"
                            >
                                <Link :href="route('register')">
                                    Daftar
                                </Link>
                            </Button>
                        </template>
                    </nav>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="space-y-6">

                <Button
                    variant="ghost"
                    size="sm"
                    @click="goBack"
                    class="gap-2 hover:bg-gray-100"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Kembali
                </Button>

                <Card class="border-0 shadow-lg overflow-hidden">

                    <div class="relative overflow-hidden bg-linear-to-br from-blue-600 via-blue-700 to-blue-800 p-8 md:p-12">
                        <div class="absolute inset-0 opacity-10">
                            <div class="absolute top-0 left-0 w-64 h-64 bg-white rounded-full blur-3xl"></div>
                            <div class="absolute bottom-0 right-0 w-64 h-64 bg-white rounded-full blur-3xl"></div>
                        </div>

                        <div class="relative space-y-6">
                            <!-- Badge -->
                            <Badge
                                variant="secondary"
                                class="bg-white/20 text-white border-white/30 px-4 py-1.5"
                            >
                                Pengumuman
                            </Badge>

                            <h1 class="text-3xl md:text-4xl font-bold text-white leading-tight">
                                {{ announcement.title }}
                            </h1>

                            <div class="flex flex-wrap items-center gap-4 text-blue-100">
                                <div class="flex items-center gap-2">
                                    <User class="h-4 w-4" />
                                    <span class="text-sm">{{ announcement.announcement_creator?.name }}</span>
                                </div>
                                <span class="text-blue-300">•</span>
                                <div class="flex items-center gap-2">
                                    <Calendar class="h-4 w-4" />
                                    <time :datetime="announcement.created_at" class="text-sm">
                                        {{ formattedDate }} WITA
                                    </time>
                                </div>
                                <span class="text-blue-300" v-if="announcement.announcement_files?.length">•</span>
                                <div
                                    v-if="announcement.announcement_files?.length"
                                    class="flex items-center gap-2"
                                >
                                    <Paperclip class="h-4 w-4" />
                                    <span class="text-sm">
                                        {{ announcement.announcement_files.length }} Lampiran
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Content -->
                    <CardContent class="p-8 md:p-12">
                        <div
                            class="prose prose-lg max-w-none text-gray-700 leading-relaxed"
                            v-html="announcement.content"
                        ></div>
                    </CardContent>
                </Card>

                <!-- Attachments -->
                <Card
                    v-if="announcement.announcement_files?.length"
                    class="border-0 shadow-lg"
                >
                    <CardContent class="p-8">
                        <div class="space-y-6">
                            <div class="flex items-center gap-2">
                                <Paperclip class="h-5 w-5 text-blue-600" />
                                <h2 class="text-xl font-semibold text-gray-900">
                                    File Lampiran
                                </h2>
                                <Badge variant="secondary" class="ml-auto">
                                    {{ announcement.announcement_files.length }} File
                                </Badge>
                            </div>

                            <Separator />

                            <div class="grid gap-3">
                                <a
                                    v-for="file in announcement.announcement_files"
                                    :key="file.id"
                                    :href="file.file_path"
                                    target="_blank"
                                    download
                                    class="group flex items-center justify-between p-4 rounded-lg border hover:border-blue-300 hover:bg-blue-50/50 transition-all"
                                >
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <div class="p-2 bg-gray-100 rounded-lg group-hover:bg-blue-100 transition-colors shrink-0">
                                            <FileText :class="['h-5 w-5', getFileIcon(file.mime_type)]" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="font-medium text-gray-900 truncate group-hover:text-blue-600 transition-colors">
                                                {{ file.file_name }}
                                            </p>
                                            <div class="flex items-center gap-2 mt-1">
                                                <span class="text-xs text-gray-500">
                                                    {{ file.mime_type }}
                                                </span>
                                                <span class="text-xs text-gray-400">•</span>
                                                <span class="text-xs text-gray-500">
                                                    {{ formatFileSize(file.file_size) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-linear-to-br from-gray-900 via-gray-800 to-gray-900 text-gray-300 mt-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                    <!-- About -->
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <img
                                src="/images/Logo-ITK.png"
                                alt="Logo ITK"
                                class="h-8 w-auto object-contain brightness-0 invert"
                            />
                            <div>
                                <h3 class="font-bold text-white">SIM Kerjasama</h3>
                                <p class="text-xs text-gray-400">ITK</p>
                            </div>
                        </div>
                        <p class="text-sm text-gray-400">
                            Platform resmi Institut Teknologi Kalimantan untuk mengelola
                            kerja sama institusional.
                        </p>
                    </div>

                    <div>
                        <h4 class="font-semibold text-white mb-4">Link Cepat</h4>
                        <ul class="space-y-2 text-sm">
                            <li>
                                <Link href="/" class="hover:text-blue-400 transition-colors">
                                    Beranda
                                </Link>
                            </li>
                            <li>
                                <Link href="/#announcements" class="hover:text-blue-400 transition-colors">
                                    Pengumuman
                                </Link>
                            </li>
                            <li>
                                <Link
                                    v-if="canLogin"
                                    :href="route('login')"
                                    class="hover:text-blue-400 transition-colors"
                                >
                                    Login
                                </Link>
                            </li>
                            <li>
                                <Link
                                    v-if="canRegister"
                                    :href="route('register')"
                                    class="hover:text-blue-400 transition-colors"
                                >
                                    Register
                                </Link>
                            </li>
                        </ul>
                    </div>

                    <!-- Contact -->
                    <div>
                        <h4 class="font-semibold text-white mb-4">Kontak</h4>
                        <ul class="space-y-3 text-sm">
                            <li class="flex items-start gap-2">
                                <MapPin class="h-4 w-4 text-blue-400 shrink-0 mt-0.5" />
                                <span>
                                    Jl. Soekarno-Hatta Km. 15, Karang Joang,
                                    Balikpapan, Kalimantan Timur, 76127
                                </span>
                            </li>
                            <li class="flex items-center gap-2">
                                <Phone class="h-4 w-4 text-blue-400 shrink-0" />
                                <span>0542-8530801</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <Mail class="h-4 w-4 text-blue-400 shrink-0" />
                                <a
                                    href="mailto:humas@itk.ac.id"
                                    class="hover:text-blue-400 transition-colors"
                                >
                                    humas@itk.ac.id
                                </a>
                            </li>
                        </ul>
                    </div>

                    <!-- Info -->
                    <div>
                        <h4 class="font-semibold text-white mb-4">Informasi</h4>
                        <ul class="space-y-2 text-sm">
                            <li>
                                <a
                                    href="https://itk.ac.id"
                                    target="_blank"
                                    class="hover:text-blue-400 transition-colors flex items-center gap-1"
                                >
                                    Website ITK
                                    <ExternalLink class="h-3 w-3" />
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <Separator class="my-8 bg-gray-700" />

                <!-- Bottom Footer -->
                <div class="flex flex-col md:flex-row justify-between items-center gap-4 text-sm text-gray-400">
                    <p>
                        © {{ new Date().getFullYear() }} Institut Teknologi Kalimantan. All Rights Reserved.
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>
