<!-- filepath: e:\ITK\sim-kerjasama-itk\resources\js\Pages\StudyPrograms\Index.vue -->
<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow
} from "@/Components/ui/table";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/Components/ui/select";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/Components/ui/dialog"
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import {
    Plus,
    Search,
    MoreVertical,
    Edit,
    Trash2,
    GraduationCap,
    Building2,
    ArrowUpDown,
    ChevronLeft,
    ChevronRight,
    Filter,
    AlertTriangle
} from "lucide-vue-next";
import { debounce } from 'lodash';
import { useToast } from "@/Components/ui/toast/use-toast";

interface Faculty {
    id: number;
    name: string;
}

interface StudyProgram {
    id: number;
    name: string;
    faculty_id: number;
    faculty: Faculty;
    created_at: string;
    updated_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedStudyPrograms {
    data: StudyProgram[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: PaginationLink[];
}

interface Filters {
    search: string;
    faculty_id: string;
    per_page: number;
    sort_by: string;
    sort_order: string;
}

interface FlashMessages {
    success?: string;
    error?: string;
}

interface Props {
    studyPrograms: PaginatedStudyPrograms;
    faculties: Faculty[];
    filters: Filters;
}

// Extend the existing PageProps interface
interface ExtendedPageProps {
    flash?: FlashMessages;
    auth: any;
    [key: string]: any;
}

const props = defineProps<Props>();
const page = usePage<ExtendedPageProps>();
const { toast } = useToast();

// Reactive filters
const search = ref(props.filters.search || '');
const facultyFilter = ref(props.filters.faculty_id || 'all');
const perPage = ref(props.filters.per_page || 10);
const sortBy = ref(props.filters.sort_by || 'name');
const sortOrder = ref(props.filters.sort_order || 'asc');

const showDeleteDialog = ref(false);
const prodiToDelete = ref<StudyProgram | null>(null);

// Loading states
const isDeleting = ref<number | null>(null);

// Watch for flash messages
watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) {
            toast({
                title: "Sukses",
                description: flash.success,
            });
        }
        if (flash?.error) {
            toast({
                title: "Error",
                description: flash.error,
                variant: "destructive",
            });
        }
    },
    { immediate: true, deep: true }
);

// Search debounced function
const debouncedSearch = debounce((value: string) => {
    updateFilters({ search: value });
}, 300);

// Watch for changes
watch(search, (newValue) => {
    debouncedSearch(newValue);
});

watch(facultyFilter, (newValue) => {
    const filterValue = newValue === 'all' ? '' : newValue;
    updateFilters({ faculty_id: filterValue });
});

const confirmDelete = () => {
    if (prodiToDelete.value) {
        isDeleting.value = prodiToDelete.value.id;
        router.delete(route("admin.faculties.study-programs.destroy", prodiToDelete.value.id), {
            onSuccess: () => {
                showDeleteDialog.value = false;
                prodiToDelete.value = null;
                toast({
                    title: "Sukses",
                    description: "Program Studi berhasil dihapus!",
                });
            },
            onError: (errors) => {
                toast({
                    title: "Error",
                    description: errors.error || "Gagal menghapus program studi.",
                    variant: "destructive",
                });
            },
            onFinish: () => {
                isDeleting.value = null;
                showDeleteDialog.value = false;
                prodiToDelete.value = null;
            }
        });
    }
}

const cancelDelete = () => {
    showDeleteDialog.value = false;
    prodiToDelete.value = null;
};

// Update filters function
const updateFilters = (newFilters: Partial<Filters>) => {
    router.get(route('admin.faculties.study-programs'), {
        ...props.filters,
        ...newFilters,
    }, {
        preserveState: true,
        replace: true,
    });
};

// Sort function
const sortTable = (column: string) => {
    const newSortOrder = sortBy.value === column && sortOrder.value === 'asc' ? 'desc' : 'asc';
    sortBy.value = column;
    sortOrder.value = newSortOrder;

    updateFilters({
        sort_by: column,
        sort_order: newSortOrder,
    });
};

// Delete function
const deleteStudyProgram = (studyProgram: StudyProgram) => {
    prodiToDelete.value = studyProgram;
    showDeleteDialog.value = true;
};

// Pagination function
const goToPage = (url: string | null) => {
    if (url) {
        router.visit(url, {
            preserveState: true,
        });
    }
};

// Per page change
const changePerPage = (newPerPage: any) => {
    if (newPerPage !== null && newPerPage !== undefined) {
        let perPageNumber: number;
        if (typeof newPerPage === 'string') {
            perPageNumber = parseInt(newPerPage);
        } else if (typeof newPerPage === 'bigint') {
            perPageNumber = Number(newPerPage);
        } else if (typeof newPerPage === 'number') {
            perPageNumber = newPerPage;
        } else {
            return;
        }
        perPage.value = perPageNumber;
        updateFilters({ per_page: perPageNumber });
    }
};

// Clear filters
const clearFilters = () => {
    search.value = '';
    facultyFilter.value = 'all';
    updateFilters({ search: '', faculty_id: '' });
};

