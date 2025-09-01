<script setup lang="ts">
import { computed, onBeforeUnmount } from "vue";
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

interface AnnouncementForm {
    title: string;
    content: string;
    type: string;
    expired_at: string | undefined;
    files: File[] | null;
    [key: string]: any;
}

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

onBeforeUnmount(() => {
  editor.value?.destroy()
})

const handleSubmit = () => {
  form.value.content = editor.value?.getHTML() || ""
  console.log("Data terkirim:", form.value)
}

const submit = () => {
    form.content = editor.value?.getHTML() || ""
    form.post(route("admin.announcements.store"));
};


</script>

<template>
    <Head title="Announcement Detail" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Announcement Detail
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
                                :class="errors.title ? 'border-destructive' : ''"
                            />
                            <p v-if="errors.title" class="text-sm text-destructive">
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
                                    <SelectItem value="public">Public</SelectItem>
                                    <SelectItem value="private">Private</SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="errors.type" class="text-sm text-destructive">
                                {{ errors.type }}
                            </p>
                        </div>

                        <!-- Expired At -->
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

                <!-- Files -->
                <Card>
                    <CardHeader>
                        <CardTitle>Attachments</CardTitle>
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
                        {{ form.processing ? "Creating..." : "Create Announcement" }}
                    </Button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
