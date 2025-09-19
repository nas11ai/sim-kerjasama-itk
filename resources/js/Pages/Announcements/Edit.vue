<script setup lang="ts">
import { computed, handleError, onMounted, ref } from "vue";
import { Head, useForm, usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "../../Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/Components/ui/select";

import { Textarea } from "@/Components/ui/textarea";
import { ArrowLeft, X } from "lucide-vue-next";
import type { DateValue } from "@internationalized/date";
import {
    today,
    DateFormatter,
    getLocalTimeZone,
    parseDate,
} from "@internationalized/date";
import { Calendar as CalendarIcon } from "lucide-vue-next";
import { cn } from "@/lib/utils";
import { Calendar } from "@/Components/ui/calendar";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/Components/ui/popover";

interface AnnouncementFile {
    id: number;
    announcement_id: number;
    file_name: string;
    file_path: string;
    mime_type: string;
    file_size: number;
}

interface ExistingAnnouncementDetail {
    id: number;
    title: string;
    content: string;
    type: string;
    expired_at: string | undefined;
    announcement_files: AnnouncementFile[];
}

interface AnnouncementForm {
    title: string;
    content: string;
    type: string;
    expired_at: DateValue | undefined;
    expired_time?: string;
    files: File[] | null;
    deleted_files?: number[];
    [key: string]: any;
}

interface Props {
    announcement: ExistingAnnouncementDetail;
}

const props = defineProps<Props>();

// Ref untuk input file
const fileInputRef = ref<HTMLInputElement | null>(null);

// State untuk menampilkan file yang tidak dihapus
const existingFiles = ref<AnnouncementFile[]>([]);

const form = useForm<AnnouncementForm>({
    title: "",
    content: "",
    type: "public",
    expired_at: undefined,
    expired_time: "",
    files: null,
    deleted_files: [],
});

const errors = computed<Partial<Record<keyof AnnouncementForm, string>>>(
    () => {
        console.log('Form errors:', form.errors);
        return form.errors ?? {};
    }
);

// Computed untuk mengecek apakah masih ada file
const hasFiles = computed(() => {
    return existingFiles.value.length > 0 || newFiles.value.length > 0;
});

// Computed untuk validasi client-side
const clientErrors = ref<{ files?: string }>({});

const df = new DateFormatter("en-US", { dateStyle: "long" });
const tomorrow = today(getLocalTimeZone()).add({ days: 1 });

onMounted(() => {
    form.title = props.announcement.title;
    form.content = props.announcement.content;
    form.type = props.announcement.type;

    // Copy existing files to local state
    existingFiles.value = [...props.announcement.announcement_files];

    if (props.announcement.expired_at) {
        const date = new Date(props.announcement.expired_at);
        form.expired_at = parseDate(date.toLocaleDateString("en-CA"));
        form.expired_time = date.toLocaleTimeString("en-GB", {
            hour: "2-digit",
            minute: "2-digit",
        });
    }
});

const newFiles = ref<File[]>([]);

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (!target.files?.length) return;

    newFiles.value.push(...Array.from(target.files));

    // Clear client-side validation error ketika file ditambahkan
    if (clientErrors.value.files) {
        delete clientErrors.value.files;
    }

    if (fileInputRef.value) {
        fileInputRef.value.value = "";
    }
};

// Function untuk menghapus file dari tampilan
const deleteFile = (fileId: number) => {
    // Tambahkan ke array deleted_files
    if (!form.deleted_files) {
        form.deleted_files = [];
    }
    form.deleted_files.push(fileId);

    // Hapus dari tampilan
    existingFiles.value = existingFiles.value.filter(
        (file) => file.id !== fileId
    );
};

// Function untuk menghapus new file
const removeNewFile = (index: number) => {
    newFiles.value.splice(index, 1);
};

// Client-side validation
const validateFiles = () => {
    if (!hasFiles.value) {
        clientErrors.value.files = "At least one file is required.";
        return false;
    }
    delete clientErrors.value.files;
    return true;
};

