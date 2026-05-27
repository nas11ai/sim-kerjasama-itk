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
    FileText,
    Calendar,
    Building2,
    GraduationCap,
    TrendingUp,
    BarChart3,
    PieChart as PieChartIcon,
    Filter,
} from 'lucide-vue-next'
import { computed, ref } from 'vue'
import BarChart from '@/Components/ui/chart-bar/BarChart.vue'
import DonutChart from '@/Components/ui/chart-donut/DonutChart.vue'
import Popover from '@/Components/ui/popover/Popover.vue'
import PopoverTrigger from '@/Components/ui/popover/PopoverTrigger.vue'
import PopoverContent from '@/Components/ui/popover/PopoverContent.vue'
import Checkbox from '@/Components/ui/checkbox/Checkbox.vue'
import Label from '@/Components/ui/label/Label.vue'

interface FormPhaseFaculty {
    id: number
    title: string
    faculty_id: number
    faculty_name: string
    total_forms: number
    total_submissions: number
}

interface FormPhaseProdi {
    id: number
    title: string
    faculty_id: number
    faculty_name: string
    study_program_id: number
    study_program_name: string
    total_forms: number
    total_submissions: number
}

interface FormPhaseStatus {
    id: number
    title: string
    total_submissions: number
    pending: string
    under_review: string
    approved: string
    rejected: string
    revision: string
}

interface FormPhaseTotal {
    id: number
    title: string
    total_forms: number
    total_submissions: number
}

interface FormPhaseByPeriod {
    submission_period_id: number
    submission_period_name: string
    total_forms: number
    total_submissions: number
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
    formPhaseFaculty: FormPhaseFaculty[]
    formPhaseProdi: FormPhaseProdi[]
    formPhaseStatus: FormPhaseStatus[]
    formPhaseTotal: FormPhaseTotal[]
    formPhaseByPeriod: FormPhaseByPeriod[]
    faculties: Faculty[]
    studyPrograms: StudyProgram[]
}>()

// Filter and chart type states
const facultyChartType = ref<'bar' | 'donut'>('bar')
const prodiChartType = ref<'bar' | 'donut'>('bar')
const statusChartType = ref<'bar' | 'donut'>('donut')
const periodChartType = ref<'bar' | 'donut'>('donut')
const selectedFaculties = ref<number[]>([])
const selectedStudyPrograms = ref<number[]>([])

const faculties = computed(() => props.faculties)
const studyPrograms = computed(() => props.studyPrograms)

// By Period Chart Data
const periodData = computed(() => {
    return (
        props.formPhaseByPeriod?.map((period) => ({
            name: period.submission_period_name,
            forms: period.total_forms || 0,
            submissions: period.total_submissions || 0,
        })) || []
    )
})

