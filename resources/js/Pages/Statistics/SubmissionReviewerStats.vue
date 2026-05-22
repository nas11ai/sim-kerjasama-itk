<script setup lang="ts">
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/vue3";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/Components/ui/tabs";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Checkbox } from "@/Components/ui/checkbox";
import { Label } from "@/Components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/Components/ui/select";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/Components/ui/popover";
import {
    Calendar,
    Clock,
    Building2,
    GraduationCap,
    TrendingUp,
    FileText,
    UserCheck,
    Users,
    Filter,
    AlertCircle,
    CheckCircle,
    BarChart3,
    PieChart as PieChartIcon,
} from "lucide-vue-next";
import { computed, ref, watch } from "vue";
import DonutChart from "@/Components/ui/chart-donut/DonutChart.vue";
import BarChart from "@/Components/ui/chart-bar/BarChart.vue";

interface ReviewerRecent {
    id: number;
    user_id: number;
    users_name: string;
    reviewer_role_id: number;
}

interface TotalByRole {
    id: number;
    reviewer_role_name: string;
    total_reviewers: number;
}

interface EvaluationStatus {
    evaluation_status: string;
    total: number;
}

interface ReviewerByYear {
    year: number;
    total: number;
}

interface ReviewerByFaculty {
    name: string;
    total: number;
}

interface ReviewerByProdi {
    name: string;
    total: number;
}

interface ReviewerActiveStatus {
    user_id: number;
    reviewer_role_id: number;
}

interface Faculty {
    id: number;
    name: string;
}

interface StudyProgram {
    id: number;
    name: string;
    faculty_id: number;
}

const props = defineProps<{
    reviewerRecent: ReviewerRecent[],
    totalReviewers: number,
    totalByRole: TotalByRole[],
    evaluationStatus: EvaluationStatus[],
    reviewerByYear: ReviewerByYear[],
    reviewerByFaculty: ReviewerByFaculty[],
    reviewerByProdi: ReviewerByProdi[],
    reviewerActiveStatus: ReviewerActiveStatus[],
    faculties: Faculty[],
    studyPrograms: StudyProgram[],
}>();

// Year filter
const selectedYear = ref<string>("all");
const availableYears = computed(() => {
    const years = ["all"];
    const yearsFromData = props.reviewerByYear?.map(item => item.year.toString()) || [];
    return [...years, ...yearsFromData.sort((a, b) => parseInt(b) - parseInt(a))];
});

// Chart type states
const facultyChartType = ref<"bar" | "donut">("bar");
const prodiChartType = ref<"bar" | "donut">("bar");
const roleChartType = ref<"bar" | "donut">("donut");
const statusChartType = ref<"bar" | "donut">("donut");
const selectedFaculties = ref<number[]>([]);
const selectedStudyPrograms = ref<number[]>([]);

// Recent reviewers count
const recentReviewersCount = computed(() => props.reviewerRecent?.length || 0);

// Active reviewers count
const activeReviewersCount = computed(() => props.reviewerActiveStatus?.length || 0);

// Role chart data
const roleData = computed(() => {
    return props.totalByRole?.map(item => ({
        name: item.reviewer_role_name,
        total: item.total_reviewers,
    })) || [];
});

const faculties = computed(() => props.faculties);
const studyPrograms = computed(() => props.studyPrograms);

const toggleFaculties = (facultyId: number, checked?: boolean | 'indeterminate') => {
    const isSelected = selectedFaculties.value.includes(facultyId)
    const normalized = checked === 'indeterminate' ? true : checked ?? !isSelected

    if (normalized) {
        if (!isSelected) selectedFaculties.value.push(facultyId)
    } else {
        selectedFaculties.value = selectedFaculties.value.filter(id => id !== facultyId)
    }
};

const toggleStudyProgram = (studyProgramId: number, checked?: boolean | 'indeterminate') => {
    const isSelected = selectedStudyPrograms.value.includes(studyProgramId)
    const normalized = checked === 'indeterminate' ? true : checked ?? !isSelected

    if (normalized) {
        if (!isSelected) selectedStudyPrograms.value.push(studyProgramId)
    } else {
        selectedStudyPrograms.value = selectedStudyPrograms.value.filter(id => id !== studyProgramId)
    }
};

