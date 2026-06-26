<script setup lang="ts">
import { route } from 'ziggy-js'
import { ref, watch, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table'
import {
    Ellipsis,
    Plus,
    Eye,
    Edit,
    Copy,
    Trash2,
    Search,
    Filter,
    FileText,
    X,
    ArrowUpDown,
    CheckCircle,
    XCircle,
    ChevronsUpDown,
    Check,
} from 'lucide-vue-next'
import { useToast } from '@/Components/ui/toast/use-toast'
import { debounce } from 'lodash'
import { cn } from '@/lib/utils'
import Checkbox from '@/Components/ui/checkbox/UiCheckbox.vue'

interface FormType {
    id: number
    name: string
}

interface FormField {
    id: number
    label: string
    is_required: boolean
}

interface Form {
    id: number
    form_type_id: number
    title: string
    description: string
    is_active: boolean
    form_type: FormType
    form_fields: FormField[]
    created_at: string
    updated_at: string
}

interface PaginationLink {
    url: string | undefined
    label: string
    active: boolean
}

interface Props {
    forms: {
        data: Form[]
        links: PaginationLink[]
        current_page: number
        last_page: number
        per_page: number
        total: number
        from: number
        to: number
    }
    formTypes: FormType[]
    filters: {
        search?: string
        per_page?: number
        sort_by?: string
        sort_order?: string
        form_type?: string
        is_active?: string
    }
}

const props = defineProps<Props>()
const { toast } = useToast()

const search = ref(props.filters.search || '')
// const perPage = ref(props.filters.per_page || 10)
const sortBy = ref(props.filters.sort_by || 'created_at')
const sortOrder = ref(props.filters.sort_order || 'desc')
const formTypeFilter = ref(props.filters.form_type || 'all')
const isActiveFilter = ref(props.filters.is_active || 'all')

const openFormType = ref(false)
const openStatus = ref(false)

const isDeleting = ref<number | null>(null)
const selectedItems = ref<number[]>([])
const allFormIds = computed(() => props.forms.data.map((f) => f.id))
const isPartiallySelected = computed(
    () => selectedItems.value.length > 0 && selectedItems.value.length < allFormIds.value.length
)
const isAllSelected = computed(
    () => allFormIds.value.length > 0 && selectedItems.value.length === allFormIds.value.length
)

const selectAll = computed({
    get() {
        return isAllSelected.value
    },
    set(value: boolean) {
        selectedItems.value = value ? allFormIds.value : []
    },
})

const statusOptions = [
    { value: 'all', label: 'Semua Status' },
    { value: 'active', label: 'Aktif' },
    { value: 'inactive', label: 'Nonaktif' },
]

const selectedFormTypeLabel = computed(() => {
    if (formTypeFilter.value === 'all') return 'Semua Tipe Formulir'
    const type = props.formTypes.find((t) => t.id.toString() === formTypeFilter.value)
    return type?.name || 'Pilih tipe...'
})

const selectedStatusLabel = computed(() => {
    const status = statusOptions.find((s) => s.value === isActiveFilter.value)
    return status?.label || 'Pilih status...'
})

const debouncedSearch = debounce((value: string) => {
    updateFilters({ search: value })
}, 300)

watch(search, (newValue) => {
    debouncedSearch(newValue)
})

watch(formTypeFilter, (newValue) => {
    updateFilters({ form_type: newValue })
})

watch(isActiveFilter, (newValue) => {
    updateFilters({ is_active: newValue })
})

watch(selectAll, (newValue) => {
    if (newValue) {
        selectedItems.value = props.forms.data.map((item) => item.id)
    } else {
        selectedItems.value = []
    }
})

const updateFilters = (newFilters: Record<string, string | undefined>) => {
    router.get(
        route('admin.forms.index'),
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

const sortTable = (column: string) => {
    const newSortOrder = sortBy.value === column && sortOrder.value === 'asc' ? 'desc' : 'asc'
    sortBy.value = column
    sortOrder.value = newSortOrder

    updateFilters({
        sort_by: column,
        sort_order: newSortOrder,
    })
}

const clearAllFilters = () => {
    search.value = ''
    formTypeFilter.value = 'all'
    isActiveFilter.value = 'all'
    updateFilters({
        search: '',
        form_type: 'all',
        is_active: 'all',
    })
}

const activeFiltersCount = computed(() => {
    let count = 0
    if (search.value) count++
    if (formTypeFilter.value !== 'all') count++
    if (isActiveFilter.value !== 'all') count++
    return count
})

const deleteForm = (form: Form) => {
    if (confirm(`Apakah Anda yakin ingin menghapus "${form.title}"?`)) {
        isDeleting.value = form.id
        router.delete(route('admin.forms.destroy', form.id), {
            onSuccess: () => {
                toast({
                    title: 'Sukses',
                    description: 'Formulir berhasil dihapus!',
                })
                isDeleting.value = null
            },
            onError: () => {
                isDeleting.value = null
            },
        })
    }
}

const duplicateForm = (form: Form) => {
    router.post(
        route('admin.forms.duplicate', form.id),
        {},
        {
            onSuccess: () => {
                toast({
                    title: 'Sukses',
                    description: 'Formulir berhasil diduplikasi!',
                })
            },
        }
    )
}

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    })
}

