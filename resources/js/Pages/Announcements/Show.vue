<script setup lang="ts">
import { computed } from "vue";
import { Head } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "../../Components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import {
    ArrowLeft,
    Edit,
    Calendar,
    Clock,
    FileText,
    Paperclip,
} from "lucide-vue-next";

interface AnnouncementFile {
    id: number;
    announcement_id: number;
    file_name: string;
    file_path: string;
    mime_type: string;
    file_size: number;
}

interface AnnouncementDetail {
    id: number;
    title: string;
    content: string;
    type: string;
    expired_at: string | null;
    created_at: string;
    updated_at: string;
    announcement_files: AnnouncementFile[];
}

interface Props {
    announcement: AnnouncementDetail;
}

const props = defineProps<Props>();

const formatDate = (dateString: string | null) => {
    if (!dateString) return null;
    return new Date(dateString).toLocaleString("en-EN", {
        year: "numeric",
        month: "long",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
        timeZone: "Asia/Makassar",
    });
};

const formatFileSize = (bytes: number) => {
    if (bytes < 1024) return bytes + " B";
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + " KB";
    return (bytes / (1024 * 1024)).toFixed(1) + " MB";
};

const typeVariant = computed(() => {
    return props.announcement.type === "public" ? "default" : "secondary";
});

const isExpired = computed(() => {
    if (!props.announcement.expired_at) return false;
    return new Date(props.announcement.expired_at) < new Date();
});

console.log("Files:", props.announcement.announcement_files);

</script>

<template>
    <Head :title="`Announcement: ${announcement.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Button
                        variant="ghost"
                        class="p-0 mr-2"
                        size="sm"
                        @click="
                            $inertia.visit(route('admin.announcements.index'))
                        "
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Back
                    </Button>
                    <h2
                        class="text-xl font-semibold leading-tight text-gray-800"
                    >
                        Announcement Details
                    </h2>
                </div>
            </div>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Basic Information -->
            <Card>
                <CardHeader>
                    <div class="flex items-start justify-between">
                        <CardTitle class="text-2xl">{{
                            announcement.title
                        }}</CardTitle>
                        <div class="flex items-center gap-2">
                            <Badge :variant="typeVariant">
                                {{
                                    announcement.type.charAt(0).toUpperCase() +
                                    announcement.type.slice(1)
                                }}
                            </Badge>
                            <Badge v-if="isExpired" variant="destructive">
                                Expired
                            </Badge>
                            <Badge
                                v-else-if="announcement.expired_at"
                                variant="success"
                            >
                                Active
                            </Badge>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <!-- Metadata -->
                    <div
                        class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600"
                    >
                        <div class="flex items-center gap-2">
                            <Calendar class="h-4 w-4" />
                            <span
                                >Created:
                                {{ formatDate(announcement.created_at) }}</span
                            >
                        </div>
                        <div class="flex items-center gap-2">
                            <Clock class="h-4 w-4" />
                            <span
                                >Updated:
                                {{ formatDate(announcement.updated_at) }}</span
                            >
                        </div>
                        <div
                            v-if="announcement.expired_at"
                            class="flex items-center gap-2 md:col-span-2"
                        >
                            <Calendar class="h-4 w-4" />
                            <span
                                :class="
                                    isExpired
                                        ? 'text-red-600'
                                        : 'text-orange-600'
                                "
                            >
                                Expires:
                                {{ formatDate(announcement.expired_at) }}
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Content -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <FileText class="h-5 w-5" />
                        Content
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="prose max-w-none">
                        <div
                            class="whitespace-pre-wrap text-gray-700 leading-relaxed"
                        >
                            {{ announcement.content }}
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- File Attachments -->
            <Card v-if="announcement.announcement_files && announcement.announcement_files.length > 0">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Paperclip class="h-5 w-5" />
                        Attachments ({{ announcement.announcement_files.length }})
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-3">
                        <div
                            v-for="file in announcement.announcement_files"
                            :key="file.id"
                            class="flex items-center justify-between p-3 border rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <div class="flex-shrink-0">
                                    <div
                                        class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center"
                                    >
                                        <Paperclip
                                            class="h-4 w-4 text-blue-600"
                                        />
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="text-sm font-medium text-gray-900 truncate"
                                    >
                                        {{ file.file_name }}
                                    </p>
                                    <div
                                        class="flex items-center gap-2 text-xs text-gray-500"
                                    >
                                        <span>{{ file.mime_type }}</span>
                                        <span>•</span>
                                        <span>{{
                                            formatFileSize(file.file_size)
                                        }}</span>
                                    </div>
                                </div>
                            </div>
                            <a
                                :href="file.file_path"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-9 px-3"
                            >
                                Download
                            </a>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- No Attachments Message -->
            <Card v-else>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Paperclip class="h-5 w-5" />
                        Attachments
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-center py-8 text-gray-500">
                        <Paperclip class="h-12 w-12 mx-auto mb-3 opacity-50" />
                        <p>No attachments available</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Actions -->
            <div class="flex items-center justify-between">
                <Button
                    variant="outline"
                    @click="$inertia.visit(route('admin.announcements.index'))"
                >
                    Back to List
                </Button>
                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        @click="
                            $inertia.visit(
                                route(
                                    'admin.announcements.edit',
                                    announcement.id
                                )
                            )
                        "
                    >
                        Edit
                    </Button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
