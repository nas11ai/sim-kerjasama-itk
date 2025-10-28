<!-- resources/js/Pages/Forms/Index.vue -->
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

interface FormType {
    id: number;
    name: string;
}

interface FormField {
    id: number;
    label: string;
    is_required: boolean;
}

interface Form {
    id: number;
    title: string;
    description: string;
    is_active: boolean;
    form_type: FormType;
    form_fields: FormField[];
    created_at: string;
    updated_at: string;
}

interface Props {
    forms: {
        data: Form[];
        links: any[];
        meta: any;
    };
}

const props = defineProps<Props>();
const { toast } = useToast();

const deleteForm = (form: Form) => {
    if (confirm(`Are you sure you want to delete "${form.title}"?`)) {
        router.delete(route("admin.forms.destroy", form.id), {
            onSuccess: () => {
                toast({
                    title: "Success",
                    description: "Form deleted successfully!",
                });
            },
        });
    }
};

const duplicateForm = (form: Form) => {
    router.post(
        route("admin.forms.duplicate", form.id),
        {},
        {
            onSuccess: () => {
                toast({
                    title: "Success",
                    description: "Form duplicated successfully!",
                });
            },
        }
    );
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
    });
};
</script>

<template>

    <Head title="Forms" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Form Management
                </h2>
                <Link :href="route('admin.forms.create')">
                <Button>
                    <Plus class="h-4 w-4 mr-2" />
                    Create Form
                </Button>
                </Link>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Forms Grid -->
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <Card v-for="form in props.forms.data" :key="form.id" class="group hover:shadow-lg transition-shadow">
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <CardTitle class="text-lg line-clamp-2">{{
                                    form.title
                                }}</CardTitle>
                                <CardDescription class="mt-1 line-clamp-2">
                                    {{ form.description || "No description" }}
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
                                        <Link :href="route('admin.forms.show', form.id)">
                                        <Eye class="h-4 w-4 mr-2" />
                                        View
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem as-child>
                                        <Link :href="route('admin.forms.edit', form.id)">
                                        <Edit class="h-4 w-4 mr-2" />
                                        Edit
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click="duplicateForm(form)">
                                        <Copy class="h-4 w-4 mr-2" />
                                        Duplicate
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click="deleteForm(form)" class="text-destructive">
                                        <Trash2 class="h-4 w-4 mr-2" />
                                        Delete
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div class="flex items-center gap-2">
                                <Badge variant="outline">{{
                                    form.form_type.name
                                }}</Badge>
                                <Badge :variant="form.is_active
                                    ? 'default'
                                    : 'destructive'
                                    ">
                                    {{ form.is_active ? "Active" : "Inactive" }}
                                </Badge>
                            </div>

                            <div class="text-sm text-muted-foreground">
                                {{ form.form_fields.length }} field{{
                                    form.form_fields.length !== 1 ? "s" : ""
                                }}
                            </div>

                            <div class="text-xs text-muted-foreground">
                                Created {{ formatDate(form.created_at) }}
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Empty State -->
            <div v-if="props.forms.data.length === 0" class="text-center py-12">
                <div class="mx-auto max-w-md">
                    <div class="mx-auto h-12 w-12 text-muted-foreground">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">
                        No forms
                    </h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Get started by creating your first form.
                    </p>
                    <div class="mt-6">
                        <Link :href="route('admin.forms.create')">
                        <Button>
                            <Plus class="h-4 w-4 mr-2" />
                            Create Form
                        </Button>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="props.forms.links.length > 3" class="flex justify-center">
                <nav class="flex items-center space-x-1">
                    <Link v-for="link in props.forms.links" :key="link.label" :href="link.url" :class="[
                        'px-3 py-2 text-sm font-medium rounded-md',
                        link.active
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-muted',
                        !link.url && 'opacity-50 cursor-not-allowed',
                    ]" v-html="link.label" />
                </nav>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