// By Status Chart Data
const statusData = computed(() => {
    const statusLabels = {
        pending: 'Pending',
        under_review: 'Dalam Review',
        approved: 'Disetujui',
        rejected: 'Ditolak',
        revision: 'Revisi',
    }

    if (!props.formPhaseStatus?.length) return []

    return Object.keys(statusLabels)
        .map((key) => {
            const statusKey = key as keyof typeof statusLabels

            const total = props.formPhaseStatus.reduce((sum, item) => {
                const value = Number(item[statusKey]) || 0
                return sum + value
            }, 0)

            return {
                name: statusLabels[statusKey],
                total,
            }
        })
        .filter((item) => item.total > 0)
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

// By Faculty Chart Data
const facultyData = computed(() => {
    if (!props.formPhaseFaculty?.length) return []

    // Filter berdasarkan fakultas yang dipilih
    const filtered = selectedFaculties.value.length
        ? props.formPhaseFaculty.filter((item) => selectedFaculties.value.includes(item.faculty_id))
        : props.formPhaseFaculty

    const aggregated = filtered.reduce(
        (acc, item) => {
            const existing = acc.find((f) => f.name === item.faculty_name)
            if (existing) {
                existing.forms += item.total_forms || 0
                existing.submissions += item.total_submissions || 0
            } else {
                acc.push({
                    name: item.faculty_name,
                    forms: item.total_forms || 0,
                    submissions: item.total_submissions || 0,
                })
            }
            return acc
        },
        [] as { name: string; forms: number; submissions: number }[]
    )

    return aggregated.sort((a, b) => b.submissions - a.submissions)
})

// By Study Program Chart Data
const prodiData = computed(() => {
    if (!props.formPhaseProdi?.length) return []

    // Filter berdasarkan prodi yang dipilih
    const filtered = selectedStudyPrograms.value.length
        ? props.formPhaseProdi.filter((item) =>
              selectedStudyPrograms.value.includes(item.study_program_id)
          )
        : props.formPhaseProdi

    const aggregated = filtered.reduce(
        (acc, item) => {
            const existing = acc.find((p) => p.name === item.study_program_name)
            if (existing) {
                existing.forms += item.total_forms || 0
                existing.submissions += item.total_submissions || 0
            } else {
                acc.push({
                    name: item.study_program_name,
                    forms: item.total_forms || 0,
                    submissions: item.total_submissions || 0,
                })
            }
            return acc
        },
        [] as { name: string; forms: number; submissions: number }[]
    )

    return aggregated.sort((a, b) => b.submissions - a.submissions)
})

// Total statistics
const totalStats = computed(() => {
    const totals = props.formPhaseTotal || []
    const statuses = props.formPhaseStatus || []

    return {
        // Dari formPhaseTotal
        phases: totals.length,
        forms: totals.reduce((sum, item) => sum + (item.total_forms || 0), 0),
        submissions: totals.reduce((sum, item) => sum + (item.total_submissions || 0), 0),

        // Dari formPhaseStatus
        pending: statuses.reduce((sum, item) => sum + (Number(item.pending) || 0), 0),
        underReview: statuses.reduce((sum, item) => sum + (Number(item.under_review) || 0), 0),
        approved: statuses.reduce((sum, item) => sum + (Number(item.approved) || 0), 0),
        rejected: statuses.reduce((sum, item) => sum + (Number(item.rejected) || 0), 0),
        revision: statuses.reduce((sum, item) => sum + (Number(item.revision) || 0), 0),
    }
})
</script>

<template>
    <Head title="Statistik Tahap Formulir" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Statistik Tahap Formulir
                    </h2>
                    <p class="text-sm text-gray-600 mt-1">
                        Analisis komprehensif tentang tahap formulir dan pengajuan
                    </p>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <Card class="border-l-4 border-l-blue-500">
                    <CardContent class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase">
                                    Total Tahap
                                </p>
                                <p class="text-2xl font-bold text-gray-900 mt-1">
                                    {{ totalStats.phases }}
                                </p>
                            </div>
                            <div
                                class="h-10 w-10 bg-blue-100 rounded-lg flex items-center justify-center"
                            >
                                <FileText class="h-5 w-5 text-blue-600" />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-l-4 border-l-green-500">
                    <CardContent class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase">
                                    Total Formulir
                                </p>
                                <p class="text-2xl font-bold text-gray-900 mt-1">
                                    {{ totalStats.forms }}
                                </p>
                            </div>
                            <div
                                class="h-10 w-10 bg-green-100 rounded-lg flex items-center justify-center"
                            >
                                <BarChart3 class="h-5 w-5 text-green-600" />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-l-4 border-l-purple-500">
                    <CardContent class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase">
                                    Total Pengajuan
                                </p>
                                <p class="text-2xl font-bold text-gray-900 mt-1">
                                    {{ totalStats.submissions }}
                                </p>
                            </div>
                            <div
                                class="h-10 w-10 bg-purple-100 rounded-lg flex items-center justify-center"
                            >
                                <Calendar class="h-5 w-5 text-purple-600" />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-l-4 border-l-orange-500">
                    <CardContent class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase">Disetujui</p>
                                <p class="text-2xl font-bold text-gray-900 mt-1">
                                    {{ totalStats.approved }}
                                </p>
                            </div>
                            <div
                                class="h-10 w-10 bg-orange-100 rounded-lg flex items-center justify-center"
                            >
                                <TrendingUp class="h-5 w-5 text-orange-600" />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-l-4 border-l-yellow-500">
                    <CardContent class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase">Pending</p>
                                <p class="text-2xl font-bold text-gray-900 mt-1">
                                    {{ totalStats.pending }}
                                </p>
                            </div>
                            <div
                                class="h-10 w-10 bg-yellow-100 rounded-lg flex items-center justify-center"
                            >
                                <Calendar class="h-5 w-5 text-yellow-600" />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Main Charts Section -->
            <Tabs default-value="period" class="w-full">
                <TabsList class="grid w-full grid-cols-4">
                    <TabsTrigger value="period"> Berdasarkan Periode </TabsTrigger>
                    <TabsTrigger value="status"> Berdasarkan Status </TabsTrigger>
                    <TabsTrigger value="faculty"> Berdasarkan Fakultas </TabsTrigger>
                    <TabsTrigger value="prodi"> Berdasarkan Program Studi </TabsTrigger>
                </TabsList>

                <!-- Period Tab -->
                <TabsContent value="period" class="space-y-4">
                    <Card>
                        <CardHeader>
                            <div class="flex items-center justify-between flex-wrap gap-3">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="h-10 w-10 bg-blue-100 rounded-lg flex items-center justify-center"
                                    >
                                        <Calendar class="h-5 w-5 text-blue-600" />
                                    </div>
                                    <div>
                                        <CardTitle>Ringkasan Periode Pengajuan</CardTitle>
                                        <CardDescription
                                            >Formulir dan pengajuan berdasarkan periode
                                            pengajuan</CardDescription
                                        >
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :class="{ 'bg-gray-100': periodChartType === 'donut' }"
                                        @click="periodChartType = 'donut'"
                                    >
                                        <PieChartIcon class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :class="{ 'bg-gray-100': periodChartType === 'bar' }"
                                        @click="periodChartType = 'bar'"
                                    >
                                        <BarChart3 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div v-if="periodData.length === 0" class="text-center py-12">
                                <Calendar class="h-16 w-16 mx-auto text-gray-300 mb-4" />
                                <p class="text-gray-500">Tidak ada data periode tersedia</p>
                            </div>
                            <div v-else>
                                <DonutChart
                                    v-if="periodChartType === 'donut'"
                                    :data="periodData"
                                    index="name"
                                    category="forms"
                                    :value-formatter="(value: number) => value.toString()"
                                />
                                <BarChart
                                    v-else
                                    :data="periodData"
                                    index="name"
                                    :categories="['forms', 'submissions']"
                                    :colors="['#16a34a', '#9333ea']"
                                    type="grouped"
                                    :y-formatter="(valueFormatter) => `${valueFormatter}`"
                                />
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Status Tab -->
                <TabsContent value="status" class="space-y-4">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <Card>
                            <CardHeader>
                                <div class="flex items-center justify-between flex-wrap gap-3">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="h-10 w-10 bg-purple-100 rounded-lg flex items-center justify-center"
                                        >
                                            <PieChartIcon class="h-5 w-5 text-purple-600" />
                                        </div>
                                        <div>
                                            <CardTitle>Distribusi Status</CardTitle>
                                            <CardDescription
                                                >Rincian status pengajuan</CardDescription
                                            >
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            :class="{ 'bg-gray-100': statusChartType === 'donut' }"
                                            @click="statusChartType = 'donut'"
                                        >
                                            <PieChartIcon class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            :class="{ 'bg-gray-100': statusChartType === 'bar' }"
                                            @click="statusChartType = 'bar'"
                                        >
                                            <BarChart3 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div v-if="statusData.length === 0" class="text-center py-12">
                                    <PieChartIcon class="h-16 w-16 mx-auto text-gray-300 mb-4" />
                                    <p class="text-gray-500">Tidak ada data status tersedia</p>
                                </div>
                                <div v-else>
                                    <DonutChart
                                        v-if="statusChartType === 'donut'"
                                        :data="statusData"
                                        index="name"
                                        category="total"
                                        type="pie"
                                        :value-formatter="(value: number) => value.toString()"
                                    />
                                    <BarChart
                                        v-else
                                        :data="statusData"
                                        index="name"
                                        :categories="['total']"
                                        :y-formatter="(valueFormatter) => `${valueFormatter}`"
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <div class="flex items-center gap-3">
                                    <div
                                        class="h-10 w-10 bg-indigo-100 rounded-lg flex items-center justify-center"
                                    >
                                        <BarChart3 class="h-5 w-5 text-indigo-600" />
                                    </div>
                                    <div>
                                        <CardTitle>Ringkasan Status</CardTitle>
                                        <CardDescription>Rincian jumlah status</CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-3">
                                    <div
                                        class="flex items-center justify-between p-3 bg-yellow-50 rounded-lg border border-yellow-200"
                                    >
                                        <div class="flex items-center gap-3">
                                            <div class="h-3 w-3 rounded-full bg-yellow-500" />
                                            <span class="font-medium text-gray-900">Pending</span>
                                        </div>
                                        <Badge variant="secondary" class="text-lg">
                                            {{ totalStats.pending }}
                                        </Badge>
                                    </div>
                                    <div
                                        class="flex items-center justify-between p-3 bg-blue-50 rounded-lg border border-blue-200"
                                    >
                                        <div class="flex items-center gap-3">
                                            <div class="h-3 w-3 rounded-full bg-blue-500" />
                                            <span class="font-medium text-gray-900"
                                                >Dalam Peninjauan</span
                                            >
                                        </div>
                                        <Badge variant="secondary" class="text-lg">
                                            {{ totalStats.underReview }}
                                        </Badge>
                                    </div>
                                    <div
                                        class="flex items-center justify-between p-3 bg-green-50 rounded-lg border border-green-200"
                                    >
                                        <div class="flex items-center gap-3">
                                            <div class="h-3 w-3 rounded-full bg-green-500" />
                                            <span class="font-medium text-gray-900">Disetujui</span>
                                        </div>
                                        <Badge variant="secondary" class="text-lg">
                                            {{ totalStats.approved }}
                                        </Badge>
                                    </div>
                                    <div
                                        class="flex items-center justify-between p-3 bg-red-50 rounded-lg border border-red-200"
                                    >
                                        <div class="flex items-center gap-3">
                                            <div class="h-3 w-3 rounded-full bg-red-500" />
                                            <span class="font-medium text-gray-900">Ditolak</span>
                                        </div>
                                        <Badge variant="secondary" class="text-lg">
                                            {{ totalStats.rejected }}
                                        </Badge>
                                    </div>
                                    <div
                                        class="flex items-center justify-between p-3 bg-purple-50 rounded-lg border border-purple-200"
                                    >
                                        <div class="flex items-center gap-3">
                                            <div class="h-3 w-3 rounded-full bg-purple-500" />
                                            <span class="font-medium text-gray-900">Revisi</span>
                                        </div>
                                        <Badge variant="secondary" class="text-lg">
                                            {{ totalStats.revision }}
                                        </Badge>
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
                            <div class="flex items-center justify-between flex-wrap gap-3">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="h-10 w-10 bg-indigo-100 rounded-lg flex items-center justify-center"
                                    >
                                        <Building2 class="h-5 w-5 text-indigo-600" />
                                    </div>
                                    <div>
                                        <CardTitle>Distribusi Fakultas</CardTitle>
                                        <CardDescription>
                                            Formulir dan pengajuan berdasarkan fakultas
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
                                <DonutChart
                                    v-if="facultyChartType === 'donut'"
                                    :data="facultyData"
                                    index="name"
                                    category="forms"
                                    :value-formatter="(value: number) => value.toString()"
                                />
                                <BarChart
                                    v-else
                                    :data="facultyData"
                                    index="name"
                                    type="grouped"
                                    :categories="['forms', 'submissions']"
                                    :colors="['#16a34a', '#9333ea']"
                                    :y-formatter="(valueFormatter) => `${valueFormatter}`"
                                />
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Program Study Tab -->
                <TabsContent value="prodi" class="space-y-4">
                    <Card>
                        <CardHeader>
                            <div class="flex items-center justify-between flex-wrap gap-3">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="h-10 w-10 bg-teal-100 rounded-lg flex items-center justify-center"
                                    >
                                        <GraduationCap class="h-5 w-5 text-teal-600" />
                                    </div>
                                    <div>
                                        <CardTitle>Distribusi Program Studi</CardTitle>
                                        <CardDescription>
                                            Formulir dan pengajuan berdasarkan program studi
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
                                <DonutChart
                                    v-if="prodiChartType === 'donut'"
                                    :data="prodiData"
                                    index="name"
                                    category="submissions"
                                    :value-formatter="(value: number) => value.toString()"
                                />
                                <BarChart
                                    v-else
                                    :data="prodiData"
                                    index="name"
                                    type="grouped"
                                    :colors="['#16a34a', '#9333ea']"
                                    :categories="['forms', 'submissions']"
                                    :y-formatter="(valueFormatter) => `${valueFormatter}`"
                                />
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    </AuthenticatedLayout>
</template>