// Success/Error messages
const successMessage = computed(() => page.props.flash?.success);
const errorMessage = computed(() => page.props.flash?.error);

// Sort icon helper
const getSortIcon = (column: string) => {
    if (sortBy.value !== column) return 'text-gray-400';
    return sortOrder.value === 'asc' ? 'text-blue-600 rotate-0' : 'text-blue-600 rotate-180';
};

// Active filters count
const activeFiltersCount = computed(() => {
    let count = 0;
    if (search.value) count++;
    if (facultyFilter.value && facultyFilter.value !== 'all') count++;
    return count;
});
</script>

<template>
  <Head title="Manajemen Program Studi" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Manajemen Program Studi
          </h2>
        </div>
        <div class="flex items-center gap-2">
          <Button
            variant="outline"
            @click="router.visit(route('admin.faculties.index'))"
          >
            <Building2 class="h-4 w-4 mr-2" />
            Fakultas
          </Button>
          <Button @click="router.visit(route('admin.faculties.study-programs.create'))">
            <Plus class="h-4 w-4 mr-2" />
            Tambah Program Studi
          </Button>
        </div>
      </div>
    </template>

    <div class="space-y-6">
      <!-- Filters Card -->
      <Card>
        <CardHeader>
          <div class="flex items-center justify-between">
            <CardTitle class="text-lg flex items-center gap-2">
              <Search class="h-5 w-5" />
              Cari & Filter
              <Badge
                v-if="activeFiltersCount > 0"
                variant="secondary"
              >
                {{ activeFiltersCount }} aktif
              </Badge>
            </CardTitle>
            <Button
              v-if="activeFiltersCount > 0"
              variant="ghost"
              size="sm"
              @click="clearFilters"
            >
              Hapus Semua Filter
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          <div class="flex flex-col lg:flex-row gap-4">
            <!-- Search Input -->
            <div class="flex-1">
              <div class="relative">
                <Search
                  class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4"
                />
                <Input
                  v-model="search"
                  placeholder="Cari berdasarkan nama program studi atau fakultas..."
                  class="pl-10"
                />
              </div>
            </div>

            <!-- Faculty Filter -->
            <div class="w-full lg:w-64">
              <Select v-model="facultyFilter">
                <SelectTrigger>
                  <SelectValue placeholder="Filter Berdasarkan Fakultas" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">
                    Semua Fakultas
                  </SelectItem>
                  <SelectItem
                    v-for="faculty in faculties"
                    :key="faculty.id"
                    :value="faculty.id.toString()"
                  >
                    {{ faculty.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <!-- Per Page Select -->
            <div class="w-full lg:w-36">
              <Select
                :model-value="perPage.toString()"
                @update:model-value="changePerPage"
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="5">
                    5 per halaman
                  </SelectItem>
                  <SelectItem value="10">
                    10 per halaman
                  </SelectItem>
                  <SelectItem value="25">
                    25 per halaman
                  </SelectItem>
                  <SelectItem value="50">
                    50 per halaman
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Data Table Card -->
      <Card>
        <CardHeader>
          <div class="flex items-center justify-between">
            <CardTitle class="flex items-center gap-2">
              Program Studi
              <Badge variant="secondary">
                {{ studyPrograms.total }} total
              </Badge>
            </CardTitle>
          </div>
        </CardHeader>
        <CardContent class="p-0">
          <!-- Table -->
          <div class="overflow-x-auto">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead class="w-12">
                    #
                  </TableHead>
                  <TableHead>
                    <button
                      class="flex items-center gap-1 hover:text-blue-600 font-semibold"
                      @click="sortTable('name')"
                    >
                      Nama Program Studi
                      <ArrowUpDown
                        class="h-4 w-4 transition-transform"
                        :class="getSortIcon('name')"
                      />
                    </button>
                  </TableHead>
                  <TableHead>
                    <button
                      class="flex items-center gap-1 hover:text-blue-600 font-semibold"
                      @click="sortTable('faculty_name')"
                    >
                      Fakultas
                      <ArrowUpDown
                        class="h-4 w-4 transition-transform"
                        :class="getSortIcon('faculty_name')"
                      />
                    </button>
                  </TableHead>
                  <TableHead>
                    <button
                      class="flex items-center gap-1 hover:text-blue-600 font-semibold"
                      @click="sortTable('created_at')"
                    >
                      Dibuat Pada
                      <ArrowUpDown
                        class="h-4 w-4 transition-transform"
                        :class="getSortIcon('created_at')"
                      />
                    </button>
                  </TableHead>
                  <TableHead class="text-center w-20">
                    Aksi
                  </TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-if="studyPrograms.data.length === 0">
                  <TableCell
                    colspan="5"
                    class="text-center py-8 text-gray-500"
                  >
                    <GraduationCap class="h-12 w-12 mx-auto mb-4 opacity-50" />
                    <p>Tidak ada Program Studi yang ditemukan</p>
                  </TableCell>
                </TableRow>
                <TableRow
                  v-for="(program, index) in studyPrograms.data"
                  :key="program.id"
                  class="hover:bg-muted/50"
                >
                  <TableCell class="font-medium">
                    {{ studyPrograms.from + index }}
                  </TableCell>
                  <TableCell>
                    <div class="font-medium">
                      {{ program.name }}
                    </div>
                  </TableCell>
                  <TableCell>
                    <Badge
                      variant="outline"
                      class="bg-blue-50 text-blue-700 border-blue-200"
                    >
                      {{ program.faculty.name }}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    {{ new Date(program.created_at).toLocaleDateString('id-ID') }}
                  </TableCell>
                  <TableCell>
                    <DropdownMenu>
                      <DropdownMenuTrigger as-child>
                        <Button
                          variant="ghost"
                          size="sm"
                          :disabled="isDeleting === program.id"
                        >
                          <MoreVertical class="h-4 w-4" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end">
                        <DropdownMenuItem
                          @click="router.visit(route('admin.faculties.study-programs.edit', program.id))"
                        >
                          <Edit class="h-4 w-4 mr-2" />
                          Edit
                        </DropdownMenuItem>
                        <DropdownMenuItem
                          class="text-red-600 cursor-pointer"
                          @click="deleteStudyProgram(program)"
                        >
                          <Trash2 class="h-4 w-4 mr-2" />
                          Hapus
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>

          <!-- Pagination -->
          <div
            v-if="studyPrograms.last_page > 1"
            class="flex items-center justify-between px-6 py-4 border-t"
          >
            <div class="text-sm text-gray-500">
              Menampilkan {{ studyPrograms.from }} hingga {{ studyPrograms.to }} dari {{
                studyPrograms.total }}
              hasil
            </div>
            <div class="flex items-center space-x-2">
              <!-- Previous Button -->
              <Button
                variant="outline"
                size="sm"
                :disabled="!studyPrograms.links[0].url"
                @click="goToPage(studyPrograms.links[0].url)"
              >
                <ChevronLeft class="h-4 w-4" />
                Halaman Sebelumnya
              </Button>

              <!-- Page Numbers -->
              <template
                v-for="link in studyPrograms.links.slice(1, -1)"
                :key="link.label"
              >
                <Button
                  variant="outline"
                  size="sm"
                  :disabled="!link.url"
                  :class="{
                    'bg-blue-600 text-white hover:bg-blue-700': link.active,
                    'hover:bg-gray-100': !link.active
                  }"
                  @click="goToPage(link.url)"
                >
                  {{ link.label }}
                </Button>
              </template>

              <!-- Next Button -->
              <Button
                variant="outline"
                size="sm"
                :disabled="!studyPrograms.links[studyPrograms.links.length - 1].url"
                @click="goToPage(studyPrograms.links[studyPrograms.links.length - 1].url)"
              >
                Halaman Selanjutnya
                <ChevronRight class="h-4 w-4" />
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Delete Confirmation Dialog -->
    <Dialog v-model:open="showDeleteDialog">
      <DialogContent class="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle class="flex items-center gap-2">
            <AlertTriangle class="h-5 w-5 text-destructive" />
            Konfirmasi Hapus Program Studi
          </DialogTitle>
          <DialogDescription>
            Apakah Anda yakin ingin menghapus program studi ini? Tindakan ini tidak dapat dibatalkan.
          </DialogDescription>
        </DialogHeader>

        <div
          v-if="prodiToDelete"
          class="py-4"
        >
          <div class="p-4 bg-muted rounded-lg">
            <div class="flex items-center gap-3">
              <div
                class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center shrink-0"
              >
                <GraduationCap class="h-6 w-6 text-blue-600" />
              </div>
              <div class="flex-1 min-w-0">
                <h4 class="font-semibold text-lg truncate">
                  {{ prodiToDelete.name }}
                </h4>
                <div class="flex items-center gap-2 mt-2">
                  <Badge
                    variant="outline"
                    class="bg-blue-50 text-blue-700 border-blue-200"
                  >
                    {{ prodiToDelete.faculty.name }}
                  </Badge>
                </div>
                <p class="text-sm text-muted-foreground mt-2">
                  Dibuat pada: {{ new Date(prodiToDelete.created_at).toLocaleDateString('id-ID', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                  }) }}
                </p>
              </div>
            </div>
          </div>

          <!-- Warning Message -->
          <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
            <div class="flex items-start gap-2">
              <AlertTriangle class="h-5 w-5 text-yellow-600 shrink-0 mt-0.5" />
              <div class="text-sm text-yellow-800">
                <p class="font-medium mb-1">
                  Perhatian
                </p>
                <p>
                  Menghapus program studi ini akan menghapus semua data terkait yang berhubungan
                  dengan program studi ini.
                </p>
              </div>
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button
            variant="outline"
            :disabled="isDeleting !== null"
            @click="cancelDelete"
          >
            Batal
          </Button>
          <Button
            variant="destructive"
            :disabled="isDeleting !== null"
            @click="confirmDelete"
          >
            <Trash2
              v-if="isDeleting === null"
              class="h-4 w-4 mr-2"
            />
            <span v-if="isDeleting === null">Hapus Program Studi</span>
            <span v-else>Menghapus...</span>
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </AuthenticatedLayout>
</template>
