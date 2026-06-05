<script setup lang="ts">
import { route } from 'ziggy-js'
import { computed, onMounted, ref, shallowRef } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Button } from '../../Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select'

import { Textarea } from '@/Components/ui/textarea'
import { ArrowLeft, X } from 'lucide-vue-next'
import {
    today,
    DateFormatter,
    getLocalTimeZone,
    parseDate,
    CalendarDate,
} from '@internationalized/date'
import { Calendar as CalendarIcon } from 'lucide-vue-next'
import { cn } from '@/lib/utils'
import { Calendar } from '@/Components/ui/calendar'
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover'
import { useToast } from '@/Components/ui/toast/use-toast'
const { toast } = useToast()

interface AnnouncementFile {
    id: number
    announcement_id: number
    file_name: string
    file_path: string
    mime_type: string
    file_size: number
}

interface ExistingAnnouncementDetail {
    id: number
    title: string
    content: string
    type: string
    expired_at: string | undefined
    announcement_files: AnnouncementFile[]
}

interface AnnouncementForm {
    title: string
    content: string
    type: string
    expired_at: string | null
    expired_time?: string
    files: File[] | null
    deleted_files?: number[]
    [key: string]: any
}

interface Props {
    announcement: ExistingAnnouncementDetail
}

const fileInputRef = ref<HTMLInputElement | null>(null)
const existingFiles = ref<AnnouncementFile[]>([])
const newFiles = ref<File[]>([])
const clientErrors = ref<{ files?: string }>({})
const { announcement } = defineProps<Props>()
const df = new DateFormatter('en-US', { dateStyle: 'long' })
const tomorrow = today(getLocalTimeZone()).add({ days: 1 })
const expiredAt = shallowRef<CalendarDate | undefined>(undefined)
const form = useForm<AnnouncementForm>({
    title: '',
    content: '',
    type: 'public',
    expired_at: null,
    expired_time: '',
    files: null,
    deleted_files: [],
})

const errors = computed<Record<string, string>>(() => form.errors || {})
const hasFiles = computed(() => existingFiles.value.length > 0 || newFiles.value.length > 0)
onMounted(() => {
    form.title = announcement.title
    form.content = announcement.content
    form.type = announcement.type

    existingFiles.value = [...announcement.announcement_files]

    if (announcement.expired_at) {
        const dateOnly = announcement.expired_at.split('T')[0]
        expiredAt.value = parseDate(dateOnly)

        if (announcement.expired_at.includes('T')) {
            form.expired_time = announcement.expired_at.split('T')[1].substring(0, 5)
        }
    }
})

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement
    if (!target.files?.length) return
    newFiles.value.push(...Array.from(target.files))

    // hapus error validasi client
    if (clientErrors.value.files) delete clientErrors.value.files
    if (fileInputRef.value) fileInputRef.value.value = ''
}

const deleteFile = (fileId: number) => {
    if (!form.deleted_files) form.deleted_files = []
    form.deleted_files.push(fileId)
    existingFiles.value = existingFiles.value.filter((f) => f.id !== fileId)
}

const removeNewFile = (index: number) => newFiles.value.splice(index, 1)

const validateFiles = () => {
    delete clientErrors.value.files
    return true
}

const submit = () => {
    clientErrors.value = {}
    if (!validateFiles()) return

    form.files = newFiles.value
    form.transform((data) => {
        let expired = null

        if (expiredAt.value) {
            const date = expiredAt.value.toDate(getLocalTimeZone())

            const yyyy = date.getFullYear()
            const mm = String(date.getMonth() + 1).padStart(2, '0')
            const dd = String(date.getDate()).padStart(2, '0')

            expired = form.expired_time
                ? `${yyyy}-${mm}-${dd}T${form.expired_time}:00`
                : `${yyyy}-${mm}-${dd}`
        }

        return {
            ...data,
            expired_at: expired,
            _method: 'put',
        }
    }).post(route('admin.announcements.update', announcement.id), {
        forceFormData: true,
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            newFiles.value = []
            form.files = null
            clientErrors.value = {}

            if (fileInputRef.value) fileInputRef.value.value = ''

            toast({
                title: 'Sukses',
                description: 'Pengumuman berhasil diperbarui!',
            })
        },
        onError: (errors) => {
            console.error('Validation errors:', errors)
            toast({
                title: 'Error',
                description:
                    'Gagal memperbarui pengumuman. Silakan periksa input Anda dan coba lagi.',
                variant: 'destructive',
            })
        },
    })
}
</script>

