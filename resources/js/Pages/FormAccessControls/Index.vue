<!-- resources\js\Pages\FormAccessControls\Index.vue -->
<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Badge } from '@/Components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Checkbox } from '@/Components/ui/checkbox'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table'
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/Components/ui/command'
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu'
import {
    Plus,
    Search,
    Eye,
    Edit,
    Trash2,
    MoreHorizontal,
    Filter,
    FileText,
    Users,
    X,
    ChevronDown,
    ChevronUp,
    ChevronsUpDown,
    Check,
    CornerDownRight,
} from 'lucide-vue-next'
import { useToast } from '@/Components/ui/toast/use-toast'
import { debounce } from 'lodash'
import { cn } from '@/lib/utils'

interface Role {
    id: number
    name: string
}

interface Faculty {
    id: number
    name: string
    study_programs: StudyProgram[]
}

interface StudyProgram {
    id: number
    name: string
    faculty: Faculty
}

interface Form {
    id: number
    title: string
}

interface FormAccessControl {
    id: number
    form?: Form
    role: Role
    study_program: StudyProgram
    created_at: string
    updated_at: string
}

interface GroupAccessControl {
    form_id: number
    jumlah_access_controls: number
    form: Form
    controls: FormAccessControl[]
}

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

interface PaginatedData {
    data: GroupAccessControl[]
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number
    to: number
    links: PaginationLink[]
}

interface Filters {
    [key: string]: string | number | boolean | null | undefined
    form_id?: string
    role_id?: string
    faculty_id?: string
    study_program_id?: string
    search?: string
}

interface Props {
    groupAccessControls: PaginatedData
    forms: Form[]
    roles: Role[]
    faculties: Faculty[]
    filters: Filters
}

const props = defineProps<Props>()
const { toast } = useToast()

const searchQuery = ref(props.filters.search || '')
const selectedFormId = ref(props.filters.form_id || 'all')
const selectedRoleId = ref(props.filters.role_id || 'all')
const selectedFacultyId = ref(props.filters.faculty_id || 'all')
const selectedStudyProgramId = ref(props.filters.study_program_id || 'all')

const openForm = ref(false)
const openRole = ref(false)
const openFaculty = ref(false)
const openStudyProgram = ref(false)

const selectedItems = ref<number[]>([])
const selectAll = computed({
    get() {
        return isAllSelected.value
    },
    set(value: boolean) {
        const allIds = props.groupAccessControls.data.flatMap((group) =>
            group.controls.map((c) => c.id)
        )
        selectedItems.value = value ? allIds : []
    },
})
const openGroups = ref<number[]>([])

const selectedFormLabel = computed(() => {
    if (selectedFormId.value === 'all') return 'Semua Formulir'
    const form = props.forms.find((f) => f.id.toString() === selectedFormId.value)
    return form?.title || 'Pilih formulir...'
})

const selectedRoleLabel = computed(() => {
    if (selectedRoleId.value === 'all') return 'Semua Role'
    const role = props.roles.find((r) => r.id.toString() === selectedRoleId.value)
    return role?.name || 'Pilih role...'
})

const selectedFacultyLabel = computed(() => {
    if (selectedFacultyId.value === 'all') return 'Semua Fakultas'
    const faculty = props.faculties.find((f) => f.id.toString() === selectedFacultyId.value)
    return faculty?.name || 'Pilih fakultas...'
})

const selectedStudyProgramLabel = computed(() => {
    if (selectedStudyProgramId.value === 'all') return 'Semua Program Studi'
    const studyProgram = studyPrograms.value.find(
        (sp) => sp.id.toString() === selectedStudyProgramId.value
    )
    return studyProgram?.name || 'Pilih program studi...'
})

const studyPrograms = computed(() => {
    if (!selectedFacultyId.value || selectedFacultyId.value === 'all') return []
    const faculty = props.faculties.find((f) => f.id.toString() === selectedFacultyId.value)
    return faculty?.study_programs || []
})

