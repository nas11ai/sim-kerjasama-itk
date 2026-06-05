<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head } from '@inertiajs/vue3'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select'
import {
    Calendar,
    Clock,
    Building2,
    GraduationCap,
    TrendingUp,
    FileText,
    AlertCircle,
    PieChartIcon,
    BarChart3,
    Filter,
} from 'lucide-vue-next'
import { computed, ref } from 'vue'
import DonutChart from '@/Components/ui/chart-donut/DonutChart.vue'
import BarChart from '@/Components/ui/chart-bar/BarChart.vue'
import Popover from '@/Components/ui/popover/UiPopover.vue'
import PopoverTrigger from '@/Components/ui/popover/PopoverTrigger.vue'
import PopoverContent from '@/Components/ui/popover/PopoverContent.vue'
import Checkbox from '@/Components/ui/checkbox/UiCheckbox.vue'
import Label from '@/Components/ui/label/UiLabel.vue'

interface RecentSubmission {
    id: number
    name: string
    total_forms: number
    total_submissions: number
}

interface TotalSubmission {
    id: number
    name: string
    total_forms: number
    total_submissions: number
}

interface TotalByStatus {
    status: string
    total: number
}

interface TotalByFaculty {
    name: string
    total: number
}

interface TotalByProdi {
    name: string
    total: number
}

interface Faculty {
    id: number
    name: string
}

interface StudyProgram {
    id: number
    name: string
    faculty_id: number
}

const props = defineProps<{
    recentSubmissions: RecentSubmission[]
    totalSubmissions: TotalSubmission[]
    totalByStatus: TotalByStatus[]
    totalByFaculty: TotalByFaculty[]
    totalByProdi: TotalByProdi[]
    faculties: Faculty[]
    studyPrograms: StudyProgram[]
}>()

const statusChartType = ref<'bar' | 'donut'>('donut')
const facultyChartType = ref<'bar' | 'donut'>('donut')
const prodiChartType = ref<'bar' | 'donut'>('donut')
const selectedFaculties = ref<number[]>([])
const selectedStudyPrograms = ref<number[]>([])

const faculties = computed(() => props.faculties)
const studyPrograms = computed(() => props.studyPrograms)

// Year filter
const selectedYear = ref<string>('all')
const availableYears = computed(() => {
    const currentYear = new Date().getFullYear()
    const years = ['all']
    for (let i = 0; i < 5; i++) {
        years.push((currentYear - i).toString())
    }
    return years
})

// Recent Submissions (24h)
const recentSubmissionsData = computed(() => {
    return props.recentSubmissions || []
})

const recentSubmissionsCount = computed(() => {
    return recentSubmissionsData.value.reduce((sum, item) => sum + (item.total_submissions || 0), 0)
})

// Total Submissions
const totalSubmissionsData = computed(() => {
    return props.totalSubmissions || []
})

const totalSubmissionsCount = computed(() => {
    return totalSubmissionsData.value.reduce((sum, item) => sum + (item.total_submissions || 0), 0)
})

const totalFormsCount = computed(() => {
    return totalSubmissionsData.value.reduce((sum, item) => sum + (item.total_forms || 0), 0)
})

// Status Chart Data (Donut)
const statusData = computed(() => {
    const data = props.totalByStatus || []
    return data
        .map((item) => ({
            name: item.status.charAt(0).toUpperCase() + item.status.slice(1).replace('_', ' '),
            total: item.total || 0,
        }))
        .filter((item) => item.total > 0)
})

const totalByStatusCount = computed(() => {
    return statusData.value.reduce((sum, item) => sum + item.total, 0)
})

const toggleFaculties = (facultyId: number, checked?: boolean | 'indeterminate') => {
    const isSelected = selectedFaculties.value.includes(facultyId)
    const normalized = checked === 'indeterminate' ? true : (checked ?? !isSelected)

    if (normalized) {
        if (!isSelected) selectedFaculties.value.push(facultyId)
    } else {
        selectedFaculties.value = selectedFaculties.value.filter((id) => id !== facultyId)
    }
}

const toggleStudyPrograms = (studyProgramId: number, checked?: boolean | 'indeterminate') => {
    const isSelected = selectedStudyPrograms.value.includes(studyProgramId)
    const normalized = checked === 'indeterminate' ? true : (checked ?? !isSelected)

    if (normalized) {
        if (!isSelected) selectedStudyPrograms.value.push(studyProgramId)
    } else {
        selectedStudyPrograms.value = selectedStudyPrograms.value.filter(
            (id) => id !== studyProgramId
        )
    }
}