const bulkDelete = () => {
    if (selectedItems.value.length === 0) return

    if (confirm(`Apakah Anda yakin ingin menghapus ${selectedItems.value.length} item terpilih?`)) {
        router.post(
            route('admin.forms.bulk-delete'),
            { ids: selectedItems.value },
            {
                onSuccess: () => {
                    toast({
                        title: 'Sukses',
                        description: `${selectedItems.value.length} formulir berhasil dihapus!`,
                    })
                    selectedItems.value = []
                    selectAll.value = false
                },
            }
        )
    }
}

const getSortIcon = (column: string) => {
    if (sortBy.value !== column) return 'text-gray-400'
    return sortOrder.value === 'asc' ? 'text-blue-600 rotate-0' : 'text-blue-600 rotate-180'
}

const toggleItemSelection = (id: number, checked?: boolean | 'indeterminate') => {
    const isSelected = selectedItems.value.includes(id)

    const shouldSelect =
        checked === 'indeterminate' ? true : typeof checked === 'boolean' ? checked : !isSelected

    if (shouldSelect) {
        if (!isSelected) selectedItems.value.push(id)
    } else {
        selectedItems.value = selectedItems.value.filter((itemId) => itemId !== id)
    }
}
</script>

<template>
    <Head title="Formulir" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Manajemen Formulir
                    </h2>
                </div>
                <Link :href="route('admin.forms.create')">
                    <Button>
                        <Plus class="h-4 w-4 mr-2" />
                        Buat Formulir
                    </Button>
                </Link>
            </div>
        </template>

        <div class="max-w-7xl mx-auto space-y-6">
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
                            v-if="activeFiltersCount > 0"
                            variant="ghost"
                            size="sm"
                            class="flex items-center"
                            @click="clearAllFilters"
                        >
                            <X class="h-4 w-4 mr-2" />
                            Bersihkan Semua Filter
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-col lg:flex-row gap-4">
                        <div class="flex-1">
                            <div class="relative">
                                <Search
                                    class="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground h-4 w-4"
                                />
                                <Input
                                    v-model="search"
                                    placeholder="Cari formulir berdasarkan judul, deskripsi, atau tipe..."
                                    class="pl-10"
                                />
                            </div>
                        </div>

                        <div class="w-full lg:w-64">
                            <Popover v-model:open="openFormType">
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        role="combobox"
                                        :aria-expanded="openFormType"
                                        class="w-full justify-between"
                                    >
                                        <span class="truncate">{{ selectedFormTypeLabel }}</span>
                                        <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-[250px] p-0">
                                    <Command>
                                        <div
                                            class="flex items-center border-b px-3"
                                            cmdk-input-wrapper
                                        >
                                            <Search class="mr-2 h-4 w-4 shrink-0 opacity-50" />
                                            <CommandInput
                                                placeholder="Cari tipe formulir..."
                                                class="flex h-11 w-full rounded-md bg-transparent py-3 text-sm outline-hidden placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50 border-0 ring-0 focus:ring-0 focus:outline-hidden"
                                            />
                                        </div>
                                        <CommandList>
                                            <CommandEmpty>
                                                Tidak ada tipe formulir ditemukan.
                                            </CommandEmpty>
                                            <CommandGroup>
                                                <CommandItem
                                                    value="all"
                                                    @select="
                                                        () => {
                                                            formTypeFilter = 'all'
                                                            openFormType = false
                                                        }
                                                    "
                                                >
                                                    <Check
                                                        :class="
                                                            cn(
                                                                'mr-2 h-4 w-4',
                                                                formTypeFilter === 'all'
                                                                    ? 'opacity-100'
                                                                    : 'opacity-0'
                                                            )
                                                        "
                                                    />
                                                    Semua Tipe Formulir
                                                </CommandItem>
                                                <CommandItem
                                                    v-for="type in formTypes"
                                                    :key="type.id"
                                                    :value="type.id.toString()"
                                                    @select="
                                                        () => {
                                                            formTypeFilter = type.id.toString()
                                                            openFormType = false
                                                        }
                                                    "
                                                >
                                                    <Check
                                                        :class="
                                                            cn(
                                                                'mr-2 h-4 w-4',
                                                                formTypeFilter ===
                                                                    type.id.toString()
                                                                    ? 'opacity-100'
                                                                    : 'opacity-0'
                                                            )
                                                        "
                                                    />
                                                    {{ type.name }}
                                                </CommandItem>
                                            </CommandGroup>
                                        </CommandList>
                                    </Command>
                                </PopoverContent>
                            </Popover>
                        </div>

                        <div class="w-full lg:w-48">
                            <Popover v-model:open="openStatus">
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        role="combobox"
                                        :aria-expanded="openStatus"
                                        class="w-full justify-between"
                                    >
                                        <span class="truncate">{{ selectedStatusLabel }}</span>
                                        <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-[200px] p-0">
                                    <Command>
                                        <CommandList>
                                            <CommandGroup>
                                                <CommandItem
                                                    v-for="status in statusOptions"
                                                    :key="status.value"
                                                    :value="status.value"
                                                    @select="
                                                        () => {
                                                            isActiveFilter = status.value
                                                            openStatus = false
                                                        }
                                                    "
                                                >
                                                    <Check
                                                        :class="
                                                            cn(
                                                                'mr-2 h-4 w-4',
                                                                isActiveFilter === status.value
                                                                    ? 'opacity-100'
                                                                    : 'opacity-0'
                                                            )
                                                        "
                                                    />
                                                    <div class="flex items-center gap-2">
                                                        <CheckCircle
                                                            v-if="status.value === 'active'"
                                                            class="h-4 w-4 text-green-600"
                                                        />
                                                        <XCircle
                                                            v-else-if="status.value === 'inactive'"
                                                            class="h-4 w-4 text-red-600"
                                                        />
                                                        {{ status.label }}
                                                    </div>
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

            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2">
                            <FileText class="h-5 w-5" />
                            Formulir
                            <Badge variant="secondary"> {{ props.forms.total }} total </Badge>
                        </CardTitle>
                        <Button
                            v-if="selectedItems.length > 0"
                            variant="destructive"
                            size="sm"
                            @click="bulkDelete"
                        >
                            <Trash2 class="h-4 w-4 mr-2" />
                            Hapus Terpilih ({{ selectedItems.length }})
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="rounded-md border ml-4 mr-4 mb-4 overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="w-12">
                                        <Checkbox
                                            v-model="selectAll"
                                            :indeterminate="isPartiallySelected"
                                        />
                                    </TableHead>
                                    <TableHead>
                                        <button
                                            class="flex items-center gap-1 hover:text-blue-600"
                                            @click="sortTable('title')"
                                        >
                                            Judul Formulir
                                            <ArrowUpDown
                                                class="h-4 w-4 transition-transform"
                                                :class="getSortIcon('title')"
                                            />
                                        </button>
                                    </TableHead>
                                    <TableHead>Deskripsi</TableHead>
                                    <TableHead>
                                        <button
                                            class="flex items-center gap-1 hover:text-blue-600"
                                            @click="sortTable('form_type')"
                                        >
                                            Tipe
                                            <ArrowUpDown
                                                class="h-4 w-4 transition-transform"
                                                :class="getSortIcon('form_type')"
                                            />
                                        </button>
                                    </TableHead>
                                    <TableHead class="text-center"> Status </TableHead>
                                    <TableHead>
                                        <button
                                            class="flex items-center gap-1 hover:text-blue-600"
                                            @click="sortTable('created_at')"
                                        >
                                            Dibuat Pada
                                            <ArrowUpDown
                                                class="h-4 w-4 transition-transform"
                                                :class="getSortIcon('created_at')"
                                            />
                                        </button>
                                    </TableHead>
                                    <TableHead class="text-right"> Aksi </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-if="props.forms.data.length === 0">
                                    <TableCell colspan="7" class="text-center py-8 text-gray-500">
                                        <FileText class="h-12 w-12 mx-auto mb-4 opacity-50" />
                                        <p class="font-medium">Tidak ada formulir ditemukan</p>
                                        <p class="text-sm mt-1">
                                            {{
                                                activeFiltersCount > 0
                                                    ? 'Coba sesuaikan filter Anda'
                                                    : 'Buat formulir pertama Anda untuk memulai'
                                            }}
                                        </p>
                                    </TableCell>
                                </TableRow>

                                <TableRow
                                    v-for="form in props.forms.data"
                                    :key="form.id"
                                    class="hover:bg-muted/50"
                                >
                                    <TableCell class="font-medium">
                                        <Checkbox
                                            :model-value="selectedItems.includes(form.id)"
                                            @update:model-value="
                                                (val) => toggleItemSelection(form.id, val)
                                            "
                                        />
                                    </TableCell>
                                    <TableCell>
                                        <div class="font-medium">
                                            {{ form.title }}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div class="max-w-md truncate text-sm text-gray-600">
                                            {{ form.description || '-' }}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant="outline"
                                            class="bg-blue-50 text-blue-700 border-blue-200"
                                        >
                                            {{ form.form_type.name }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="text-center">
                                        <Badge
                                            :variant="form.is_active ? 'default' : 'destructive'"
                                            class="flex items-center gap-1 w-fit mx-auto"
                                        >
                                            <CheckCircle v-if="form.is_active" class="h-3 w-3" />
                                            <XCircle v-else class="h-3 w-3" />
                                            {{ form.is_active ? 'Active' : 'Inactive' }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        {{ formatDate(form.created_at) }}
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    :disabled="isDeleting === form.id"
                                                >
                                                    <Ellipsis class="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem as-child>
                                                    <Link
                                                        :href="route('admin.forms.show', form.id)"
                                                    >
                                                        <Eye class="h-4 w-4 mr-2" />
                                                        Lihat
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem as-child>
                                                    <Link
                                                        :href="route('admin.forms.edit', form.id)"
                                                    >
                                                        <Edit class="h-4 w-4 mr-2" />
                                                        Edit
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem @click="duplicateForm(form)">
                                                    <Copy class="h-4 w-4 mr-2" />
                                                    Duplikat
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    class="text-red-600"
                                                    @click="deleteForm(form)"
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
                </CardContent>
            </Card>

            <div v-if="props.forms.last_page > 1" class="flex justify-center">
                <div class="flex items-center gap-2">
                    <template v-for="link in props.forms.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            :class="[
                                'px-3 py-2 text-sm rounded-md',
                                link.active
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-background border hover:bg-muted',
                            ]"
                        >
                            {{ link.label }}
                        </Link>
                        <span
                            v-else
                            :class="[
                                'px-3 py-2 text-sm rounded-md text-muted-foreground',
                                'bg-muted cursor-not-allowed',
                            ]"
                        >
                            {{ link.label }}
                        </span>
                    </template>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
