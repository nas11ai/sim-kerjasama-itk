<!-- resources/js/Pages/Admin/ReviewerRoles/Create.vue -->
<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Checkbox } from "@/Components/ui/checkbox";
import { ArrowLeft, Shield } from "lucide-vue-next";

const form = useForm({
    name: '',
    is_active: true
});

const submit = () => {
    form.post(route('admin.reviewer-roles.store'));
};
</script>

<template>

    <Head title="Create Reviewer Role" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="sm" @click="$inertia.visit(route('admin.reviewer-roles.index'))">
                    <ArrowLeft class="h-4 w-4 mr-2" />
                    Back to Reviewer Roles
                </Button>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Create Reviewer Role
                </h2>
            </div>
        </template>

        <div class="max-w-2xl mx-auto">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Shield class="h-5 w-5" />
                        Role Information
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Role Name -->
                        <div class="space-y-2">
                            <Label for="name">Role Name *</Label>
                            <Input id="name" v-model="form.name" type="text"
                                placeholder="Enter reviewer role name (e.g., Faculty Reviewer, External Reviewer)"
                                required />
                            <p class="text-xs text-muted-foreground">
                                Choose a descriptive name that clearly identifies the reviewer's role and
                                responsibilities.
                            </p>
                            <p v-if="form.errors.name" class="text-sm text-destructive">
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <!-- Active Status -->
                        <div class="space-y-2">
                            <div class="flex items-center space-x-2">
                                <Checkbox id="is_active" v-model="form.is_active" />
                                <Label for="is_active"
                                    class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                                    Active Role
                                </Label>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Only active roles can be assigned to reviewers. Inactive roles are hidden from selection
                                but
                                preserve existing assignments.
                            </p>
                            <p v-if="form.errors.is_active" class="text-sm text-destructive">
                                {{ form.errors.is_active }}
                            </p>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex items-center justify-end space-x-3">
                            <Button type="button" variant="outline"
                                @click="$inertia.visit(route('admin.reviewer-roles.index'))">
                                Cancel
                            </Button>
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? "Creating..." : "Create Role" }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
