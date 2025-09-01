<script setup lang="ts">
import { Head } from "@inertiajs/vue3";

interface AnnouncementFile {
    id: number;
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
    files: AnnouncementFile[];
    created_by: {
        name: string;
    };
}

const props = defineProps<{ announcement: AnnouncementDetail }>();
</script>

<template>
    <Head :title="props.announcement.title" />

    <AuthenticatedLayout>
        <div class="max-w-3xl mx-auto py-8 space-y-6">
            <div class="border-b pb-4 mb-4">
                <h1 class="text-2xl font-bold">{{ props.announcement.title }}</h1>
                <div class="text-sm text-gray-500 flex gap-4 mt-2">
                    <span>Type: {{ props.announcement.type }}</span>
                    <span>Created by: {{ props.announcement.created_by?.name }}</span>
                    <span>Created at: {{ new Date(props.announcement.created_at).toLocaleString() }}</span>
                    <span v-if="props.announcement.expired_at">Expired at: {{ new Date(props.announcement.expired_at).toLocaleDateString() }}</span>
                </div>
            </div>

            <div class="prose max-w-none" v-html="props.announcement.content"></div>

            <div v-if="props.announcement.files && props.announcement.files.length > 0" class="mt-6">
                <h2 class="font-semibold mb-2">Attachments</h2>
                <ul class="space-y-2">
                    <li v-for="file in props.announcement.files" :key="file.id">
                        <a :href="file.file_path" target="_blank" class="text-blue-600 underline">
                            {{ file.file_name }}
                        </a>
                        <span class="text-xs text-gray-500 ml-2">({{ file.mime_type }}, {{ (file.file_size / 1024).toFixed(1) }} KB)</span>
                    </li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
