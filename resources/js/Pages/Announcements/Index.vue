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
import { MoreHorizontal, Plus, Eye, Edit, Copy, Trash2 } from "lucide-vue-next";
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
        hour: "2-digit",
        minute: "2-digit",
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
                    Announcement Management
                </h2>
                <Link :href="route('admin.announcements.create')">
                    <Button>
                        <Plus class="h-4 w-4 mr-2" />
                        Create Announcement
                    </Button>
                </Link>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Forms Grid -->
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <Card
                    v-for="announcement in props.announcements.data"
                    :key="announcement.id"
                    class="group hover:shadow-lg transition-shadow"
                >
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <CardTitle class="text-lg line-clamp-2">{{
                                    capitalize(announcement.title)
                                }}</CardTitle>
                                <CardDescription class="mt-1 line-clamp-2">
                                    {{
                                        stripHtml(announcement.content) ||
                                        "No description"
                                    }}
                                </CardDescription>
                            </div>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="sm">
                                        <MoreHorizontal class="h-4 w-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem as-child>
                                        <Link
                                            :href="
                                                route(
                                                    'admin.announcements.show',
                                                    announcement.id
                                                )
                                            " class="cursor-pointer"
                                        >
                                            <Eye class="h-4 w-4 mr-2" />
                                            View
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem as-child>
                                        <Link
                                            :href="
                                                route(
                                                    'admin.announcements.edit',
                                                    announcement.id
                                                )
                                            " class="cursor-pointer"
                                        >
                                            <Edit class="h-4 w-4 mr-2" />
                                            Edit
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        @click="
                                            deleteAnnouncement(announcement)
                                        "
                                        class="text-destructive cursor-pointer"
                                    >
                                        <Trash2 class="h-4 w-4 mr-2" />
                                        Delete
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <div class="flex items-center gap-2">
                                <Badge variant="outline">
                                    {{
                                        isPublic(announcement)
                                            ? "Public"
                                            : "Private"
                                    }}
                                </Badge>
                                <Badge
                                    :variant="
                                        isExpired(announcement.expired_at)
                                            ? 'destructive'
                                            : 'success'
                                    "
                                >
                                    {{
                                        isExpired(announcement.expired_at)
                                            ? "Expired"
                                            : "Active"
                                    }}
                                </Badge>
                            </div>

                            <div
                                class="flex items-center text-sm text-muted-foreground"
                            >
                                <Paperclip class="mr-2 h-4 w-4" />
                                {{ announcement.announcement_files.length }}
                                file
                                {{
                                    announcement.announcement_files.length !== 1
                                        ? "s"
                                        : ""
                                }}
                                attached
                            </div>

                            <div
                                class="space-y-1 text-xs text-muted-foreground"
                            >
                                <div>
                                    Created:
                                    {{ formatDate(announcement.created_at) }}
                                </div>
                                <div>
                                    Expires:
                                    {{
                                        formatDate(
                                            announcement.expired_at ?? ""
                                        )
                                    }}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Empty State -->
            <div
                v-if="props.announcements.data.length === 0"
                class="text-center py-12"
            >
                <div class="mx-auto max-w-md">
                    <div class="mx-auto h-12 w-12 text-muted-foreground">
                        <svg
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                            />
                        </svg>
                    </div>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">
                        No announcements
                    </h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Get started by creating your first announcement.
                    </p>
                    <div class="mt-6">
                        <Link :href="route('admin.announcements.create')">
                            <Button>
                                <Plus class="h-4 w-4 mr-2" />
                                Create Announcement
                            </Button>
                        </Link>
                    </div>
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
