<!-- resources\js\Pages\Welcome.vue -->
<script setup lang="ts">
import { Head, Link, usePage, router } from '@inertiajs/vue3'
import { ref, onMounted, onUnmounted } from 'vue'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Separator } from '@/Components/ui/separator'
import {
    ArrowRight,
    FileText,
    Calendar,
    Paperclip,
    Mail,
    Phone,
    MapPin,
    ExternalLink,
    ChevronLeft,
    ChevronRight,
    Pause,
    Play,
} from 'lucide-vue-next'
import { User } from '@/types'

const page = usePage()
const user = page.props.auth?.user as User | null

interface AnnouncementFile {
    id: number
    file_name: string
    file_path: string
}

interface Announcement {
    id: number
    title: string
    content: string
    type: string
    created_at: string
    announcement_files: AnnouncementFile[]
}

interface PaginatedAnnouncements {
    data: Announcement[]
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number | null
    to: number | null
    links?: any[]
}

const props = defineProps<{
    canLogin?: boolean
    canRegister?: boolean
    laravelVersion: string
    phpVersion: string
    announcements?: PaginatedAnnouncements
}>()

// Ensure announcements data is available with default values
const announcementsData = props.announcements?.data || []
const currentPage = props.announcements?.current_page || 1
const lastPage = props.announcements?.last_page || 1
const total = props.announcements?.total || 0
const from = props.announcements?.from || 0
const to = props.announcements?.to || 0

// Parallax state
const scrollY = ref(0)
const heroOpacity = ref(1)
const heroScale = ref(1)
const navbarBg = ref(0)
const showNavbar = ref(false)

// Carousel state
const currentSlide = ref(0)
const isAutoPlaying = ref(true)
let autoPlayInterval: ReturnType<typeof setInterval> | null = null

const handleScroll = () => {
    scrollY.value = window.scrollY

    // Hero parallax effects
    const heroHeight = window.innerHeight
    const scrollProgress = Math.min(scrollY.value / heroHeight, 1)

    // Fade and scale hero
    heroOpacity.value = 1 - scrollProgress * 0.8
    heroScale.value = 1 + scrollProgress * 0.1

    // Navbar visibility - show after hero section
    showNavbar.value = scrollY.value > heroHeight * 0.8

    // Navbar background
    navbarBg.value = Math.min(scrollY.value / 100, 1)
}

// Carousel functions
const nextSlide = () => {
    if (announcementsData.length > 0) {
        currentSlide.value = (currentSlide.value + 1) % announcementsData.length
    }
}

const prevSlide = () => {
    if (announcementsData.length > 0) {
        currentSlide.value =
            currentSlide.value === 0 ? announcementsData.length - 1 : currentSlide.value - 1
    }
}

const goToSlide = (index: number) => {
    currentSlide.value = index
    resetAutoPlay()
}

const toggleAutoPlay = () => {
    isAutoPlaying.value = !isAutoPlaying.value
    if (isAutoPlaying.value) {
        startAutoPlay()
    } else {
        stopAutoPlay()
    }
}

const startAutoPlay = () => {
    if (autoPlayInterval) clearInterval(autoPlayInterval)
    autoPlayInterval = setInterval(() => {
        nextSlide()
    }, 5000)
}

const stopAutoPlay = () => {
    if (autoPlayInterval) {
        clearInterval(autoPlayInterval)
        autoPlayInterval = null
    }
}

const resetAutoPlay = () => {
    if (isAutoPlaying.value) {
        stopAutoPlay()
        startAutoPlay()
    }
}

onMounted(() => {
    window.addEventListener('scroll', handleScroll, { passive: true })
    if (isAutoPlaying.value && announcementsData.length > 1) {
        startAutoPlay()
    }
})

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll)
    stopAutoPlay()
})

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    })
}

const stripHtml = (html: string) => {
    return html ? html.replace(/<[^>]+>/g, '') : 'Tidak ada deskripsi.'
}

const goToPage = (page: number) => {
    router.get(
        '/',
        { page },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['announcements'],
        }
    )
}
</script>

<template>

    <Head title="Welcome to SIM Kerjasama ITK" />

    <div class="bg-white min-h-screen">
        <!-- Navbar with Dynamic Background (Hidden in Hero) -->
        <header