const selectAllFaculties = () => {
    if (selectedFaculties.value.length === faculties.value.length) {
        selectedFaculties.value = []
    } else {
        selectedFaculties.value = faculties.value.map(sp => sp.id)
    }
};

const selectAllStudyPrograms = () => {
    if (selectedStudyPrograms.value.length === studyPrograms.value.length) {
        selectedStudyPrograms.value = []
    } else {
        selectedStudyPrograms.value = studyPrograms.value.map(sp => sp.id)
    }
};

// Evaluation status chart data
const evaluationStatusData = computed(() => {
    return props.evaluationStatus?.map(item => ({
        name: item.evaluation_status.charAt(0).toUpperCase() + item.evaluation_status.slice(1).replace('_', ' '),
        total: parseInt(item.total.toString()) || 0,
    })) || [];
});

// Faculty chart data (filtered)
const facultyData = computed(() => {
    const data = props.reviewerByFaculty || [];

    // Jika tidak ada filter yang dipilih, tampilkan semua
    if (selectedFaculties.value.length === 0) {
        return data.map(item => ({
            name: item.name,
            total: item.total || 0,
        }));
    }

    // Filter berdasarkan fakultas yang dipilih
    return data
        .filter(item => {
            const faculty = props.faculties.find(f => f.name === item.name);
            return faculty && selectedFaculties.value.includes(faculty.id);
        })
        .map(item => ({
            name: item.name,
            total: item.total || 0,
        }));
});

// Prodi chart data (filtered)
const prodiData = computed(() => {
   const data = props.reviewerByProdi || [];

    // Jika tidak ada filter yang dipilih, tampilkan semua
    if (selectedStudyPrograms.value.length === 0) {
        return data.map(item => ({
            name: item.name,
            total: item.total || 0,
        }));
    }

    // Filter berdasarkan prodi yang dipilih
    return data
        .filter(item => {
            const studyProgram = props.studyPrograms.find(f => f.name === item.name);
            return studyProgram && selectedStudyPrograms.value.includes(studyProgram.id);
        })
        .map(item => ({
            name: item.name,
            total: item.total || 0,
        }));
});

// Get status color
const getStatusColor = (status: string) => {
    const statusLower = status.toLowerCase();
    if (statusLower.includes('approved') || statusLower.includes('completed')) return 'bg-green-500';
    if (statusLower.includes('pending') || statusLower.includes('waiting')) return 'bg-yellow-500';
    if (statusLower.includes('rejected') || statusLower.includes('declined')) return 'bg-red-500';
    if (statusLower.includes('review') || statusLower.includes('progress')) return 'bg-blue-500';
    return 'bg-gray-500';
};
</script>

