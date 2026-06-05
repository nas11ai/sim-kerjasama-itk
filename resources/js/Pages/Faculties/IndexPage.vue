<script setup lang="ts">
import { ref, watch } from 'vue'
import { route } from 'ziggy-js'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu'
import {
    Plus,
    Search,
    MoreVertical,
    Edit,
    Trash2,
    Eye,
    GraduationCap,
    Building2,
    ArrowUpDown,
    ChevronLeft,
    ChevronRight,
    AlertTriangle,
    UserCheck,
} from 'lucide-vue-next'
import { debounce } from 'lodash'
import { useToast } from '@/Components/ui/toast/use-toast'

const { toast } = useToast()

interface Faculty {
    id: number
    name: string
    study_programs_count: number
    created_at: string
    updated_at: string
}

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

interface PaginatedFaculties {
    data: Faculty[]
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number
    to: number
    links: PaginationLink[]
}

interface Filters {
    search: string
    per_page: number
    sort_by: string
    sort_order: string
}

interface FlashMessages {
    success?: string
    error?: string
}

interface Props {
    faculties: PaginatedFaculties
    filters: Filters
}

// // Extend the existing PageProps interface
// interface ExtendedPageProps {
//     flash?: FlashMessages
//     auth: any // This should match your existing auth type
//     [key: string]: any // Allow additional properties
// }

const props = defineProps<Props>()
// const page = usePage<ExtendedPageProps>()

// Reactive filters
const search = ref(props.filters.search || '')
const perPage = ref(props.filters.per_page || 10)
const sortBy = ref(props.filters.sort_by || 'name')
const sortOrder = ref(props.filters.sort_order || 'asc')

const showDeleteDialog = ref(false)
const facultyToDelete = ref<Faculty | null>(null)

// Loading states
const isDeleting = ref<number | null>(null)

// Search debounced function
const debouncedSearch = debounce((value: string) => {
    updateFilters({ search: value })
}, 300)

// Watch for search changes
watch(search, (newValue) => {
    debouncedSearch(newValue)
})

// Update filters function
const updateFilters = (newFilters: Partial<Filters>) => {
    router.get(
        route('admin.faculties.index'),
        {
            ...props.filters,
            ...newFilters,
        },
        {
            preserveState: true,
            replace: true,
        }
    )
}

const confirmDelete = () => {
    if (facultyToDelete.value) {
        router.delete(route('admin.faculties.destroy', facultyToDelete.value.id), {
            onSuccess: () => {
                showDeleteDialog.value = false
                facultyToDelete.value = null
                toast({
                    title: 'Sukses',
                    description: 'Fakultas Berhasil dihapus!',
                })
            },
            onError: (errors) => {
                toast({
                    title: 'Error',
                    description: errors.error || 'Gagal menghapus fakultas.',
                    variant: 'destructive',
                })
            },
            onFinish: () => {
                showDeleteDialog.value = false
                facultyToDelete.value = null
            },
        })
    }
}

const cancelDelete = () => {
    showDeleteDialog.value = false
    facultyToDelete.value = null
}

// Sort function
const sortTable = (column: string) => {
    const newSortOrder = sortBy.value === column && sortOrder.value === 'asc' ? 'desc' : 'asc'
    sortBy.value = column
    sortOrder.value = newSortOrder

    updateFilters({
        sort_by: column,
        sort_order: newSortOrder,
    })
}

// Delete function
const deleteFaculty = (faculty: Faculty) => {
    facultyToDelete.value = faculty
    showDeleteDialog.value = true
}

// Pagination function
const goToPage = (url: string | null) => {
    if (url) {
        router.visit(url, {
            preserveState: true,
        })
    }
}

// Per page change
const changePerPage = (newPerPage: any) => {
    if (newPerPage !== null && newPerPage !== undefined) {
        let perPageNumber: number
        if (typeof newPerPage === 'string') {
            perPageNumber = parseInt(newPerPage)
        } else if (typeof newPerPage === 'bigint') {
            perPageNumber = Number(newPerPage)
        } else if (typeof newPerPage === 'number') {
            perPageNumber = newPerPage
        } else {
            // Handle Record<string, any> or other types
            return
        }
        perPage.value = perPageNumber
        updateFilters({ per_page: perPageNumber })
    }
}

// Success/Error messages
// const successMessage = computed(() => page.props.flash?.success)
// const errorMessage = computed(() => page.props.flash?.error)

// Sort icon helper
const getSortIcon = (column: string) => {
    if (sortBy.value !== column) return 'text-gray-400'
    return sortOrder.value === 'asc' ? 'text-blue-600 rotate-0' : 'text-blue-600 rotate-180'
}
</script>