const selectAllFaculties = () => {
    if (selectedFaculties.value.length === faculties.value.length) {
        selectedFaculties.value = []
    } else {
        selectedFaculties.value = faculties.value.map((sp) => sp.id)
    }
}

const selectAllStudyPrograms = () => {
    if (selectedStudyPrograms.value.length === studyPrograms.value.length) {
        selectedStudyPrograms.value = []
    } else {
        selectedStudyPrograms.value = studyPrograms.value.map((sp) => sp.id)
    }
}

// Faculty Chart Data (Bar)
const facultyData = computed(() => {
    const data = props.totalByFaculty || []

    // Jika tidak ada filter yang dipilih, tampilkan semua
    if (selectedFaculties.value.length === 0) {
        return data.map((item) => ({
            name: item.name,
            total: item.total || 0,
        }))
    }

    // Filter berdasarkan fakultas yang dipilih
    return data
        .filter((item) => {
            const faculty = props.faculties.find((f) => f.name === item.name)
            return faculty && selectedFaculties.value.includes(faculty.id)
        })
        .map((item) => ({
            name: item.name,
            total: item.total || 0,
        }))
})

// Program Study Chart Data (Bar)
const prodiData = computed(() => {
    const data = props.totalByProdi || []

    // Jika tidak ada filter yang dipilih, tampilkan semua
    if (selectedStudyPrograms.value.length === 0) {
        return data.map((item) => ({
            name: item.name,
            total: item.total || 0,
        }))
    }

    // Filter berdasarkan prodi yang dipilih
    return data
        .filter((item) => {
            const studyPrograms = props.studyPrograms.find((f) => f.name === item.name)
            return studyPrograms && selectedStudyPrograms.value.includes(studyPrograms.id)
        })
        .map((item) => ({
            name: item.name,
            total: item.total || 0,
        }))
})

// Get status color
const getStatusColor = (status: string) => {
    const statusLower = status.toLowerCase()
    if (statusLower.includes('pending')) return 'bg-yellow-500'
    if (statusLower.includes('review')) return 'bg-blue-500'
    if (statusLower.includes('approved')) return 'bg-green-500'
    if (statusLower.includes('rejected')) return 'bg-red-500'
    if (statusLower.includes('revision')) return 'bg-purple-500'
    return 'bg-gray-500'
}

// Chart colors
</script>

