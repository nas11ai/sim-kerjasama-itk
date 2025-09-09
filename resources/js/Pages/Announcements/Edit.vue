<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
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
    () => form.errors ?? {}
);

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

const submit = () => {
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
        forceFormData: true, // WAJIB saat ada File
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            // Reset file input setelah berhasil submit
            form.files = null;
            if (fileInputRef.value) {
                fileInputRef.value.value = "";
            }
        },
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
                                class="text-sm text-destructive"
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
                                class="text-sm text-destructive"
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
                                    class="text-sm text-destructive"
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
                                    class="text-sm text-destructive"
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
                            v-model="form.content"
                            placeholder="Enter announcement content"
                            :class="errors.content ? 'border-destructive' : ''"
                        />
                        <p
                            v-if="errors.content"
                            class="text-sm text-destructive"
                        >
                            {{ errors.content }}
                        </p>
                    </CardContent>
                </Card>

                <!-- Existing Files -->
                <Card v-if="existingFiles.length > 0">
                    <CardHeader>
                        <CardTitle>Existing Attachments</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul class="space-y-2">
                            <li
                                v-for="file in existingFiles"
                                :key="file.id"
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
                        </ul>
                    </CardContent>
                </Card>

                <!-- Upload New Files -->
                <Card>
                    <CardHeader>
                        <CardTitle>Upload New Attachments</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Label for="attachments"
                            >File Attachments (optional)</Label
                        >
                        <Input
                            ref="fileInputRef"
                            type="file"
                            multiple
                            @change="
                                form.files = $event.target.files
                                    ? Array.from($event.target.files)
                                    : []
                            "
                            :class="errors.files ? 'border-destructive' : ''"
                        />
                        <p class="text-sm text-gray-500 mt-1">
                            Select files to add as new attachments to this
                            announcement.
                        </p>
                        <p v-if="errors.files" class="text-sm text-destructive">
                            {{ errors.files }}
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
                    <Button type="submit" :disabled="form.processing">
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
