<!-- resources/js/Pages/Announcement/Index.vue -->
<script setup lang="ts">
import { ref } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import {
    MoreHorizontal,
    Plus,
    Eye,
    Edit,
    Copy,
    Trash2,
    Paperclip,
} from "lucide-vue-next";
import { useToast } from "@/Components/ui/toast/use-toast";

interface AnnouncementFile {
    id: number;
    file_name: string;
    file_path: string;
}

interface Announcement {
    id: number;
    title: string;
    content: string;
    type: "public" | "private";
    expired_at: string | null;
    announcement_files: AnnouncementFile[];
    created_at: string;
    updated_at: string;
}

interface Props {
    announcements: {
        data: Announcement[];
        links: any[];
        meta: any;
    };
}

const props = defineProps<Props>();
const { toast } = useToast();

const isPublic = (announcement: Announcement) => {
    return announcement.type === "public";
};

const isExpired = (dateString: string | null) => {
    if (!dateString) return false;
    const today = new Date();
    const expiryDate = new Date(dateString);
    return expiryDate < today;
};

const deleteAnnouncement = (announcement: Announcement) => {
    if (confirm(`Are you sure you want to delete "${announcement.title}"?`)) {
        router.delete(route("admin.announcements.destroy", announcement.id), {
            onSuccess: () => {
                toast({
                    title: "Success",
                    description: "Announcement deleted successfully!",
                });
            },
        });
    }
};

// const duplicateAnnouncement = (announcement: Announcement) => {
//     router.post(
//         route("admin.announcements.duplicate", announcement.id),
//         {},
//         {
//             onSuccess: () => {
//                 toast({
//                     title: "Success",
//                     description: "Announcement duplicated successfully!",
//                 });
//             },
//         }
//     );
// };

const formatDate = (dateString: string) => {
    if (!dateString) {
        return "No expiration date";
    }

    return new Date(dateString).toLocaleDateString("en-EN", {
        year: "numeric",
        month: "short",
        day: "numeric",
        timeZone: "Asia/Makassar",
    });
};

const capitalize = (s: string) => {
    return s.charAt(0).toUpperCase() + s.slice(1);
};

function stripHtml(html: string) {
    if (!html) return "";
    const doc = new DOMParser().parseFromString(html, "text/html");
    return doc.body.textContent || "";
}
</script>

<template>
    <Head title="Announcement" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Announcement Center
                </h2>
            </div>
        </template>

        <div class="space-y-6 flex justify-center">
            <!-- Forms Grid -->
            <div
                class="flex flex-col flex-wrap gap-4 max-w-4xl justify-center align-middle"
            >
                <Card
                    v-for="announcement in props.announcements.data.filter(
                        (a) => a.type === 'private'
                    )"
                    :key="announcement.id"
                    class="group hover:shadow-lg transition-shadow rounded-xl"
                >
                    <CardHeader class="pb-2">
                        <span
                            class="w-fit px-2 py-1 text-[12px] rounded-full bg-blue-100 text-blue-700 font-medium"
                        >
                            {{
                                new Date(
                                    announcement.created_at
                                ).toLocaleDateString("id-ID", {
                                    day: "2-digit",
                                    month: "long",
                                    year: "numeric",
                                })
                            }}
                        </span>
                        <CardTitle
                            class="text-lg font-semibold text-gray-800"
                        >
                            {{ capitalize(announcement.title) }}
                        </CardTitle>
                    </CardHeader>

                    <CardContent>
                        <CardDescription
                            class="mt-1 text-gray-600 leading-relaxed"
                        >
                            {{
                                stripHtml(announcement.content) ||
                                "Tidak ada deskripsi"
                            }}
                        </CardDescription>

                        <!-- Attachments -->
                        <div
                            v-if="announcement.announcement_files.length"
                            class="mt-4 space-y-1"
                        >
                            <div
                                class="flex items-center text-sm text-gray-500 font-medium"
                            >
                                <Paperclip class="h-4 w-4 mr-2" /> Lampiran:
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <a
                                    v-for="file in announcement.announcement_files"
                                    :key="file.id"
                                    :href="file.file_path"
                                    target="_blank"
                                    class="inline-flex items-center rounded-md border px-3 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50 transition-colors"
                                >
                                    {{ file.file_name }}
                                </a>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Empty State -->
            <div
                v-if="
                    props.announcements.data.filter((a) => a.type === 'private')
                        .length === 0
                "
                class="text-center py-12"
            >
                <div class="mx-auto max-w-md">
                    <div class="mx-auto h-12 w-12 text-gray-400">
                        <Paperclip class="h-12 w-12 mx-auto text-gray-400" />
                    </div>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">
                        Belum ada pengumuman
                    </h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Nantikan informasi terbaru dari kami.
                    </p>
                </div>
            </div>

            <!-- Pagination -->
            <div
                v-if="props.announcements.links.length > 3"
                class="flex justify-center"
            >
                <nav class="flex items-center space-x-1">
                    <Link
                        v-for="link in props.announcements.links"
                        :key="link.label"
                        :href="link.url"
                        :class="[
                            'px-3 py-2 text-sm font-medium rounded-md',
                            link.active
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-muted',
                            !link.url && 'opacity-50 cursor-not-allowed',
                        ]"
                        v-html="link.label"
                    />
                </nav>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