class="fixed top-0 left-0 right-0 z-50 transition-all duration-500" :style="{
            backgroundColor: `rgba(255, 255, 255, ${navbarBg})`,
            backdropFilter: navbarBg > 0 ? 'blur(12px)' : 'none',
            borderBottom: navbarBg > 0 ? '1px solid rgba(0, 0, 0, 0.1)' : 'none',
            transform: showNavbar ? 'translateY(0)' : 'translateY(-100%)',
            opacity: showNavbar ? 1 : 0,
        }">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-16">
                    <div class="flex items-center gap-3">
                        <img src="/images/Logo-ITK.png" alt="Logo ITK" class="h-10 w-auto object-contain" />
                        <div>
                            <h1 class="text-lg font-bold text-gray-900">SIM Kerjasama ITK</h1>
                            <p class="text-xs text-gray-500">Institut Teknologi Kalimantan</p>
                        </div>
                    </div>

                    <nav v-if="canLogin" class="flex items-center gap-2">
                        <Button v-if="$page.props.auth.user" as-child size="sm">
                            <Link
:href="user?.roles.some(
                                (r) => r.name === 'Super Admin' || r.name === 'Admin'
                            )
                                    ? route('admin.dashboard')
                                    : route('user.dashboard')
                                ">
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

        <!-- Hero Section with Parallax -->
        <section class="relative h-screen overflow-hidden bg-black">
            <!-- Background with Parallax -->
            <div
class="absolute inset-0 bg-linear-to-br from-blue-600 via-blue-700 to-blue-900" :style="{
                transform: `translateY(${scrollY * 0.5}px) scale(${heroScale})`,
                opacity: heroOpacity,
            }">
                <div class="absolute inset-0 opacity-20">
                    <div class="absolute top-0 left-0 w-96 h-96 bg-white rounded-full blur-3xl" />
                    <div class="absolute bottom-0 right-0 w-96 h-96 bg-white rounded-full blur-3xl" />
                    <div
                        class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] bg-blue-400 rounded-full blur-[100px]" />
                </div>
            </div>

            <!-- Content -->
            <div
class="relative h-full flex items-center justify-center" :style="{
                transform: `translateY(${scrollY * 0.3}px)`,
                opacity: heroOpacity,
            }">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <div class="space-y-8">
                        <div class="space-y-6">
                            <h1
                                class="text-5xl md:text-7xl lg:text-8xl font-bold text-white leading-tight tracking-tight">
                                Sistem Informasi<br />
                                <span class="text-blue-200">Kerjasama ITK</span>
                            </h1>
                            <p class="text-xl md:text-2xl text-blue-100 max-w-3xl mx-auto">
                                Mengelola dan membangun kemitraan strategis untuk kemajuan Institut
                                Teknologi Kalimantan
                            </p>
                        </div>

                        <div class="flex flex-col sm:flex-row items-center justify-center gap-4 pt-8">
                            <Button
v-if="!$page.props.auth.user" as-child size="lg"
                                class="bg-white text-blue-600 hover:bg-blue-50 gap-2 px-8 py-6 text-lg rounded-full shadow-2xl hover:scale-105 transition-transform">
                                <Link :href="route('login')">
                                    Masuk ke Sistem
                                    <ArrowRight class="h-5 w-5" />
                                </Link>
                            </Button>

                            <Button
as-child size="lg" variant="outline"
                                class="bg-transparent border-2 border-white text-white hover:bg-white hover:text-blue-600 gap-2 px-8 py-6 text-lg rounded-full backdrop-blur-xs hover:scale-105 transition-all">
                                <a href="#announcements">
                                    Lihat Pengumuman
                                    <ArrowRight class="h-5 w-5" />
                                </a>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Scroll Indicator -->
            <div
class="absolute bottom-8 left-1/2 transform -translate-x-1/2 animate-bounce"
                :style="{ opacity: heroOpacity }">
                <div class="flex flex-col items-center gap-2 text-white">
                    <span class="text-sm font-medium">Scroll</span>
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                    </svg>
                </div>
            </div>
        </section>

        <!-- Announcements Section with Carousel -->
        <section
