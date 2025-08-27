<!-- resources/js/Pages/Admin/Reviewers/Edit.vue -->
<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
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
import { Badge } from "@/Components/ui/badge";
import { ArrowLeft, UserCog } from "lucide-vue-next";

interface User {
    id: number;
    name: string;
    email: string;
}

interface ReviewerRole {
    id: number;
    name: string;
}

interface Reviewer {
    id: number;
    start_date: string;
    end_date: string | null;
    user: User;
    reviewer_role: ReviewerRole;
}

interface Props {
    reviewer: Reviewer;
    reviewerRoles: ReviewerRole[];
}

const props = defineProps<Props>();

const form = useForm({
    reviewer_role_id: props.reviewer.reviewer_role.id.toString(),
    start_date: props.reviewer.start_date,
    end_date: props.reviewer.end_date || ''
});

const submit = () => {
    form.put(route('admin.reviewers.update', props.reviewer.id));
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString("id-ID", {
        year: "numeric",
        month: "long",
        day: "numeric",
    });
};
</script>

<template>

    <Head title="Edit Reviewer" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="sm" @click="$inertia.visit(route('admin.reviewers.index'))">
                    <ArrowLeft class="h-4 w-4 mr-2" />
                    Back to Reviewers
                </Button>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Edit Reviewer
                </h2>
            </div>
        </template>

        <div class="max-w-2xl mx-auto space-y-6">
            <!-- Reviewer Information -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <UserCog class="h-5 w-5" />
                        Current Reviewer Information
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 bg-muted/50 rounded-lg">
                            <div>
                                <h3 class="font-semibold text-lg">{{ reviewer.user.name }}</h3>
                                <p class="text-muted-foreground">{{ reviewer.user.email }}</p>
                                <div class="flex items-center gap-2 mt-2">
                                    <Badge variant="outline">
                                        User ID: {{ reviewer.user.id }}
                                    </Badge>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Current Role</Label>
                                <p class="font-medium">{{ reviewer.reviewer_role.name }}</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Start Date</Label>
                                <p class="font-medium">{{ formatDate(reviewer.start_date) }}</p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Edit Form -->
            <Card>
                <CardHeader>
                    <CardTitle>Update Reviewer Details</CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Reviewer Role -->
                        <div class="space-y-2">
                            <Label for="reviewer_role_id">Reviewer Role *</Label>
                            <Select v-model="form.reviewer_role_id" required>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select reviewer role" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="role in props.reviewerRoles" :key="role.id"
                                        :value="role.id.toString()">
                                        {{ role.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.reviewer_role_id" class="text-sm text-destructive">
                                {{ form.errors.reviewer_role_id }}
                            </p>
                        </div>

                        <!-- Start Date -->
                        <div class="space-y-2">
                            <Label for="start_date">Start Date *</Label>
                            <Input id="start_date" v-model="form.start_date" type="date" required />
                            <p v-if="form.errors.start_date" class="text-sm text-destructive">
                                {{ form.errors.start_date }}
                            </p>
                        </div>

                        <!-- End Date -->
                        <div class="space-y-2">
                            <Label for="end_date">End Date (Optional)</Label>
                            <Input id="end_date" v-model="form.end_date" type="date" />
                            <p class="text-xs text-muted-foreground">
                                Leave empty for no end date (permanent reviewer assignment)
                            </p>
                            <p v-if="form.errors.end_date" class="text-sm text-destructive">
                                {{ form.errors.end_date }}
                            </p>
                        </div>

                        <!-- Warning for Active Reviewer -->
                        <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-yellow-800">
                                        Important Notice
                                    </h3>
                                    <div class="mt-2 text-sm text-yellow-700">
                                        <p>
                                            Updating reviewer information may affect ongoing review processes.
                                            Please ensure this change is necessary and coordinated with relevant
                                            stakeholders.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex items-center justify-end space-x-3">
                            <Button type="button" variant="outline"
                                @click="$inertia.visit(route('admin.reviewers.index'))">
                                Cancel
                            </Button>
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? "Updating..." : "Update Reviewer" }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