<template>
    <Head title="Statistik Pengajuan Formulir" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Statistik Pengajuan Formulir
                    </h2>
                </div>
                <div class="flex items-center gap-3">
                    <Select v-model="selectedYear">
                        <SelectTrigger class="w-[180px]">
                            <SelectValue placeholder="Filter by Year" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all"> semua Tahun </SelectItem>
                            <SelectItem
                                v-for="year in availableYears.slice(1)"
                                :key="year"
                                :value="year"
                            >
                                {{ year }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <Card class="border-l-4 border-l-blue-500 hover:shadow-lg transition-shadow">
                    <CardContent class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase">
                                    Terbaru (24 jam)
                                </p>
                                <p class="text-2xl font-bold text-gray-900 mt-1">
                                    {{ recentSubmissionsCount }}
                                </p>
                                <p class="text-xs text-gray-500 mt-1">Pengajuan baru</p>
                            </div>
                            <div
                                class="h-10 w-10 bg-blue-100 rounded-lg flex items-center justify-center"
                            >
                                <Clock class="h-5 w-5 text-blue-600" />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-l-4 border-l-green-500 hover:shadow-lg transition-shadow">
                    <CardContent class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase">
                                    Total Pengajuan
                                </p>
                                <p class="text-2xl font-bold text-gray-900 mt-1">
                                    {{ totalSubmissionsCount }}
                                </p>
                                <p class="text-xs text-gray-500 mt-1">Sepanjang waktu</p>
                            </div>
                            <div
                                class="h-10 w-10 bg-green-100 rounded-lg flex items-center justify-center"
                            >
                                <FileText class="h-5 w-5 text-green-600" />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-l-4 border-l-purple-500 hover:shadow-lg transition-shadow">
                    <CardContent class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase">
                                    Total Formulir
                                </p>
                                <p class="text-2xl font-bold text-gray-900 mt-1">
                                    {{ totalFormsCount }}
                                </p>
                                <p class="text-xs text-gray-500 mt-1">Formulir aktif</p>
                            </div>
                            <div
                                class="h-10 w-10 bg-purple-100 rounded-lg flex items-center justify-center"
                            >
                                <Calendar class="h-5 w-5 text-purple-600" />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-l-4 border-l-orange-500 hover:shadow-lg transition-shadow">
                    <CardContent class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase">
                                    Jenis Status
                                </p>
                                <p class="text-2xl font-bold text-gray-900 mt-1">
                                    {{ statusData.length }}
                                </p>
                                <p class="text-xs text-gray-500 mt-1">Status aktif</p>
                            </div>
                            <div
                                class="h-10 w-10 bg-orange-100 rounded-lg flex items-center justify-center"
                            >
                                <TrendingUp class="h-5 w-5 text-orange-600" />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Recent Submissions Section -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div
                                class="h-10 w-10 bg-blue-100 rounded-lg flex items-center justify-center"
                            >
                                <Clock class="h-5 w-5 text-blue-600" />
                            </div>
                            <div>
                                <CardTitle>Pengajuan Terbaru (24 Jam Terakhir)</CardTitle>
                                <CardDescription
                                    >Aktivitas pengajuan formulir terbaru</CardDescription
                                >
                            </div>
                        </div>
                        <Badge variant="secondary" class="text-sm">
                            {{ recentSubmissionsCount }} total
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="recentSubmissionsData.length === 0" class="text-center py-12">
                        <Clock class="h-16 w-16 mx-auto text-gray-300 mb-4" />
                        <h3 class="text-lg font-semibold text-gray-500 mb-2">
                            Tidak Ada Pengajuan Terbaru
                        </h3>
                        <p class="text-sm text-gray-400">
                            Tidak ada pengajuan dalam 24 jam terakhir
                        </p>
                    </div>
                    <div v-else class="space-y-3">
                        <div
                            v-for="submission in recentSubmissionsData"
                            :key="submission.id"
                            class="flex items-center justify-between py-2 px-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
                        >
                            <div class="flex items-center gap-4">
                                <div
                                    class="h-10 w-10 bg-blue-100 rounded-lg flex items-center justify-center"
                                >
                                    <FileText class="h-5 w-5 text-blue-600" />
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">
                                        {{ submission.name }}
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        {{ submission.total_forms }} formulir tersedia
                                    </p>
                                </div>
                            </div>
                            <div class="text-right">
                                <Badge variant="secondary" class="text-base">
                                    {{ submission.total_submissions }}
                                </Badge>
                                <p class="text-xs text-gray-500 mt-1">pengajuan</p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Main Charts Section -->
            <Tabs default-value="status" class="w-full">
                <TabsList class="grid w-full grid-cols-3">
                    <TabsTrigger value="status"> berdasarkan Status </TabsTrigger>
                    <TabsTrigger value="faculty"> berdasarkan Fakultas </TabsTrigger>
                    <TabsTrigger value="prodi"> berdasarkan Program Studi </TabsTrigger>
                </TabsList>

                <!-- Status Tab -->
                <TabsContent value="status" class="space-y-4">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <Card>
                            <CardHeader>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="h-10 w-10 bg-purple-100 rounded-lg flex items-center justify-center"
                                        >
                                            <TrendingUp class="h-5 w-5 text-purple-600" />
                                        </div>
                                        <div>
                                            <CardTitle>Distribusi Status</CardTitle>
                                            <CardDescription
                                                >Distribusi status pengajuan</CardDescription
                                            >
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            :class="{
                                                'bg-gray-100': statusChartType === 'donut',
                                            }"
                                            @click="statusChartType = 'donut'"
                                        >
                                            <PieChartIcon class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            :class="{
                                                'bg-gray-100': statusChartType === 'bar',
                                            }"
                                            @click="statusChartType = 'bar'"
                                        >
                                            <BarChart3 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div v-if="statusData.length === 0" class="text-center py-12">
                                    <AlertCircle class="h-16 w-16 mx-auto text-gray-300 mb-4" />
                                    <p class="text-gray-500">Tidak ada data status tersedia</p>
                                </div>
                                <div v-else>
                                    <div class="flex justify-center items-center h-full">
                                        <DonutChart
                                            v-if="statusChartType === 'donut'"
                                            :data="statusData"
                                            index="name"
                                            category="total"
                                            :value-formatter="
                                                (valueFormatter) =>
                                                    `Total ${valueFormatter} Submissions`
                                            "
                                            class="h-[256px]"
                                        />
                                        <BarChart
                                            v-if="statusChartType === 'bar'"
                                            :data="statusData"
                                            index="name"
                                            :categories="['total']"
                                            :y-formatter="
                                                (valueFormatter) => `${valueFormatter} Submissions`
                                            "
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <div class="flex items-center gap-3">
                                    <div
                                        class="h-10 w-10 bg-indigo-100 rounded-lg flex items-center justify-center"
                                    >
                                        <FileText class="h-5 w-5 text-indigo-600" />
                                    </div>
                                    <div>
                                        <CardTitle>Rincian Status</CardTitle>
                                        <CardDescription
                                            >Distribusi berdasarkan jenis status</CardDescription
                                        >
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div v-if="statusData.length === 0" class="text-center py-12">
                                    <AlertCircle class="h-16 w-16 mx-auto text-gray-300 mb-4" />
                                    <p class="text-gray-500">Tidak ada data status tersedia</p>
                                </div>
                                <div v-else class="space-y-3">
                                    <div
                                        v-for="status in statusData"
                                        :key="status.name"
                                        class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
                                    >
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="h-3 w-3 rounded-full"
                                                :class="getStatusColor(status.name)"
                                            />
                                            <span class="font-medium text-gray-900">{{
                                                status.name
                                            }}</span>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <Badge variant="secondary" class="text-base">
                                                {{ status.total }}
                                            </Badge>
                                            <span class="text-sm text-gray-500">
                                                ({{
                                                    Math.round(
                                                        (status.total / totalByStatusCount) * 100
                                                    )
                                                }}%)
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>

                <!-- Faculty Tab -->
                <TabsContent value="faculty" class="space-y-4">
                    <Card>
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <div class="flex flex-row gap-3 items-center">
                                    <div
                                        class="h-10 w-10 bg-indigo-100 rounded-lg flex items-center justify-center"
                                    >
                                        <Building2 class="h-5 w-5 text-indigo-600" />
                                    </div>
                                    <div>
                                        <CardTitle>Distribusi Fakultas</CardTitle>
                                        <CardDescription>
                                            Pengajuan berdasarkan fakultas
                                            <span class="text-indigo-600 font-medium ml-1">
                                                ({{ selectedFaculties.length }}/{{
                                                    faculties.length
                                                }}
                                                dipilih)
                                            </span>
                                        </CardDescription>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Popover>
                                        <PopoverTrigger as-child>
                                            <Button variant="outline" size="sm">
                                                <Filter class="h-4 w-4 mr-2" />
                                                Filter
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent class="w-80" align="end">
                                            <div class="space-y-4">
                                                <div class="flex items-center justify-between">
                                                    <h4 class="font-semibold text-sm">
                                                        Pilih Fakultas
                                                    </h4>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        class="h-auto py-1 px-2 text-xs"
                                                        @click="selectAllFaculties"
                                                    >
                                                        {{
                                                            selectedFaculties.length ===
                                                            faculties.length
                                                                ? 'Batal Pilih Semua'
                                                                : 'Pilih Semua'
                                                        }}
                                                    </Button>
                                                </div>
                                                <div
                                                    class="max-h-[300px] overflow-y-auto space-y-2"
                                                >
                                                    <div
                                                        v-for="faculty in faculties"
                                                        :key="faculty.id"
                                                        class="flex items-center space-x-2"
                                                    >
                                                        <Checkbox
                                                            :id="'faculty-' + faculty.name"
                                                            :model-value="
                                                                selectedFaculties.includes(
                                                                    faculty.id
                                                                )
                                                            "
                                                            @update:model-value="
                                                                (val) =>
                                                                    toggleFaculties(faculty.id, val)
                                                            "
                                                        />
                                                        <Label
                                                            :for="'faculty-' + faculty.name"
                                                            class="text-sm font-normal cursor-pointer"
                                                        >
                                                            {{ faculty.name }}
                                                        </Label>
                                                    </div>
                                                </div>
                                            </div>
                                        </PopoverContent>
                                    </Popover>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :class="{ 'bg-gray-100': facultyChartType === 'donut' }"
                                        @click="facultyChartType = 'donut'"
                                    >
                                        <PieChartIcon class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :class="{ 'bg-gray-100': facultyChartType === 'bar' }"
                                        @click="facultyChartType = 'bar'"
                                    >
                                        <BarChart3 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div v-if="facultyData.length === 0" class="text-center py-12">
                                <Building2 class="h-16 w-16 mx-auto text-gray-300 mb-4" />
                                <p class="text-gray-500">Tidak ada data fakultas tersedia</p>
                            </div>
                            <div v-else>
                                <div class="flex justify-center items-center h-full">
                                    <DonutChart
                                        v-if="facultyChartType === 'donut'"
                                        index="name"
                                        category="total"
                                        :data="facultyData"
                                        :value-formatter="
                                            (valueFormatter) =>
                                                `Total ${valueFormatter} Submissions`
                                        "
                                        class="h-[256px]"
                                    />
                                    <BarChart
                                        v-if="facultyChartType === 'bar'"
                                        :data="facultyData"
                                        index="name"
                                        :categories="['total']"
                                        :y-formatter="
                                            (valueFormatter) => `${valueFormatter} Submissions`
                                        "
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Program Study Tab -->
                <TabsContent value="prodi" class="space-y-4">
                    <Card>
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <div class="flex flex-row gap-3 items-center">
                                    <div
                                        class="h-10 w-10 bg-teal-100 rounded-lg flex items-center justify-center"
                                    >
                                        <GraduationCap class="h-5 w-5 text-teal-600" />
                                    </div>
                                    <div>
                                        <CardTitle>Distribusi Program Studi</CardTitle>
                                        <CardDescription>
                                            Pengajuan berdasarkan program studi
                                            <span class="text-teal-600 font-medium ml-1">
                                                ({{ selectedStudyPrograms.length }}/{{
                                                    studyPrograms.length
                                                }}
                                                dipilih)
                                            </span>
                                        </CardDescription>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Popover>
                                        <PopoverTrigger as-child>
                                            <Button variant="outline" size="sm">
                                                <Filter class="h-4 w-4 mr-2" />
                                                Filter
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent class="w-80" align="end">
                                            <div class="space-y-4">
                                                <div class="flex items-center justify-between">
                                                    <h4 class="font-semibold text-sm">
                                                        Pilih Program Studi
                                                    </h4>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        class="h-auto py-1 px-2 text-xs"
                                                        @click="selectAllStudyPrograms"
                                                    >
                                                        {{
                                                            selectedStudyPrograms.length ===
                                                            studyPrograms.length
                                                                ? 'Batal Pilih Semua'
                                                                : 'Pilih Semua'
                                                        }}
                                                    </Button>
                                                </div>
                                                <div
                                                    class="max-h-[300px] overflow-y-auto space-y-2"
                                                >
                                                    <div
                                                        v-for="studyProgram in studyPrograms"
                                                        :key="studyProgram.id"
                                                        class="flex items-center space-x-2"
                                                    >
                                                        <Checkbox
                                                            :id="
                                                                'studyProgram-' + studyProgram.name
                                                            "
                                                            :model-value="
                                                                selectedStudyPrograms.includes(
                                                                    studyProgram.id
                                                                )
                                                            "
                                                            @update:model-value="
                                                                (val) =>
                                                                    toggleStudyPrograms(
                                                                        studyProgram.id,
                                                                        val
                                                                    )
                                                            "
                                                        />
                                                        <Label
                                                            :for="
                                                                'studyProgram-' + studyProgram.name
                                                            "
                                                            class="text-sm font-normal cursor-pointer"
                                                        >
                                                            {{ studyProgram.name }}
                                                        </Label>
                                                    </div>
                                                </div>
                                            </div>
                                        </PopoverContent>
                                    </Popover>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :class="{ 'bg-gray-100': prodiChartType === 'donut' }"
                                        @click="prodiChartType = 'donut'"
                                    >
                                        <PieChartIcon class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :class="{ 'bg-gray-100': prodiChartType === 'bar' }"
                                        @click="prodiChartType = 'bar'"
                                    >
                                        <BarChart3 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div v-if="prodiData.length === 0" class="text-center py-12">
                                <GraduationCap class="h-16 w-16 mx-auto text-gray-300 mb-4" />
                                <p class="text-gray-500">Tidak ada data program studi tersedia</p>
                            </div>
                            <div v-else>
                                <div class="flex justify-center items-center h-full">
                                    <DonutChart
                                        v-if="prodiChartType === 'donut'"
                                        :data="prodiData"
                                        index="name"
                                        category="total"
                                        :colors="['teal']"
                                        :value-formatter="
                                            (valueFormatter) =>
                                                `Total ${valueFormatter} Submissions`
                                        "
                                        class="h-[256px]"
                                    />
                                    <BarChart
                                        v-if="prodiChartType === 'bar'"
                                        :data="prodiData"
                                        index="name"
                                        :categories="['total']"
                                        :y-formatter="
                                            (valueFormatter) => `${valueFormatter} Submissions`
                                        "
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    </AuthenticatedLayout>
</template>