const submit = () => {
    // Reset client errors
    clientErrors.value = {};

    // Validate files client-side
    if (!validateFiles()) {
        return;
    }

    form.files = newFiles.value;
    form.transform((data) => {
        let expired: string | undefined;

        if (form.expired_at) {
            const date = form.expired_at.toDate(getLocalTimeZone());
            const yyyy = date.getFullYear();
            const mm = String(date.getMonth() + 1).padStart(2, "0");
            const dd = String(date.getDate()).padStart(2, "0");

            expired = form.expired_time
                ? `${yyyy}-${mm}-${dd}T${form.expired_time}:00`
                : `${yyyy}-${mm}-${dd}`;
        }

        // spoof method agar Laravel tetap masuk ke route update (PUT)
        return { ...data, expired_at: expired, _method: "put" };
    }).post(route("admin.announcements.update", props.announcement.id), {
        forceFormData: true,
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            newFiles.value = [];
            form.files = null;
            clientErrors.value = {};
            if (fileInputRef.value) {
                fileInputRef.value.value = "";
            }
        },
        onError: (errors) => {
            console.log('Validation errors:', errors);
        }
    });
};
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
                    Back
                </Button>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Edit Announcement
                </h2>
            </div>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Basic Info -->
                <Card>
                    <CardHeader>
                        <CardTitle>Announcement Information</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <!-- Title -->
                        <div>
                            <Label for="title">Title *</Label>
                            <Input
                                id="title"
                                v-model="form.title"
                                placeholder="Enter announcement title"
                                :class="
                                    errors.title ? 'border-destructive' : ''
                                "
                            />
                            <p
                                v-if="errors.title"
                                class="text-sm text-destructive mt-1"
                            >
                                {{ errors.title }}
                            </p>
                        </div>

                        <!-- Type -->
                        <div>
                            <Label for="type">Type *</Label>
                            <Select v-model="form.type">
                                <SelectTrigger id="type">
                                    <SelectValue placeholder="Select type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="public"
                                        >Public</SelectItem
                                    >
                                    <SelectItem value="private"
                                        >Private</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <p
                                v-if="errors.type"
                                class="text-sm text-destructive mt-1"
                            >
                                {{ errors.type }}
                            </p>
                        </div>

                        <!-- Expired At -->
                        <div class="flex flex-row w-full gap-2">
                            <div class="flex w-full flex-col gap-1">
                                <Label for="expired_at"
                                    >Expired Date (optional)</Label
                                >
                                <Popover>
                                    <PopoverTrigger as-child>
                                        <Button
                                            variant="outline"
                                            :class="
                                                cn(
                                                    'w-full justify-start text-left font-normal',
                                                    !form.expired_at &&
                                                        'text-muted-foreground'
                                                )
                                            "
                                        >
                                            <CalendarIcon
                                                class="mr-2 h-4 w-4"
                                            />
                                            {{
                                                form.expired_at
                                                    ? df.format(
                                                          form.expired_at.toDate(
                                                              getLocalTimeZone()
                                                          )
                                                      )
                                                    : "Pick a date"
                                            }}
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent class="w-auto p-0">
                                        <Calendar
                                            v-model="form.expired_at"
                                            initial-focus
                                            :min-value="tomorrow"
                                        />
                                    </PopoverContent>
                                </Popover>
                                <p
                                    v-if="errors.expired_at"
                                    class="text-sm text-destructive mt-1"
                                >
                                    {{ errors.expired_at }}
                                </p>
                            </div>
                            <div class="flex flex-col gap-1 w-full">
                                <Label for="expired_time"
                                    >Expired Time (optional)</Label
                                >
                                <Input
                                    id="expired_time"
                                    type="time"
                                    v-model="form.expired_time"
                                    :class="
                                        errors.expired_time
                                            ? 'border-destructive'
                                            : ''
                                    "
                                />
                                <p
                                    v-if="errors.expired_time"
                                    class="text-sm text-destructive mt-1"
                                >
                                    {{ errors.expired_time }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Content -->
                <Card>
                    <CardHeader>
                        <CardTitle>Announcement Content</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Label for="content">Content *</Label>
                        <Textarea
                            id="content"
                            v-model="form.content"
                            placeholder="Enter announcement content"
                            :class="errors.content ? 'border-destructive' : ''"
                            rows="5"
                        />
                        <p
                            v-if="errors.content"
                            class="text-sm text-destructive mt-1"
                        >
                            {{ errors.content }}
                        </p>
                    </CardContent>
                </Card>

                <!-- Current Attachments -->
                <Card>
                    <CardHeader>
                        <CardTitle>Current Attachments</CardTitle>
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
                                            :href="file.file_path"
                                            target="_blank"
                                            class="text-blue-600 underline hover:text-blue-800"
                                        >
                                            {{ file.file_name }}
                                        </a>
                                        <span class="text-xs text-gray-500"
                                            >({{ file.mime_type }},
                                            {{ (file.file_size / 1024).toFixed(1) }}
                                            KB)</span
                                        >
                                        <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">
                                            Current
                                        </span>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                        @click="deleteFile(file.id)"
                                        class="ml-2"
                                    >
                                        <X class="h-4 w-4" />
                                        Delete
                                    </Button>
                                </li>

                                <!-- New Files -->
                                <li
                                    v-for="(file, index) in newFiles"
                                    :key="`new-${index}`"
                                    class="flex items-center justify-between p-3 bg-green-50 rounded-md"
                                >
                                    <div class="flex items-center gap-2">
                                        <span>{{ file.name }}</span>
                                        <span class="text-xs text-gray-500">
                                            ({{ file.type }},
                                            {{ (file.size / 1024).toFixed(1) }} KB)
                                        </span>
                                        <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded">
                                            New
                                        </span>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                        @click="removeNewFile(index)"
                                    >
                                        <X class="h-4 w-4" /> Remove
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
                        <CardTitle>Add New Attachments</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Label for="attachments"
                            >File Attachments *</Label
                        >
                        <Input
                            ref="fileInputRef"
                            type="file"
                            multiple
                            @change="handleFileSelect"
                            :class="(errors.files || clientErrors.files) ? 'border-destructive' : ''"
                        />
                        <p class="text-sm text-gray-500 mt-1">
                            Select files to add as new attachments to this announcement.
                            At least one file is required.
                        </p>
                        <p v-if="!hasFiles" class="text-sm text-orange-600 mt-1">
                            ⚠️ Warning: All current files will be removed. Please add new files to maintain at least one attachment.
                        </p>
                    </CardContent>
                </Card>

                <!-- Actions -->
                <div class="flex items-center justify-end space-x-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="
                            $inertia.visit(route('admin.announcements.index'))
                        "
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        :disabled="form.processing"
                        :class="!hasFiles ? 'bg-red-600 hover:bg-red-700' : ''"
                    >
                        {{
                            form.processing
                                ? "Updating..."
                                : "Update Announcement"
                        }}
                    </Button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