<template>
  <Head title="Statistik Reviewer Pengajuan" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Statistik Reviewer Pengajuan
          </h2>
        </div>
        <div class="flex items-center gap-3">
          <Select v-model="selectedYear">
            <SelectTrigger class="w-[180px]">
              <SelectValue placeholder="Filter by Year" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">
                Semua Tahun
              </SelectItem>
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
                  Total Reviewer
                </p>
                <p class="text-2xl font-bold text-gray-900 mt-1">
                  {{ totalReviewers }}
                </p>
                <p class="text-xs text-gray-500 mt-1">
                  Semua reviewer
                </p>
              </div>
              <div class="h-10 w-10 bg-blue-100 rounded-lg flex items-center justify-center">
                <Users class="h-5 w-5 text-blue-600" />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card class="border-l-4 border-l-green-500 hover:shadow-lg transition-shadow">
          <CardContent class="p-4">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-xs font-medium text-gray-500 uppercase">
                  Reviewer Aktif
                </p>
                <p class="text-2xl font-bold text-gray-900 mt-1">
                  {{ activeReviewersCount }}
                </p>
                <p class="text-xs text-gray-500 mt-1">
                  Sedang aktif
                </p>
              </div>
              <div class="h-10 w-10 bg-green-100 rounded-lg flex items-center justify-center">
                <CheckCircle class="h-5 w-5 text-green-600" />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card class="border-l-4 border-l-purple-500 hover:shadow-lg transition-shadow">
          <CardContent class="p-4">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-xs font-medium text-gray-500 uppercase">
                  Terbaru (24 jam)
                </p>
                <p class="text-2xl font-bold text-gray-900 mt-1">
                  {{ recentReviewersCount }}
                </p>
                <p class="text-xs text-gray-500 mt-1">
                  Reviewer baru
                </p>
              </div>
              <div class="h-10 w-10 bg-purple-100 rounded-lg flex items-center justify-center">
                <Clock class="h-5 w-5 text-purple-600" />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card class="border-l-4 border-l-orange-500 hover:shadow-lg transition-shadow">
          <CardContent class="p-4">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-xs font-medium text-gray-500 uppercase">
                  Role Reviewer
                </p>
                <p class="text-2xl font-bold text-gray-900 mt-1">
                  {{ totalByRole?.length || 0 }}
                </p>
                <p class="text-xs text-gray-500 mt-1">
                  Total role
                </p>
              </div>
              <div class="h-10 w-10 bg-orange-100 rounded-lg flex items-center justify-center">
                <UserCheck class="h-5 w-5 text-orange-600" />
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Recent Reviewers Section -->
      <Card>
        <CardHeader>
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="h-10 w-10 bg-purple-100 rounded-lg flex items-center justify-center">
                <Clock class="h-5 w-5 text-purple-600" />
              </div>
              <div>
                <CardTitle>Reviewer Terbaru (24 Jam Terakhir)</CardTitle>
                <CardDescription>Reviewer yang baru ditambahkan dalam sistem</CardDescription>
              </div>
            </div>
            <Badge
              variant="secondary"
              class="text-sm"
            >
              {{ recentReviewersCount }} total
            </Badge>
          </div>
        </CardHeader>
        <CardContent>
          <div
            v-if="reviewerRecent.length === 0"
            class="text-center py-12"
          >
            <Clock class="h-16 w-16 mx-auto text-gray-300 mb-4" />
            <h3 class="text-lg font-semibold text-gray-500 mb-2">
              Tidak ada Reviewer Terbaru
            </h3>
            <p class="text-sm text-gray-400">
              Tidak ada reviewer yang ditambahkan dalam 24 jam terakhir
            </p>
          </div>
          <div
            v-else
            class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3"
          >
            <div
              v-for="reviewer in reviewerRecent"
              :key="reviewer.id"
              class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
            >
              <div class="h-10 w-10 bg-purple-100 rounded-full flex items-center justify-center">
                <UserCheck class="h-5 w-5 text-purple-600" />
              </div>
              <div class="flex-1 min-w-0">
                <p class="font-medium text-gray-900 truncate">
                  {{ reviewer.users_name }}
                </p>
                <p class="text-xs text-gray-500">
                  ID: {{ reviewer.user_id }}
                </p>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Main Charts Section -->
      <Tabs
        default-value="status"
        class="w-full"
      >
        <TabsList class="grid w-full grid-cols-4">
          <TabsTrigger value="status">
            Status Evaluasi
          </TabsTrigger>
          <TabsTrigger value="role">
            Berdasarkan Role
          </TabsTrigger>
          <TabsTrigger value="faculty">
            Berdasarkan Fakultas
          </TabsTrigger>
          <TabsTrigger value="prodi">
            Berdasarkan Program Studi
          </TabsTrigger>
        </TabsList>

        <!-- Evaluation Status Tab -->
        <TabsContent
          value="status"
          class="space-y-4"
        >
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <Card>
              <CardHeader>
                <div class="flex items-center justify-between flex-wrap gap-3">
                  <div class="flex items-center gap-3">
                    <div class="h-10 w-10 bg-green-100 rounded-lg flex items-center justify-center">
                      <TrendingUp class="h-5 w-5 text-green-600" />
                    </div>
                    <div>
                      <CardTitle>Status Evaluasi</CardTitle>
                      <CardDescription>Distribusi status evaluasi saat ini</CardDescription>
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
                <div
                  v-if="evaluationStatusData.length === 0"
                  class="text-center py-12"
                >
                  <AlertCircle class="h-16 w-16 mx-auto text-gray-300 mb-4" />
                  <p class="text-gray-500">
                    Tidak ada data status evaluasi tersedia
                  </p>
                </div>
                <div v-else>
                  <DonutChart
                    v-if="statusChartType === 'donut'"
                    :data="evaluationStatusData"
                    index="name"
                    category="total"
                    :value-formatter="
                      (valueFormatter) =>
                        `Total ${valueFormatter} Evaluations`
                    "
                    class="h-[256px]"
                  />
                  <BarChart
                    v-else
                    :data="evaluationStatusData"
                    index="name"
                    :categories="['total']"
                    :y-formatter="
                      (valueFormatter) =>
                        `${valueFormatter} Evaluations`
                    "
                  />
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <div class="flex items-center gap-3">
                  <div class="h-10 w-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <FileText class="h-5 w-5 text-blue-600" />
                  </div>
                  <div>
                    <CardTitle>Detail Status</CardTitle>
                    <CardDescription>Rincian berdasarkan status evaluasi</CardDescription>
                  </div>
                </div>
              </CardHeader>
              <CardContent>
                <div
                  v-if="evaluationStatusData.length === 0"
                  class="text-center py-12"
                >
                  <AlertCircle class="h-16 w-16 mx-auto text-gray-300 mb-4" />
                  <p class="text-gray-500">
                    Tidak ada data status evaluasi tersedia
                  </p>
                </div>
                <div
                  v-else
                  class="space-y-3"
                >
                  <div
                    v-for="status in evaluationStatusData"
                    :key="status.name"
                    class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
                  >
                    <div class="flex items-center gap-3">
                      <div
                        class="h-3 w-3 rounded-full"
                        :class="getStatusColor(status.name)"
                      />
                      <span class="font-medium text-gray-900">{{ status.name }}</span>
                    </div>
                    <Badge
                      variant="secondary"
                      class="text-base"
                    >
                      {{ status.total }}
                    </Badge>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </TabsContent>

        <!-- Role Tab -->
        <TabsContent
          value="role"
          class="space-y-4"
        >
          <Card>
            <CardHeader>
              <div class="flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-3">
                  <div class="h-10 w-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                    <UserCheck class="h-5 w-5 text-indigo-600" />
                  </div>
                  <div>
                    <CardTitle>Reviewer berdasarkan Role</CardTitle>
                    <CardDescription>Distribusi reviewer berdasarkan berbagai role</CardDescription>
                  </div>
                </div>
                <div class="flex items-center gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    :class="{ 'bg-gray-100': roleChartType === 'donut' }"
                    @click="roleChartType = 'donut'"
                  >
                    <PieChartIcon class="h-4 w-4" />
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    :class="{ 'bg-gray-100': roleChartType === 'bar' }"
                    @click="roleChartType = 'bar'"
                  >
                    <BarChart3 class="h-4 w-4" />
                  </Button>
                </div>
              </div>
            </CardHeader>
            <CardContent>
              <div
                v-if="roleData.length === 0"
                class="text-center py-12"
              >
                <UserCheck class="h-16 w-16 mx-auto text-gray-300 mb-4" />
                <p class="text-gray-500">
                  Tidak ada data role tersedia
                </p>
              </div>
              <div v-else>
                <DonutChart
                  v-if="roleChartType === 'donut'"
                  :data="roleData"
                  index="name"
                  :category="'total'"
                  :value-formatter="
                    (valueFormatter) =>
                      `Total ${valueFormatter} Reviewers`
                  "
                  class="h-[256px]"
                />
                <BarChart
                  v-else
                  :data="roleData"
                  index="name"
                  :categories="['total']"
                  :y-formatter="
                    (valueFormatter) =>
                      `${valueFormatter} Reviewers`
                  "
                />
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <!-- Faculty Tab -->
        <TabsContent
          value="faculty"
          class="space-y-4"
        >
          <Card>
            <CardHeader>
              <div class="flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-3">
                  <div class="h-10 w-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                    <Building2 class="h-5 w-5 text-indigo-600" />
                  </div>
                  <div>
                    <CardTitle>Reviewer berdasarkan Fakultas</CardTitle>
                    <CardDescription>
                      Distribusi reviewer berdasarkan fakultas
                      <span class="text-indigo-600 font-medium ml-1">
                        ({{ selectedFaculties.length }}/{{ faculties.length }} dipilih)
                      </span>
                    </CardDescription>
                  </div>
                </div>
                <div class="flex items-center gap-2">
                  <Popover>
                    <PopoverTrigger as-child>
                      <Button
                        variant="outline"
                        size="sm"
                      >
                        <Filter class="h-4 w-4 mr-2" />
                        Filter
                      </Button>
                    </PopoverTrigger>
                    <PopoverContent
                      class="w-80"
                      align="end"
                    >
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
                            {{ selectedFaculties.length === faculties.length ? 'Batal Pilih Semua' : 'Pilih Semua' }}
                          </Button>
                        </div>
                        <div class="max-h-[300px] overflow-y-auto space-y-2">
                          <div
                            v-for="faculty in faculties"
                            :key="faculty.id"
                            class="flex items-center space-x-2"
                          >
                            <Checkbox
                              :id="'faculty-' + faculty.name"
                              :model-value="selectedFaculties.includes(faculty.id)"
                              @update:model-value="(val) => toggleFaculties(faculty.id, val)"
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
              <div
                v-if="facultyData.length === 0"
                class="text-center py-12"
              >
                <Building2 class="h-16 w-16 mx-auto text-gray-300 mb-4" />
                <p class="text-gray-500">
                  {{ selectedFaculties.length === 0 ? 'Silakan pilih setidaknya satu fakultas' : 'Tidak ada data fakultas tersedia' }}
                </p>
              </div>
              <div v-else>
                <DonutChart
                  v-if="facultyChartType === 'donut'"
                  :data="facultyData"
                  index="name"
                  category="total"
                  :value-formatter="
                    (valueFormatter) =>
                      `Total ${valueFormatter} Reviewers`
                  "
                  class="h-[256px]"
                />
                <BarChart
                  v-else
                  :data="facultyData"
                  index="name"
                  :categories="['total']"
                  :y-formatter="
                    (valueFormatter) =>
                      `${valueFormatter} Reviewers`
                  "
                />
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <!-- Program Study Tab -->
        <TabsContent
          value="prodi"
          class="space-y-4"
        >
          <Card>
            <CardHeader>
              <div class="flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-3">
                  <div class="h-10 w-10 bg-teal-100 rounded-lg flex items-center justify-center">
                    <GraduationCap class="h-5 w-5 text-teal-600" />
                  </div>
                  <div>
                    <CardTitle>Reviewer berdasarkan Program Studi</CardTitle>
                    <CardDescription>
                      Distribusi reviewer berdasarkan program studi
                      <span class="text-teal-600 font-medium ml-1">
                        ({{ selectedStudyPrograms.length }}/{{ studyPrograms.length }} dipilih)
                      </span>
                    </CardDescription>
                  </div>
                </div>
                <div class="flex items-center gap-2">
                  <Popover>
                    <PopoverTrigger as-child>
                      <Button
                        variant="outline"
                        size="sm"
                      >
                        <Filter class="h-4 w-4 mr-2" />
                        Filter
                      </Button>
                    </PopoverTrigger>
                    <PopoverContent
                      class="w-80"
                      align="end"
                    >
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
                            {{ selectedStudyPrograms.length === studyPrograms.length ? 'Batal Pilih Semua' : 'Pilih Semua' }}
                          </Button>
                        </div>
                        <div class="max-h-[300px] overflow-y-auto space-y-2">
                          <div
                            v-for="prodi in studyPrograms"
                            :key="prodi.id"
                            class="flex items-center space-x-2"
                          >
                            <Checkbox
                              :id="'prodi-' + prodi.name"
                              :model-value="selectedStudyPrograms.includes(prodi.id)"
                              @update:model-value="(val) => toggleStudyProgram(prodi.id, val)"
                            />
                            <Label
                              :for="'prodi-' + prodi.name"
                              class="text-sm font-normal cursor-pointer"
                            >
                              {{ prodi.name }}
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
              <div
                v-if="prodiData.length === 0"
                class="text-center py-12"
              >
                <GraduationCap class="h-16 w-16 mx-auto text-gray-300 mb-4" />
                <p class="text-gray-500">
                  {{ selectedStudyPrograms.length === 0 ? 'Silakan pilih setidaknya satu program studi' : 'Tidak ada data program tersedia' }}
                </p>
              </div>
              <div v-else>
                <DonutChart
                  v-if="prodiChartType === 'donut'"
                  :data="prodiData"
                  index="name"
                  category="total"
                  :value-formatter="
                    (valueFormatter) =>
                      `Total ${valueFormatter} Reviewers`
                  "
                  class="h-[256px]"
                />
                <BarChart
                  v-else
                  :data="prodiData"
                  index="name"
                  :categories="['total']"
                  :y-formatter="(tick) => tick.toString()"
                />
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  </AuthenticatedLayout>
</template>