<template>
    <Head title="Edit Announcement" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Button
                    variant="ghost"
                    class="p-0 mr-2"
                    size="sm"
                    @click="$inertia.visit(route('admin.announcements.index'))"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Kembali
                </Button>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Edit Pengumuman</h2>
            </div>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            <form class="space-y-6" @submit.prevent="submit">
                <!-- Basic Info -->
                <Card>
                    <CardHeader>
                        <CardTitle>Informasi Pengumuman</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <!-- Title -->
                        <div>
                            <Label for="title">Judul *</Label>
                            <Input
                                id="title"
                                v-model="form.title"
                                placeholder="Masukkan judul pengumuman"
                                :class="errors.title ? 'border-destructive' : ''"
                            />
                            <p v-if="errors.title" class="text-sm text-destructive mt-1">
                                {{ errors.title }}
                            </p>
                        </div>

                        <!-- Type -->
                        <div>
                            <Label for="type">Tipe *</Label>
                            <Select v-model="form.type">
                                <SelectTrigger id="type">
                                    <SelectValue placeholder="Pilih tipe" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="public"> Public </SelectItem>
                                    <SelectItem value="private"> Privat </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="errors.type" class="text-sm text-destructive mt-1">
                                {{ errors.type }}
                            </p>
                        </div>

                        <!-- Expired At -->
                        <div class="flex flex-row w-full gap-2">
                            <div class="flex w-full flex-col gap-1">
                                <Label for="expired_at">Tanggal Kadaluarsa (opsional)</Label>
                                <Popover>
                                    <PopoverTrigger as-child>
                                        <Button
                                            variant="outline"
                                            :class="
                                                cn(
                                                    'w-full justify-start text-left font-normal',
                                                    !expiredAt && 'text-muted-foreground'
                                                )
                                            "
                                        >
                                            <CalendarIcon class="mr-2 h-4 w-4" />
                                            {{
                                                expiredAt
                                                    ? df.format(
                                                          expiredAt.toDate(getLocalTimeZone())
                                                      )
                                                    : 'Pilih Tanggal'
                                            }}
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent class="w-auto p-0">
                                        <Calendar
                                            v-model="expiredAt"
                                            initial-focus
                                            :min-value="tomorrow"
                                        />
                                    </PopoverContent>
                                </Popover>
                                <p v-if="errors.expired_at" class="text-sm text-destructive mt-1">
                                    {{ errors.expired_at }}
                                </p>
                            </div>
                            <div class="flex flex-col gap-1 w-full">
                                <Label for="expired_time">Waktu Kadaluarsa (opsional)</Label>
                                <Input
                                    id="expired_time"
                                    v-model="form.expired_time"
                                    type="time"
                                    :class="errors.expired_time ? 'border-destructive' : ''"
                                />
                                <p v-if="errors.expired_time" class="text-sm text-destructive mt-1">
                                    {{ errors.expired_time }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Content -->
                <Card>
                    <CardHeader>
                        <CardTitle>Isi Pengumuman</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Label for="content">Isi *</Label>
                        <Textarea
                            id="content"
                            v-model="form.content"
                            placeholder="Masukkan isi pengumuman..."
                            :class="errors.content ? 'border-destructive' : ''"
                            rows="5"
                        />
                        <p v-if="errors.content" class="text-sm text-destructive mt-1">
                            {{ errors.content }}
                        </p>
                    </CardContent>
                </Card>

                <!-- Current Attachments -->
                <Card>
                    <CardHeader>
                        <CardTitle>Lampiran Saat Ini</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div v-if="existingFiles.length > 0 || newFiles.length > 0">
                            <ul class="space-y-2">
                                <!-- Existing Files -->
                                <li
                                    v-for="file in existingFiles"
                                    :key="`existing-${file.id}`"
                                    class="flex items-center justify-between p-3 bg-gray-50 rounded-md"
                                >
                                    <div class="flex items-center gap-2">
                                        <a
                                            :href="`/storage/${file.file_path}`"
                                            target="_blank"
                                            class="text-blue-600 underline hover:text-blue-800 max-w-xl truncate"
                                        >
                                            {{ file.file_name }}
                                        </a>
                                        <span class="text-xs text-gray-500"
                                            >({{ file.mime_type }},
                                            {{ (file.file_size / 1024).toFixed(1) }}
                                            KB)</span
                                        >
                                        <span
                                            class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded"
                                        >
                                            Saat Ini
                                        </span>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                        class="ml-2"
                                        @click="deleteFile(file.id)"
                                    >
                                        <X class="h-4 w-4" />
                                        Hapus
                                    </Button>
                                </li>

                                <!-- New Files -->
                                <li
                                    v-for="(file, index) in newFiles"
                                    :key="`new-${index}`"
                                    class="flex items-center justify-between p-3 bg-green-50 rounded-md"
                                >
                                    <div class="flex items-center gap-2">
                                        <span class="max-w-lg truncate">{{ file.name }}</span>
                                        <span class="text-xs text-gray-500">
                                            ({{ file.type }},
                                            {{ (file.size / 1024).toFixed(1) }}
                                            KB)
                                        </span>
                                        <span
                                            class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded"
                                        >
                                            Baru
                                        </span>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                        @click="removeNewFile(index)"
                                    >
                                        <X class="h-4 w-4" /> Hapus
                                    </Button>
                                </li>
                            </ul>

                            <!-- File validation error -->
                            <p
                                v-if="clientErrors.files || errors.files"
                                class="text-sm text-destructive mt-2"
                            >
                                {{ clientErrors.files || errors.files }}
                            </p>
                        </div>

                        <!-- No files message -->
                        <div v-else class="text-center py-8">
                            <p class="text-gray-500 mb-2">No files attached</p>
                            <p
                                v-if="clientErrors.files || errors.files"
                                class="text-sm text-destructive"
                            >
                                {{ clientErrors.files || errors.files }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Upload New Files -->
                <Card>
                    <CardHeader>
                        <CardTitle>Tambahkan Lampiran Baru</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Label for="attachments">File Lampiran *</Label>
                        <p class="text-xs mb-1 text-gray-500 italic">
                            Ukuran Maks: 2MB (jpg, png, pdf)
                        </p>
                        <Input
                            ref="fileInputRef"
                            type="file"
                            multiple
                            accept=".jpg,.jpeg,.png,.pdf"
                            :class="errors.files || clientErrors.files ? 'border-destructive' : ''"
                            @change="handleFileSelect"
                        />
                        <p class="text-sm text-gray-500 mt-1">
                            Pilih file untuk ditambahkan sebagai lampiran baru pada pengumuman ini.
                            Minimal satu file diperlukan.
                        </p>
                        <p v-if="!hasFiles" class="text-sm text-orange-600 mt-1">
                            ⚠️ Peringatan: Semua file saat ini akan dihapus. Harap tambahkan file
                            baru untuk mempertahankan setidaknya satu lampiran.
                        </p>
                    </CardContent>
                </Card>

                <!-- Actions -->
                <div class="flex items-center justify-end space-x-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="$inertia.visit(route('admin.announcements.index'))"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        :disabled="form.processing"
                        :class="!hasFiles ? 'bg-red-600 hover:bg-red-700' : ''"
                    >
                        {{ form.processing ? 'Memperbarui...' : 'Perbarui Pengumuman' }}
                    </Button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
