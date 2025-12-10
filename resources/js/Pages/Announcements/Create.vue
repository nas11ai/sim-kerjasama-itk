<script setup lang="ts">
import { computed, ref } from "vue";
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
} from "@internationalized/date";
import { Calendar as CalendarIcon } from "lucide-vue-next";
import { cn } from "@/lib/utils";
import { Calendar } from "@/Components/ui/calendar";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/Components/ui/popover";
import { useToast } from "@/Components/ui/toast/use-toast";
const { toast } = useToast();

interface AnnouncementForm {
    title: string;
    content: string;
    type: string;
    expired_at: DateValue | undefined;
    expired_time?: string;
    files: File[] | null;
    [key: string]: any;
}

const fileInputRef = ref<HTMLInputElement | null>(null);
const selectedFiles = ref<File[]>([]);
const fileError = ref<string | null>(null);

const form = useForm<AnnouncementForm>({
    title: "",
    content: "",
    type: "public",
    expired_at: undefined,
    expired_time: "",
    files: null,
});

const errors = computed<Partial<Record<keyof AnnouncementForm, string>>>(() => {
    return form.errors ?? {};
});

const df = new DateFormatter("en-US", { dateStyle: "long" });
const tomorrow = today(getLocalTimeZone()).add({ days: 1 });

// ✅ validasi file frontend
const allowedTypes = ["image/jpeg", "image/png", "application/pdf"];
const maxFileSize = 2 * 1024 * 1024; // 2MB

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (!target.files?.length) return;

    const files = Array.from(target.files);
    const validFiles: File[] = [];

    fileError.value = null;

    for (const file of files) {
        if (!allowedTypes.includes(file.type)) {
            fileError.value = `File "${file.name}" memiliki format tidak valid. Hanya boleh JPG, PNG, atau PDF.`;
            continue;
        }

        if (file.size > maxFileSize) {
            fileError.value = `File "${file.name}" melebihi ukuran maksimum 2MB.`;
            continue;
        }

        validFiles.push(file);
    }

    if (validFiles.length) {
        selectedFiles.value.push(...validFiles);
    }

    if (fileInputRef.value) {
        fileInputRef.value.value = "";
    }
};

const removeFile = (index: number) => {
    selectedFiles.value.splice(index, 1);
};

// ✅ Submit + Toast
const submit = () => {
    // Hapus error sebelumnya
    fileError.value = null;

    // Kalau tidak ada file, set null (biar backend nggak bingung)
    form.files = selectedFiles.value.length > 0 ? selectedFiles.value : null;

    form.transform((data) => {
        let expired = undefined;
        if (form.expired_at) {
            const date = form.expired_at.toDate(getLocalTimeZone());
            const yyyy = date.getFullYear();
            const mm = String(date.getMonth() + 1).padStart(2, "0");
            const dd = String(date.getDate()).padStart(2, "0");
            expired = form.expired_time
                ? `${yyyy}-${mm}-${dd}T${form.expired_time}:00`
                : `${yyyy}-${mm}-${dd}`;
        }
        return { ...data, expired_at: expired };
    }).post(route("admin.announcements.store"), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            toast({
                title: "Sukses",
                description: "Pengumuman berhasil dibuat!",
            });

            // Reset semua data
            form.reset();
            selectedFiles.value = [];
            if (fileInputRef.value) fileInputRef.value.value = "";
        },
        onError: (errors) => {
            toast({
                title: "Error",
                description:
                    "Gagal membuat pengumuman. Silakan periksa input Anda dan coba lagi.",
                variant: "destructive",
            });
            console.error("Validation errors:", errors);
        },
    });
};
</script>

<template>
    <Head title="Buat Pengumuman" />

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
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Buat Pengumuman Baru
                </h2>
            </div>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            <form @submit.prevent="submit" class="space-y-6">
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
                            <Label for="type">Tipe *</Label>
                            <Select v-model="form.type">
                                <SelectTrigger id="type">
                                    <SelectValue placeholder="Pilih tipe" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="public"
                                        >Publik</SelectItem
                                    >
                                    <SelectItem value="private"
                                        >Privat</SelectItem
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
                                    >Tanggal Kadaluarsa (opsional)</Label
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
                                                    : "Pilih Tanggal"
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
                                    >Waktu Kadaluarsa (opsional)</Label
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
                        <CardTitle>Isi Pengumuman</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Label for="content">Isi *</Label>
                        <Textarea
                            id="content"
                            v-model="form.content"
                            placeholder="Masukkan isi pengumuman di sini..."
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

                <!-- Selected Files Preview -->
                <Card v-if="selectedFiles.length > 0">
                    <CardHeader>
                        <CardTitle>File Terpilih</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul class="space-y-2">
                            <li
                                v-for="(file, index) in selectedFiles"
                                :key="index"
                                class="flex items-center justify-between p-3 bg-gray-50 rounded-md"
                            >
                                <div class="flex items-center gap-2">
                                    <span>{{ file.name }}</span>
                                    <span class="text-xs text-gray-500">
                                        ({{ file.type }},
                                        {{ (file.size / 1024).toFixed(1) }} KB)
                                    </span>
                                </div>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="sm"
                                    @click="removeFile(index)"
                                >
                                    <X class="h-4 w-4" /> Hapus
                                </Button>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <!-- Files Upload -->
                <Card>
                    <CardHeader>
                        <CardTitle>Lampiran</CardTitle>
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
                            @change="handleFileSelect"
                            :class="[
                                errors.files || fileError
                                    ? 'border-destructive'
                                    : '',
                            ]"
                        />
                        <p
                            v-if="fileError"
                            class="text-sm text-destructive mt-1"
                        >
                            {{ fileError }}
                        </p>
                        <p
                            v-if="errors.files"
                            class="text-sm text-destructive mt-1"
                        >
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
                        Batal
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{
                            form.processing
                                ? "Membuat..."
                                : "Buat Pengumuman"
                        }}
                    </Button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
