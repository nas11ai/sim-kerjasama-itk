<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted } from "vue";
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

import { EditorContent, useEditor } from "@tiptap/vue-3"
import StarterKit from "@tiptap/starter-kit"

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
    files: AnnouncementFile[];
}

interface AnnouncementForm {
    title: string;
    content: string;
    type: string;
    expired_at: string | undefined;
    files: File[] | null;
    [key: string]: any;
}

interface Props {
    announcement: ExistingAnnouncementDetail;
}

const props = defineProps<Props>();

const form = useForm<AnnouncementForm>({
    title: "",
    content: "",
    type: "public",
    expired_at: undefined,
    files: null,
});

const errors = computed<Partial<Record<keyof AnnouncementForm, string>>>(() => form.errors ?? {});

const editor = useEditor({
  extensions: [StarterKit],
  content: "<p>Tulis pengumuman di sini...</p>",
})

onMounted(() => {
    form.title = props.announcement.title;
    form.content = props.announcement.content;
    form.type = props.announcement.type;
    form.expired_at = props.announcement.expired_at;
    form.files = props.announcement.files as unknown as File[] | null;
});

const submit = () => {
    form.content = editor.value?.getHTML() || ""
    form.post(route("admin.announcements.update", props.announcement.id));
};


</script>

<template>
    <Head title="Edit Announcement" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Edit Announcement
            </h2>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Basic Info -->
                <Card>
                    <CardHeader>
                        <CardTitle>Announcement Information</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div>
                            <Label for="title">Title *</Label>
                            <Input
                                id="title"
                                v-model="form.title"
                                placeholder="Enter announcement title"
                                :class="errors.title ? 'border-destructive' : ''"
                            />
                            <p v-if="errors.title" class="text-sm text-destructive">
                                {{ errors.title }}
                            </p>
                        </div>
                        <div>
                            <Label for="type">Type *</Label>
                            <Select v-model="form.type">
                                <SelectTrigger id="type">
                                    <SelectValue placeholder="Select type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="public">Public</SelectItem>
                                    <SelectItem value="private">Private</SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="errors.type" class="text-sm text-destructive">
                                {{ errors.type }}
                            </p>
                        </div>
                        <div>
                            <Label for="expired_at">Expired Date (optional)</Label>
                            <Input
                                id="expired_at"
                                v-model="form.expired_at"
                                type="datetime-local"
                            />
                            <p v-if="errors.expired_at" class="text-sm text-destructive">
                                {{ errors.expired_at }}
                            </p>
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
                        <div class="border rounded p-2 min-h-[200px]">
                            <EditorContent :editor="editor" />
                        </div>
                        <p v-if="errors.content" class="text-sm text-destructive">
                            {{ errors.content }}
                        </p>
                    </CardContent>
                </Card>

                <!-- Existing Files -->
                <Card v-if="props.announcement.files && props.announcement.files.length > 0">
                    <CardHeader>
                        <CardTitle>Existing Attachments</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul class="space-y-2">
                            <li v-for="file in props.announcement.files" :key="file.id" class="flex items-center gap-2">
                                <a :href="file.file_path" target="_blank" class="text-blue-600 underline">
                                    {{ file.file_name }}
                                </a>
                                <span class="text-xs text-gray-500">({{ file.mime_type }}, {{ (file.file_size / 1024).toFixed(1) }} KB)</span>
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
                        <input
                            type="file"
                            multiple
                            @change="form.files = ($event.target as HTMLInputElement)?.files ? Array.from(($event.target as HTMLInputElement).files!) : null"
                            class="block w-full border rounded p-2"
                        />
                        <p v-if="errors.files" class="text-sm text-destructive">
                            {{ errors.files }}
                        </p>
                    </CardContent>
                </Card>

                <!-- Actions -->
                <div class="flex items-center justify-end space-x-2">
                    <Button type="button" variant="outline" @click="$inertia.visit(route('admin.announcements.index'))">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? "Updating..." : "Update Announcement" }}
                    </Button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