<template>
    <Head title="Manajemen Fakultas" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Manajemen Fakultas
                    </h2>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        @click="router.visit(route('admin.faculties.study-programs'))"
                    >
                        <GraduationCap class="h-4 w-4 mr-2" />
                        Program Studi
                    </Button>
                    <Button @click="router.visit(route('admin.faculties.create'))">
                        <Plus class="h-4 w-4 mr-2" />
                        Tambah Fakultas
                    </Button>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Filters Card -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-lg flex items-center gap-2">
                        <Search class="h-5 w-5" />
                        Cari & Filter
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-col sm:flex-row gap-4">
                        <!-- Search Input -->
                        <div class="flex-1">
                            <div class="relative">
                                <Search
                                    class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4"
                                />
                                <Input
                                    v-model="search"
                                    placeholder="Cari Fakultas..."
                                    class="pl-10"
                                />
                            </div>
                        </div>

                        <!-- Per Page Select -->
                        <div class="w-full sm:w-36">
                            <Select
                                :model-value="perPage.toString()"
                                @update:model-value="changePerPage"
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="5"> 5 per Halaman </SelectItem>
                                    <SelectItem value="10"> 10 per Halaman </SelectItem>
                                    <SelectItem value="25"> 25 per Halaman </SelectItem>
                                    <SelectItem value="50"> 50 per Halaman </SelectItem>
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
                            Fakultas
                            <Badge variant="secondary"> {{ faculties.total }} total </Badge>
                        </CardTitle>
                    </div>
                </CardHeader>
                <CardContent class="p-0">
                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="w-12"> # </TableHead>
                                    <TableHead>
                                        <button
                                            class="flex items-center gap-1 hover:text-blue-600 font-semibold"
                                            @click="sortTable('name')"
                                        >
                                            Nama Fakultas
                                            <ArrowUpDown
                                                class="h-4 w-4 transition-transform"
                                                :class="getSortIcon('name')"
                                            />
                                        </button>
                                    </TableHead>
                                    <TableHead class="text-center"> Program Studi </TableHead>
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
                                    <TableHead class="text-center w-20"> Aksi </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-if="faculties.data.length === 0">
                                    <TableCell colspan="5" class="text-center py-8 text-gray-500">
                                        <Building2 class="h-12 w-12 mx-auto mb-4 opacity-50" />
                                        <p>Tidak ada fakultas ditemukan</p>
                                    </TableCell>
                                </TableRow>
                                <TableRow
                                    v-for="(faculty, index) in faculties.data"
                                    :key="faculty.id"
                                    class="hover:bg-muted/50"
                                >
                                    <TableCell class="font-medium">
                                        {{ faculties.from + index }}
                                    </TableCell>
                                    <TableCell>
                                        <div class="font-medium">
                                            {{ faculty.name }}
                                        </div>
                                    </TableCell>
                                    <TableCell class="text-center">
                                        <Badge variant="outline">
                                            {{ faculty.study_programs_count }} program studi
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        {{ new Date(faculty.created_at).toLocaleDateString() }}
                                    </TableCell>
                                    <TableCell>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    :disabled="isDeleting === faculty.id"
                                                >
                                                    <MoreVertical class="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem
                                                    @click="
                                                        router.visit(
                                                            route(
                                                                'admin.faculties.show',
                                                                faculty.id
                                                            )
                                                        )
                                                    "
                                                >
                                                    <Eye class="h-4 w-4 mr-2" />
                                                    Lihat
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    @click="
                                                        router.visit(
                                                            route(
                                                                'admin.faculties.edit',
                                                                faculty.id
                                                            )
                                                        )
                                                    "
                                                >
                                                    <Edit class="h-4 w-4 mr-2" />
                                                    Edit
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    class="text-red-600"
                                                    :disabled="faculty.study_programs_count > 0"
                                                    @click="deleteFaculty(faculty)"
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
                        v-if="faculties.last_page > 1"
                        class="flex items-center justify-between px-6 py-4 border-t"
                    >
                        <div class="text-sm text-gray-500">
                            Menampilkan {{ faculties.from }} hingga {{ faculties.to }} dari
                            {{ faculties.total }} hasil
                        </div>
                        <div class="flex items-center space-x-2">
                            <!-- Previous Button -->
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="!faculties.links[0].url"
                                @click="goToPage(faculties.links[0].url)"
                            >
                                <ChevronLeft class="h-4 w-4" />
                                Halaman Sebelumnya
                            </Button>

                            <!-- Page Numbers -->
                            <template
                                v-for="link in faculties.links.slice(1, -1)"
                                :key="link.label"
                            >
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="!link.url"
                                    :class="{
                                        'bg-blue-600 text-white hover:bg-blue-700': link.active,
                                        'hover:bg-gray-100': !link.active,
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
                                :disabled="!faculties.links[faculties.links.length - 1].url"
                                @click="goToPage(faculties.links[faculties.links.length - 1].url)"
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
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <AlertTriangle class="h-5 w-5 text-destructive" />
                        Konfirmasi Hapus
                    </DialogTitle>
                    <DialogDescription>
                        Apakah Anda yakin ingin menghapus fakultas ini? Tindakan ini tidak dapat
                        dibatalkan.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="facultyToDelete" class="py-4">
                    <div class="p-4 bg-muted rounded-lg">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center"
                            >
                                <UserCheck class="h-6 w-6 text-blue-600" />
                            </div>
                            <div class="flex-1">
                                <h4 class="font-semibold text-lg">
                                    {{ facultyToDelete.name }}
                                </h4>
                                <p class="text-sm text-muted-foreground">
                                    ID: {{ facultyToDelete.id }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="cancelDelete"> Batal </Button>
                    <Button variant="destructive" @click="confirmDelete"> Hapus Fakultas </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>