// Watch for faculty change to reset study program selection
watch(selectedFacultyId, () => {
    selectedStudyProgramId.value = 'all'
})

const hasActiveFilters = computed(() => {
    return (
        selectedFormId.value !== 'all' ||
        selectedRoleId.value !== 'all' ||
        selectedFacultyId.value !== 'all' ||
        selectedStudyProgramId.value !== 'all' ||
        searchQuery.value !== ''
    )
})

const activeFiltersCount = computed(() => {
    let count = 0
    if (searchQuery.value) count++
    if (selectedFormId.value !== 'all') count++
    if (selectedRoleId.value !== 'all') count++
    if (selectedFacultyId.value !== 'all') count++
    if (selectedStudyProgramId.value !== 'all') count++
    return count
})

// Debounced search
const debouncedSearch = debounce(() => {
    applyFilters()
}, 300)

watch(searchQuery, () => {
    debouncedSearch()
})

// Watch for filter changes
watch([selectedFormId, selectedRoleId, selectedFacultyId, selectedStudyProgramId], () => {
    applyFilters()
})

const applyFilters = () => {
    const params: Filters = {}

    if (searchQuery.value) params.search = searchQuery.value
    if (selectedFormId.value !== 'all') params.form_id = selectedFormId.value
    if (selectedRoleId.value !== 'all') params.role_id = selectedRoleId.value
    if (selectedFacultyId.value !== 'all') params.faculty_id = selectedFacultyId.value
    if (selectedStudyProgramId.value !== 'all')
        params.study_program_id = selectedStudyProgramId.value

    router.get(route('admin.form-access-controls.index'), params, {
        preserveState: true,
        replace: true,
    })
}

const clearFilters = () => {
    searchQuery.value = ''
    selectedFormId.value = 'all'
    selectedRoleId.value = 'all'
    selectedFacultyId.value = 'all'
    selectedStudyProgramId.value = 'all'

    router.get(
        route('admin.form-access-controls.index'),
        {},
        {
            preserveState: true,
            replace: true,
        }
    )
}

const toggleGroup = (formId: number) => {
    const index = openGroups.value.indexOf(formId)
    if (index > -1) {
        openGroups.value.splice(index, 1)
    } else {
        openGroups.value.push(formId)
    }
}

const isGroupOpen = (formId: number) => {
    return openGroups.value.includes(formId)
}

const toggleAllGroups = () => {
    if (openGroups.value.length === props.groupAccessControls.data.length) {
        openGroups.value = []
    } else {
        openGroups.value = props.groupAccessControls.data.map((group) => group.form_id)
    }
}

const toggleGroupSelection = (controls: FormAccessControl[]) => {
    const controlIds = controls.map((c) => c.id)
    const allSelected = controlIds.every((id) => selectedItems.value.includes(id))

    if (allSelected) {
        selectedItems.value = selectedItems.value.filter((id) => !controlIds.includes(id))
    } else {
        selectedItems.value = [...new Set([...selectedItems.value, ...controlIds])]
    }
}

const toggleItemSelection = (id: number, checked?: boolean) => {
    if (checked === true) {
        if (!selectedItems.value.includes(id)) selectedItems.value.push(id)
    } else if (checked === false) {
        selectedItems.value = selectedItems.value.filter((itemId) => itemId !== id)
    } else {
        // toggle biasa
        if (selectedItems.value.includes(id)) {
            selectedItems.value = selectedItems.value.filter((itemId) => itemId !== id)
        } else {
            selectedItems.value.push(id)
        }
    }
}

const isGroupSelected = (controls: FormAccessControl[]) => {
    const controlIds = controls.map((c) => c.id)
    return controlIds.every((id) => selectedItems.value.includes(id))
}

const isGroupPartiallySelected = (controls: FormAccessControl[]) => {
    const controlIds = controls.map((c) => c.id)
    const selectedCount = controlIds.filter((id) => selectedItems.value.includes(id)).length
    return selectedCount > 0 && selectedCount < controlIds.length
}

