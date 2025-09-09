<script setup lang="ts">
import { computed } from "vue";
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
import { ArrowLeft } from "lucide-vue-next";
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

interface AnnouncementForm {
    title: string;
    content: string;
    type: string;
    expired_at: DateValue | undefined;
    expired_time?: string;
    files: File[] | null;
    [key: string]: any;
}

const form = useForm<AnnouncementForm>({
    title: "",
    content: "",
    type: "public",
    expired_at: undefined,
    expired_time: "",
    files: null,
});

const errors = computed<Partial<Record<keyof AnnouncementForm, string>>>(
    () => form.errors ?? {}
);

const df = new DateFormatter("en-US", { dateStyle: "long" });
const tomorrow = today(getLocalTimeZone()).add({ days: 1 });

const submit = () => {
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
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <Head title="Create Announcement" />

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
                <h2 class="text-xl bg font-semibold leading-tight text-gray-800">
                    Create New Announcement
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
                                                    ? df.format(form.expired_at.toDate(getLocalTimeZone()))
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
                        >
                        </Textarea>
                        <p
                            v-if="errors.content"
                            class="text-sm text-destructive"
                        >
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
                        <Label for="attachments">File Attachments *</Label>
                        <Input
                            type="file"
                            multiple
                            @change="
                                form.files = $event.target.files
                                    ? Array.from($event.target.files)
                                    : []
                            "
                            :class="errors.files ? 'border-destructive' : ''"
                        />
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
                                ? "Creating..."
                                : "Create Announcement"
                        }}
                    </Button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