id="announcements"
            class="relative h-screen bg-linear-to-b from-gray-50 to-white overflow-hidden flex items-center">
            <div class="w-full h-full flex flex-col justify-center py-16">
                <!-- Header -->
                <div class="px-4 sm:px-6 lg:px-8 text-center mb-12">
                    <Badge class="mb-4 px-4 py-2 text-sm"> Informasi Terkini </Badge>
                    <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4 tracking-tight">
                        Pengumuman Terbaru
                    </h2>
                    <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                        Informasi dan berita terkini seputar kerja sama ITK
                    </p>
                </div>

                <!-- Carousel Container -->
                <div v-if="announcementsData.length > 0" class="flex-1 relative px-4 sm:px-6 lg:px-8">
                    <div class="max-w-5xl mx-auto h-full relative flex items-center">
                        <!-- Carousel Cards -->
                        <div class="w-full relative" style="min-height: 400px">
                            <TransitionGroup name="slide-fade">
                                <div
v-for="(announcement, index) in announcementsData" v-show="index === currentSlide"
                                    :key="announcement.id"
                                    class="absolute inset-0 flex items-center justify-center px-4 md:px-12">
                                    <Card
class="w-full max-w-4xl shadow-2xl border-0 overflow-hidden"
                                        @mouseenter="stopAutoPlay" @mouseleave="isAutoPlaying && startAutoPlay()">
                                        <!-- Card Header with Gradient -->
                                        <div class="bg-linear-to-r from-blue-600 to-blue-700 p-6">
                                            <div class="flex items-center gap-3 text-white">
                                                <Calendar class="h-6 w-6" />
                                                <span class="text-base font-semibold">
                                                    {{ formatDate(announcement.created_at) }}
                                                </span>
                                            </div>
                                        </div>

                                        <CardContent class="p-8 space-y-6">
                                            <!-- Title -->
                                            <h3 class="text-3xl md:text-4xl font-bold text-gray-900 leading-tight">
                                                {{ announcement.title }}
                                            </h3>

                                            <!-- Content -->
                                            <p class="text-lg text-gray-600 leading-relaxed line-clamp-4">
                                                {{ stripHtml(announcement.content) }}
                                            </p>

                                            <!-- Files Badge -->
                                            <div
v-if="announcement.announcement_files.length > 0"
                                                class="inline-flex items-center gap-2 text-sm font-medium text-gray-500 bg-gray-100 px-4 py-2 rounded-full">
                                                <Paperclip class="h-4 w-4" />
                                                {{ announcement.announcement_files.length }}
                                                Lampiran
                                            </div>

                                            <!-- CTA Button -->
                                            <div class="pt-4">
                                                <Button as-child size="lg" class="gap-2 group">
                                                    <Link
:href="route(
                                                        'announcements.detail',
                                                        announcement.id
                                                    )
                                                        ">
                                                        Lihat Selengkapnya
                                                        <ArrowRight
                                                            class="h-5 w-5 group-hover:translate-x-1 transition-transform" />
                                                    </Link>
                                                </Button>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </div>
                            </TransitionGroup>
                        </div>

                        <!-- Navigation Buttons -->
                        <button
v-if="announcementsData.length > 1"
                            class="absolute left-0 top-1/2 -translate-y-1/2 bg-white hover:bg-gray-50 text-gray-900 rounded-full p-4 shadow-xl hover:shadow-2xl transition-all hover:scale-110 z-20"
                            aria-label="Previous slide" @click="
                                prevSlide();
                            resetAutoPlay();
                            ">
                            <ChevronLeft class="h-6 w-6" />
                        </button>

                        <button
v-if="announcementsData.length > 1"
                            class="absolute right-0 top-1/2 -translate-y-1/2 bg-white hover:bg-gray-50 text-gray-900 rounded-full p-4 shadow-xl hover:shadow-2xl transition-all hover:scale-110 z-20"
                            aria-label="Next slide" @click="
                                nextSlide();
                            resetAutoPlay();
                            ">
                            <ChevronRight class="h-6 w-6" />
                        </button>
                    </div>

                    <!-- Bottom Controls -->
                    <div class="absolute bottom-8 left-0 right-0 px-4">
                        <div class="max-w-5xl mx-auto flex items-center justify-between">
                            <!-- Carousel Indicators -->
                            <div v-if="announcementsData.length > 1" class="flex justify-center gap-2 flex-1">
                                <button