const isAllSelected = computed(() => {
    const allIds = props.groupAccessControls.data.flatMap((group) =>
        group.controls.map((c) => c.id)
    )
    return allIds.length > 0 && allIds.every((id) => selectedItems.value.includes(id))
})

const isPartiallySelected = computed(() => {
    const allIds = props.groupAccessControls.data.flatMap((group) =>
        group.controls.map((c) => c.id)
    )
    return selectedItems.value.length > 0 && selectedItems.value.length < allIds.length
})

const deleteFormAccessControl = (id: number) => {
    if (confirm('Apakah anda yakin ingin menghapus kontrol akses formulir ini?')) {
        router.delete(route('admin.form-access-controls.destroy', id), {
            onSuccess: () => {
                toast({
                    title: 'Sukses',
                    description: 'Kontrol akses formulir berhasil dihapus.',
                })
            },
            onError: () => {
                toast({
                    title: 'Error',
                    description: 'Gagal menghapus kontrol akses formulir.',
                    variant: 'destructive',
                })
            },
        })
    }
}

const bulkDelete = () => {
    if (selectedItems.value.length === 0) return

    if (
        confirm(
            `Apakah Anda yakin ingin menghapus ${selectedItems.value.length} item yang dipilih?`
        )
    ) {
        router.post(
            route('admin.form-access-controls.bulk-delete'),
            { ids: selectedItems.value },
            {
                onSuccess: () => {
                    selectedItems.value = []
                    toast({
                        title: 'Sukses',
                        description: 'Kontrol akses formulir yang dipilih berhasil dihapus.',
                    })
                },
                onError: () => {
                    toast({
                        title: 'Error',
                        description: 'Gagal menghapus kontrol akses formulir yang dipilih.',
                        variant: 'destructive',
                    })
                },
            }
        )
    }
}

const goToPage = (url: string | null) => {
    if (url) {
        router.visit(url, {
            preserveState: true,
        })
    }
}

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    })
}
</script>

