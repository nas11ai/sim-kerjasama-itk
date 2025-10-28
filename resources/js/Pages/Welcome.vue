<!-- resources\js\Pages\Welcome.vue -->
<script setup lang="ts">
import { Head, Link, usePage } from "@inertiajs/vue3";
import { Button } from "@/Components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import { Separator } from "@/Components/ui/separator";
import {
    ArrowRight,
    FileText,
    Calendar,
    Paperclip,
    Mail,
    Phone,
    MapPin,
    ExternalLink,
} from "lucide-vue-next";
import { User } from "@/types";

const page = usePage();
const user = page.props.auth?.user as User | null;

const props = defineProps<{
    canLogin?: boolean;
    canRegister?: boolean;
    laravelVersion: string;
    phpVersion: string;
    announcements: Array<{
        id: number;
        title: string;
        content: string;
        type: string;
        created_at: string;
        announcement_files: Array<{
            id: number;
            file_name: string;
            file_path: string;
        }>;
    }>;
}>();

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString("id-ID", {
        day: "2-digit",
        month: "long",
        year: "numeric",
    });
};

const stripHtml = (html: string) => {
    return html ? html.replace(/<[^>]+>/g, "") : "Tidak ada deskripsi.";
};
</script>

<template>
    <Head title="Welcome to SIM Kerjasama ITK" />

    <div class="bg-gradient-to-b from-gray-50 to-white min-h-screen">
        <!-- Navbar -->
        <header class="sticky top-0 z-50 bg-white/80 backdrop-blur-md border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-16">
                    <div class="flex items-center gap-3">
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
                    </div>

                    <nav v-if="canLogin" class="flex items-center gap-2">
                        <Button v-if="$page.props.auth.user" as-child size="sm">
                            <Link
                                :href="
                                    user?.roles.some(
                                        (r) =>
                                            r.name === 'Super Admin' ||
                                            r.name === 'Admin'
                                    )
                                        ? route('admin.dashboard')
                                        : route('user.dashboard')
                                "
                            >
                                Dashboard
                                <ArrowRight class="ml-2 h-4 w-4" />
                            </Link>
                        </Button>

                        <template v-else>
                            <Button as-child variant="ghost" size="sm">
                                <Link :href="route('login')"> Masuk </Link>
                            </Button>

                            <Button v-if="canRegister" as-child size="sm">
                                <Link :href="route('register')"> Daftar </Link>
                            </Button>
                        </template>
                    </nav>
                </div>
            </div>
        </header>

        <!-- Hero -->
        <section
            class="relative overflow-hidden bg-gradient-to-br from-blue-600 via-blue-700 to-blue-800"
        >
            <div class="absolute inset-0 opacity-10">
                <div
                    class="absolute top-0 left-0 w-96 h-96 bg-white rounded-full blur-3xl"
                ></div>
                <div
                    class="absolute bottom-0 right-0 w-96 h-96 bg-white rounded-full blur-3xl"
                ></div>
            </div>

            <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
                <div class="text-center space-y-8">
                    <div class="space-y-4">
                        <h1
                            class="text-4xl md:text-6xl font-bold text-white leading-tight"
                        >
                            Sistem Informasi Kerjasama<br />
                            <span class="text-blue-200"
                                >Institut Teknologi Kalimantan</span
                            >
                        </h1>
                    </div>

                    <div
                        class="flex flex-col sm:flex-row items-center justify-center gap-4"
                    >
                        <Button
                            v-if="!$page.props.auth.user"
                            as-child
                            size="lg"
                            class="bg-white text-blue-600 hover:bg-blue-50 gap-2"
                        >
                            <Link :href="route('login')">
                                Masuk ke Sistem
                                <ArrowRight class="h-4 w-4" />
                            </Link>
                        </Button>

                        <Button
                            as-child
                            size="lg"
                            variant="outline"
                            class="bg-outline-white text-white hover:bg-white/10 gap-2"
                        >
                            <a href="#announcements">
                                Lihat Pengumuman
                                <ArrowRight class="h-4 w-4" />
                            </a>
                        </Button>
                    </div>
                </div>
            </div>
        </section>

        <!-- announcement -->
        <section id="announcements" class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <Badge class="mb-4">Informasi Terkini</Badge>
                    <h2
                        class="text-3xl md:text-4xl font-bold text-gray-900 mb-4"
                    >
                        Pengumuman Terbaru
                    </h2>
                    <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                        Informasi dan berita terkini seputar kerja sama ITK
                    </p>
                </div>

                <div
                    v-if="
                        announcements.filter((a) => a.type === 'public')
                            .length > 0
                    "
                    class="grid grid-cols-1 gap-6"
                >
                    <Card
                        v-for="announcement in announcements
                            .filter((a) => a.type === 'public')
                            .slice(0, 6)"
                        :key="announcement.id"
                        class="hover:shadow-lg transition-shadow overflow-hidden group"
                    >
                        <div
                            class="bg-gradient-to-r from-blue-600 to-blue-700 p-4"
                        >
                            <div class="flex items-center gap-2 text-white">
                                <Calendar class="h-4 w-4" />
                                <span class="text-sm font-medium">
                                    {{ formatDate(announcement.created_at) }}
                                </span>
                            </div>
                        </div>

                        <CardHeader>
                            <CardTitle
                                class="line-clamp-2 group-hover:text-blue-600 transition-colors"
                            >
                                {{ announcement.title }}
                            </CardTitle>
                        </CardHeader>

                        <CardContent class="space-y-4">
                            <p class="text-sm text-gray-600 line-clamp-3">
                                {{ stripHtml(announcement.content) }}
                            </p>

                            <div
                                v-if="
                                    announcement.announcement_files.length > 0
                                "
                                class="space-y-2"
                            >
                                <div
                                    class="flex items-center gap-1 text-xs font-medium text-gray-500"
                                >
                                    <Paperclip class="h-3 w-3" />
                                    {{
                                        announcement.announcement_files.length
                                    }}
                                    Lampiran
                                </div>
                            </div>

                            <Link
                                :href="
                                    route(
                                        'announcements.detail',
                                        announcement.id
                                    )
                                "
                                class="inline-flex items-center gap-1 text-sm font-medium text-blue-600 hover:text-blue-700 hover:gap-2 transition-all"
                            >
                                Lihat Selengkapnya
                                <ArrowRight class="h-4 w-4" />
                            </Link>
                        </CardContent>
                    </Card>
                </div>

                <!-- Empty State -->
                <Card v-else class="border-dashed">
                    <CardContent class="py-16 text-center">
                        <div class="flex justify-center mb-4">
                            <div class="p-4 bg-gray-100 rounded-full">
                                <FileText class="h-8 w-8 text-gray-400" />
                            </div>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">
                            Belum Ada Pengumuman
                        </h3>
                        <p class="text-sm text-gray-600">
                            Saat ini belum ada pengumuman yang tersedia.
                        </p>
                    </CardContent>
                </Card>

                <div
                    v-if="
                        announcements.filter((a) => a.type === 'public')
                            .length > 6
                    "
                    class="text-center mt-8"
                >
                    <Button as-child variant="outline" size="lg" class="gap-2">
                        <Link :href="route('announcements.index')">
                            Lihat Semua Pengumuman
                            <ExternalLink class="h-4 w-4" />
                        </Link>
                    </Button>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer
            class="bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 text-gray-300"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <div
                    class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8"
                >
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <img
                                src="/images/Logo-ITK.png"
                                alt="logo ITK"
                                class="h-8 w-auto object-contain brightness-0 invert"
                            />
                            <div>
                                <h3 class="font-bold text-white">
                                    SIM Kerjasama
                                </h3>
                                <p class="text-xs text-gray-400">
                                    Institut Teknologi Kalimantan
                                </p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="font-semibold text-white mb-4">
                            Link Cepat
                        </h4>
                        <ul class="space-y-2 text-sm">
                            <li>
                                <a
                                    href="#"
                                    class="hover:text-blue-400 transition-colors"
                                >
                                    Beranda
                                </a>
                            </li>
                            <li>
                                <a
                                    href="#announcements"
                                    class="hover:text-blue-400 transition-colors"
                                >
                                    Pengumuman
                                </a>
                            </li>
                            <li>
                                <Link
                                    v-if="canLogin"
                                    :href="route('login')"
                                    class="hover:text-blue-400 transition-colors"
                                >
                                    Masuk
                                </Link>
                            </li>
                            <li>
                                <Link
                                    v-if="canRegister"
                                    :href="route('register')"
                                    class="hover:text-blue-400 transition-colors"
                                >
                                    Daftar
                                </Link>
                            </li>
                        </ul>
                    </div>

                    <div>
                        <h4 class="font-semibold text-white mb-4">Kontak</h4>
                        <ul class="space-y-3 text-sm">
                            <li class="flex items-start gap-2">
                                <MapPin
                                    class="h-4 w-4 text-blue-400 flex-shrink-0 mt-0.5"
                                />
                                <span>
                                    Jl. Soekarno-Hatta Km. 15, Karang Joang,
                                    Balikpapan, Kalimantan Timur, 76127
                                </span>
                            </li>
                            <li class="flex items-center gap-2">
                                <Phone
                                    class="h-4 w-4 text-blue-400 flex-shrink-0"
                                />
                                <span>0542-8530801</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <Mail
                                    class="h-4 w-4 text-blue-400 flex-shrink-0"
                                />
                                <a
                                    href="mailto:humas@itk.ac.id"
                                    class="hover:text-blue-400 transition-colors"
                                >
                                    humas@itk.ac.id
                                </a>
                            </li>
                        </ul>
                    </div>

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
                <div
                    class="flex flex-col md:flex-row justify-between items-center gap-4 text-sm text-gray-400"
                >
                    <p>
                        © {{ new Date().getFullYear() }} Institut Teknologi
                        Kalimantan. All Rights Reserved.
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>