v-for="(announcement, index) in announcementsData"
                                    :key="`indicator-${announcement.id}`" class="transition-all duration-300" :class="[
                                        index === currentSlide
                                            ? 'w-8 bg-blue-600'
                                            : 'w-2 bg-gray-300 hover:bg-gray-400',
                                    ]" :aria-label="`Go to slide ${index + 1}`" style="height: 8px; border-radius: 4px"
                                    @click="goToSlide(index);" />
                            </div>

                            <!-- Counter & Auto-play Toggle -->
                            <div v-if="announcementsData.length > 1" class="flex items-center gap-3">
                                <button
                                    class="bg-white/90 backdrop-blur-xs px-3 py-2 rounded-full shadow-lg hover:bg-white transition-colors"
                                    :aria-label="isAutoPlaying ? 'Pause autoplay' : 'Play autoplay'"
                                    @click="toggleAutoPlay();">
                                    <Pause v-if="isAutoPlaying" class="h-4 w-4 text-gray-700" />
                                    <Play v-else class="h-4 w-4 text-gray-700" />
                                </button>

                                <div class="bg-white/90 backdrop-blur-xs px-4 py-2 rounded-full shadow-lg">
                                    <span class="text-sm font-semibold text-gray-900">
                                        {{ currentSlide + 1 }} / {{ announcementsData.length }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="flex-1 flex items-center justify-center px-4">
                    <Card class="border-dashed border-2 max-w-2xl w-full">
                        <CardContent class="py-24 text-center">
                            <div class="flex justify-center mb-6">
                                <div class="p-6 bg-gray-100 rounded-full">
                                    <FileText class="h-12 w-12 text-gray-400" />
                                </div>
                            </div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-3">
                                Belum Ada Pengumuman
                            </h3>
                            <p class="text-base text-gray-600">
                                Saat ini belum ada pengumuman yang tersedia.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="bg-linear-to-br from-gray-900 via-gray-800 to-gray-900 text-gray-300">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <img
src="/images/Logo-ITK.png" alt="logo ITK"
                                class="h-8 w-auto object-contain brightness-0 invert" />
                            <div>
                                <h3 class="font-bold text-white">SIM Kerjasama</h3>
                                <p class="text-xs text-gray-400">Institut Teknologi Kalimantan</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="font-semibold text-white mb-4">Link Cepat</h4>
                        <ul class="space-y-2 text-sm">
                            <li>
                                <a href="#" class="hover:text-blue-400 transition-colors">
                                    Beranda
                                </a>
                            </li>
                            <li>
                                <a href="#announcements" class="hover:text-blue-400 transition-colors">
                                    Pengumuman
                                </a>
                            </li>
                            <li>
                                <Link
v-if="canLogin" :href="route('login')"
                                    class="hover:text-blue-400 transition-colors">
                                    Masuk
                                </Link>
                            </li>
                            <li>
                                <Link
v-if="canRegister" :href="route('register')"
                                    class="hover:text-blue-400 transition-colors">
                                    Daftar
                                </Link>
                            </li>
                        </ul>
                    </div>

                    <div>
                        <h4 class="font-semibold text-white mb-4">Kontak</h4>
                        <ul class="space-y-3 text-sm">
                            <li class="flex items-start gap-2">
                                <MapPin class="h-4 w-4 text-blue-400 shrink-0 mt-0.5" />
                                <span>
                                    Jl. Soekarno-Hatta Km. 15, Karang Joang, Balikpapan, Kalimantan
                                    Timur, 76127
                                </span>
                            </li>
                            <li class="flex items-center gap-2">
                                <Phone class="h-4 w-4 text-blue-400 shrink-0" />
                                <span>0542-8530801</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <Mail class="h-4 w-4 text-blue-400 shrink-0" />
                                <a href="mailto:humas@itk.ac.id" class="hover:text-blue-400 transition-colors">
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
href="https://itk.ac.id" target="_blank"
                                    class="hover:text-blue-400 transition-colors flex items-center gap-1">
                                    Website ITK
                                    <ExternalLink class="h-3 w-3" />
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <Separator class="my-8 bg-gray-700" />

                <div class="flex flex-col md:flex-row justify-between items-center gap-4 text-sm text-gray-400">
                    <p>
                        © {{ new Date().getFullYear() }} Institut Teknologi Kalimantan. All Rights
                        Reserved.
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
/* Smooth scroll behavior */
html {
    scroll-behavior: smooth;
}

/* Carousel transition animations */
.slide-fade-enter-active {
    transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.slide-fade-leave-active {
    transition: all 0.4s cubic-bezier(0.4, 0, 1, 1);
}

.slide-fade-enter-from {
    transform: translateX(100px);
    opacity: 0;
}

.slide-fade-leave-to {
    transform: translateX(-100px);
    opacity: 0;
}
</style>