<template>
    <Head title="Kontrol Akses Formulir" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Kontrol Akses Formulir
                    </h2>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('admin.form-access-controls.create')">
                        <Button>
                            <Plus class="h-4 w-4 mr-2" />
                            Buat Kontrol Akses
                        </Button>
                    </Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Search and Filters -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="text-lg flex items-center gap-2">
                            <Filter class="h-5 w-5" />
                            Cari & Filter
                            <Badge v-if="activeFiltersCount > 0" variant="secondary">
                                {{ activeFiltersCount }} aktif
                            </Badge>
                        </CardTitle>
                        <Button
                            v-if="hasActiveFilters"
                            variant="ghost"
                            size="sm"
                            @click="clearFilters"
                        >
                            <X class="h-4 w-4 mr-2" />
                            Bersihkan Semua Filter
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <!-- Search Bar -->
                    <div class="flex items-center gap-2">
                        <div class="flex-1 relative">
                            <Search
                                class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground"
                            />
                            <Input
                                v-model="searchQuery"
                                placeholder="Cari berdasarkan judul formulir, nama role, atau program studi..."
                                class="pl-10"
                            />
                        </div>
                    </div>

                    <!-- filter -->
                    <div class="grid gap-4 md:grid-cols-4">
                        <!-- form filter -->
                        <div>
                            <label class="text-sm font-medium text-gray-700 mb-2 block">
                                Formulir
                            </label>
                            <Popover v-model:open="openForm">
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        role="combobox"
                                        :aria-expanded="openForm"
                                        class="w-full justify-between"
                                    >
                                        <span class="truncate">{{ selectedFormLabel }}</span>
                                        <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-[300px] p-0">
                                    <Command>
                                        <CommandInput
                                            placeholder="Search form..."
                                            class="flex h-11 w-full rounded-md bg-transparent py-3 text-sm outline-hidden placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50 border-0 ring-0 focus:ring-0 focus:outline-hidden"
                                        />
                                        <CommandList>
                                            <CommandEmpty
                                                >Tidak ada formulir ditemukan.</CommandEmpty
                                            >
                                            <CommandGroup>
                                                <CommandItem
                                                    value="all"
                                                    @select="
                                                        () => {
                                                            selectedFormId = 'all'
                                                            openForm = false
                                                        }
                                                    "
                                                >
                                                    <Check
                                                        :class="
                                                            cn(
                                                                'mr-2 h-4 w-4',
                                                                selectedFormId === 'all'
                                                                    ? 'opacity-100'
                                                                    : 'opacity-0'
                                                            )
                                                        "
                                                    />
                                                    Semua Formulir
                                                </CommandItem>
                                                <CommandItem
                                                    v-for="form in props.forms"
                                                    :key="form.id"
                                                    :value="form.id.toString()"
                                                    @select="
                                                        () => {
                                                            selectedFormId = form.id.toString()
                                                            openForm = false
                                                        }
                                                    "
                                                >
                                                    <Check
                                                        :class="
                                                            cn(
                                                                'mr-2 h-4 w-4',
                                                                selectedFormId ===
                                                                    form.id.toString()
                                                                    ? 'opacity-100'
                                                                    : 'opacity-0'
                                                            )
                                                        "
                                                    />
                                                    {{ form.title }}
                                                </CommandItem>
                                            </CommandGroup>
                                        </CommandList>
                                    </Command>
                                </PopoverContent>
                            </Popover>
                        </div>

                        <!-- role filter -->
                        <div>
                            <label class="text-sm font-medium text-gray-700 mb-2 block">
                                Role
                            </label>
                            <Popover v-model:open="openRole">
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        role="combobox"
                                        :aria-expanded="openRole"
                                        class="w-full justify-between"
                                    >
                                        <span class="truncate">{{ selectedRoleLabel }}</span>
                                        <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-[200px] p-0">
                                    <Command>
                                        <CommandInput
                                            placeholder="Cari role..."
                                            class="flex h-11 w-full rounded-md bg-transparent py-3 text-sm outline-hidden placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50 border-0 ring-0 focus:ring-0 focus:outline-hidden"
                                        />
                                        <CommandList>
                                            <CommandEmpty>Tidak ada role ditemukan.</CommandEmpty>
                                            <CommandGroup>
                                                <CommandItem
                                                    value="all"
                                                    @select="
                                                        () => {
                                                            selectedRoleId = 'all'
                                                            openRole = false
                                                        }
                                                    "
                                                >
                                                    <Check
                                                        :class="
                                                            cn(
                                                                'mr-2 h-4 w-4',
                                                                selectedRoleId === 'all'
                                                                    ? 'opacity-100'
                                                                    : 'opacity-0'
                                                            )
                                                        "
                                                    />
                                                    Semua Role
                                                </CommandItem>
                                                <CommandItem
                                                    v-for="role in props.roles"
                                                    :key="role.id"
                                                    :value="role.id.toString()"
                                                    @select="
                                                        () => {
                                                            selectedRoleId = role.id.toString()
                                                            openRole = false
                                                        }
                                                    "
                                                >
                                                    <Check
                                                        :class="
                                                            cn(
                                                                'mr-2 h-4 w-4',
                                                                selectedRoleId ===
                                                                    role.id.toString()
                                                                    ? 'opacity-100'
                                                                    : 'opacity-0'
                                                            )
                                                        "
                                                    />
                                                    {{ role.name }}
                                                </CommandItem>
                                            </CommandGroup>
                                        </CommandList>
                                    </Command>
                                </PopoverContent>
                            </Popover>
                        </div>

                        <!-- fak filter -->
                        <div>
                            <label class="text-sm font-medium text-gray-700 mb-2 block">
                                Fakultas
                            </label>
                            <Popover v-model:open="openFaculty">
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        role="combobox"
                                        :aria-expanded="openFaculty"
                                        class="w-full justify-between"
                                    >
                                        <span class="truncate">{{ selectedFacultyLabel }}</span>
                                        <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-[250px] p-0">
                                    <Command>
                                        <CommandInput
                                            placeholder="Cari fakultas..."
                                            class="flex h-11 w-full rounded-md bg-transparent py-3 text-sm outline-hidden placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50 border-0 ring-0 focus:ring-0 focus:outline-hidden"
                                        />
                                        <CommandList>
                                            <CommandEmpty
                                                >Tidak ada fakultas ditemukan.</CommandEmpty
                                            >
                                            <CommandGroup>
                                                <CommandItem
                                                    value="all"
                                                    @select="
                                                        () => {
                                                            selectedFacultyId = 'all'
                                                            openFaculty = false
                                                        }
                                                    "
                                                >
                                                    <Check
                                                        :class="
                                                            cn(
                                                                'mr-2 h-4 w-4',
                                                                selectedFacultyId === 'all'
                                                                    ? 'opacity-100'
                                                                    : 'opacity-0'
                                                            )
                                                        "
                                                    />
                                                    Semua Fakultas
                                                </CommandItem>
                                                <CommandItem
                                                    v-for="faculty in props.faculties"
                                                    :key="faculty.id"
                                                    :value="faculty.id.toString()"
                                                    @select="
                                                        () => {
                                                            selectedFacultyId =
                                                                faculty.id.toString()
                                                            openFaculty = false
                                                        }
                                                    "
                                                >
                                                    <Check
                                                        :class="
                                                            cn(
                                                                'mr-2 h-4 w-4',
                                                                selectedFacultyId ===
                                                                    faculty.id.toString()
                                                                    ? 'opacity-100'
                                                                    : 'opacity-0'
                                                            )
                                                        "
                                                    />
                                                    {{ faculty.name }}
                                                </CommandItem>
                                            </CommandGroup>
                                        </CommandList>
                                    </Command>
                                </PopoverContent>
                            </Popover>
                        </div>

                        <!-- prodi filter -->
                        <div>
                            <label class="text-sm font-medium text-gray-700 mb-2 block">
                                Program Studi
                            </label>
                            <Popover
                                v-model:open="openStudyProgram"
                                :disabled="!selectedFacultyId || selectedFacultyId === 'all'"
                            >
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        role="combobox"
                                        :aria-expanded="openStudyProgram"
                                        :disabled="
                                            !selectedFacultyId || selectedFacultyId === 'all'
                                        "
                                        class="w-full justify-between"
                                    >
                                        <span class="truncate">{{
                                            selectedStudyProgramLabel
                                        }}</span>
                                        <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-[250px] p-0">
                                    <Command>
                                        <CommandInput
                                            placeholder="Cari program studi..."
                                            class="flex h-11 w-full rounded-md bg-transparent py-3 text-sm outline-hidden placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50 border-0 ring-0 focus:ring-0 focus:outline-hidden"
                                        />
                                        <CommandList>
                                            <CommandEmpty
                                                >Tidak ada program studi ditemukan.</CommandEmpty
                                            >
                                            <CommandGroup>
                                                <CommandItem
                                                    value="all"
                                                    @select="
                                                        () => {
                                                            selectedStudyProgramId = 'all'
                                                            openStudyProgram = false
                                                        }
                                                    "
                                                >
                                                    <Check
                                                        :class="
                                                            cn(
                                                                'mr-2 h-4 w-4',
                                                                selectedStudyProgramId === 'all'
                                                                    ? 'opacity-100'
                                                                    : 'opacity-0'
                                                            )
                                                        "
                                                    />
                                                    Semua Program Studi
                                                </CommandItem>
                                                <CommandItem
                                                    v-for="studyProgram in studyPrograms"
                                                    :key="studyProgram.id"
                                                    :value="studyProgram.id.toString()"
                                                    @select="
                                                        () => {
                                                            selectedStudyProgramId =
                                                                studyProgram.id.toString()
                                                            openStudyProgram = false
                                                        }
                                                    "
                                                >
                                                    <Check
                                                        :class="
                                                            cn(
                                                                'mr-2 h-4 w-4',
                                                                selectedStudyProgramId ===
                                                                    studyProgram.id.toString()
                                                                    ? 'opacity-100'
                                                                    : 'opacity-0'
                                                            )
                                                        "
                                                    />
                                                    {{ studyProgram.name }}
                                                </CommandItem>
                                            </CommandGroup>
                                        </CommandList>
                                    </Command>
                                </PopoverContent>
                            </Popover>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Access Controls Table -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="flex items-center gap-2">
                        <FileText class="h-5 w-5" />
                        Formulir dengan Kontrol Akses
                        <Badge variant="secondary">
                            {{ props.groupAccessControls.total }} formulir
                        </Badge>
                    </CardTitle>
                    <div class="flex flex-row">
                        <Button
                            v-if="selectedItems.length > 0"
                            variant="destructive"
                            size="sm"
                            @click="bulkDelete"
                        >
                            <Trash2 class="h-4 w-4 mr-2" />
                            Hapus Terpilih ({{ selectedItems.length }})
                        </Button>

                        <div
                            v-if="props.groupAccessControls.total > 0"
                            class="text-sm text-muted-foreground"
                        >
                            <Button variant="outline" size="sm" @click="toggleAllGroups">
                                {{
                                    openGroups.length === props.groupAccessControls.data.length
                                        ? 'Tutup Semua'
                                        : 'Perluas Semua'
                                }}
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="p-0">
                    <!-- Empty State -->
                    <div
                        v-if="props.groupAccessControls.data.length === 0"
                        class="text-center py-12"
                    >
                        <Users class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                        <h3 class="text-lg font-medium mb-2">Tidak ada kontrol akses ditemukan</h3>
                        <p class="text-muted-foreground mb-4">
                            {{
                                hasActiveFilters
                                    ? 'Coba sesuaikan kriteria pencarian Anda.'
                                    : 'Mulai dengan membuat kontrol akses pertama Anda.'
                            }}
                        </p>
                        <Link
                            v-if="!hasActiveFilters"
                            :href="route('admin.form-access-controls.create')"
                        >
                            <Button>
                                <Plus class="h-4 w-4 mr-2" />
                                Buat Kontrol Akses Formulir
                            </Button>
                        </Link>
                        <Button v-else variant="outline" @click="clearFilters">
                            <X class="h-4 w-4 mr-2" />
                            Hapus Filter
                        </Button>
                    </div>

                    <!-- Table with Groups -->
                    <div v-else class="rounded-md border ml-4 mr-4 mb-4 overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="w-12">
                                        <Checkbox
                                            v-model="selectAll"
                                            :indeterminate="isPartiallySelected"
                                        />
                                    </TableHead>
                                    <TableHead>Formulir</TableHead>
                                    <TableHead>Role</TableHead>
                                    <TableHead>Program Studi</TableHead>
                                    <TableHead>Fakultas</TableHead>
                                    <TableHead>Dibuat Pada</TableHead>
                                    <TableHead class="text-right"> Aksi </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <template
                                    v-for="group in props.groupAccessControls.data"
                                    :key="group.form_id"
                                >
                                    <!-- Group Header Row -->
                                    <TableRow
                                        class="bg-muted/50 cursor-pointer hover:bg-muted"
                                        @click="toggleGroup(group.form_id)"
                                    >
                                        <TableCell>
                                            <Checkbox
                                                :model-value="isGroupSelected(group.controls)"
                                                :indeterminate="
                                                    isGroupPartiallySelected(group.controls)
                                                "
                                                @click.stop
                                                @update:model-value="
                                                    () => toggleGroupSelection(group.controls)
                                                "
                                            />
                                        </TableCell>
                                        <TableCell colspan="6" class="font-medium">
                                            <div class="flex items-center justify-start">
                                                <div class="flex items-center gap-2">
                                                    <FileText
                                                        class="h-4 w-4 text-muted-foreground"
                                                    />
                                                    {{ group.form.title }}
                                                    <Badge variant="secondary">
                                                        {{ group.jumlah_access_controls }}
                                                        kontrol akses{{
                                                            group.jumlah_access_controls !== 1
                                                                ? 's'
                                                                : ''
                                                        }}
                                                    </Badge>
                                                </div>
                                                <component
                                                    :is="
                                                        isGroupOpen(group.form_id)
                                                            ? ChevronUp
                                                            : ChevronDown
                                                    "
                                                    class="h-4 w-4 text-muted-foreground transition-transform duration-200"
                                                />
                                            </div>
                                        </TableCell>
                                    </TableRow>

                                    <!-- Group Detail Rows -->
                                    <template v-if="isGroupOpen(group.form_id)">
                                        <TableRow
                                            v-for="control in group.controls"
                                            :key="control.id"
                                            class="border-t"
                                        >
                                            <TableCell>
                                                <Checkbox
                                                    :model-value="
                                                        selectedItems.includes(control.id)
                                                    "
                                                    @update:model-value="
                                                        () => toggleItemSelection(control.id)
                                                    "
                                                />
                                            </TableCell>
                                            <TableCell class="pl-12">
                                                <CornerDownRight
                                                    class="h-4 w-4 text-muted-foreground"
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline">
                                                    {{ control.role.name }}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {{ control.study_program.name }}
                                            </TableCell>
                                            <TableCell>
                                                {{ control.study_program.faculty.name }}
                                            </TableCell>
                                            <TableCell>
                                                {{ formatDate(control.created_at) }}
                                            </TableCell>
                                            <TableCell class="text-right">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger as-child>
                                                        <Button variant="ghost" size="sm">
                                                            <MoreHorizontal class="h-4 w-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <Link
                                                            :href="
                                                                route(
                                                                    'admin.form-access-controls.show',
                                                                    control.id
                                                                )
                                                            "
                                                        >
                                                            <DropdownMenuItem>
                                                                <Eye class="h-4 w-4 mr-2" />
                                                                Lihat Detail
                                                            </DropdownMenuItem>
                                                        </Link>
                                                        <Link
                                                            :href="
                                                                route(
                                                                    'admin.form-access-controls.edit',
                                                                    control.id
                                                                )
                                                            "
                                                        >
                                                            <DropdownMenuItem>
                                                                <Edit class="h-4 w-4 mr-2" />
                                                                Edit
                                                            </DropdownMenuItem>
                                                        </Link>
                                                        <DropdownMenuItem
                                                            class="text-destructive cursor-pointer"
                                                            @click="
                                                                deleteFormAccessControl(control.id)
                                                            "
                                                        >
                                                            <Trash2 class="h-4 w-4 mr-2" />
                                                            Hapus
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </TableRow>
                                    </template>
                                </template>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>

            <!-- Pagination -->
            <div v-if="props.groupAccessControls.last_page > 1" class="flex justify-center mt-4">
                <div class="flex items-center gap-2">
                    <template v-for="link in props.groupAccessControls.links" :key="link.label">
                        <Button
                            v-if="link.url"
                            variant="outline"
                            size="sm"
                            :class="{
                                'bg-primary text-primary-foreground': link.active,
                                'hover:bg-muted': !link.active,
                            }"
                            @click="goToPage(link.url)"
                            v-html="link.label"
                        />
                        <span
                            v-else
                            :class="[
                                'px-3 py-2 text-sm rounded-md text-muted-foreground',
                                'bg-muted cursor-not-allowed',
                            ]"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
