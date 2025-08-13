<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { ArrowLeft, Building2 } from "lucide-vue-next";

const form = useForm({
    name: "",
});

const submit = () => {
    form.post(route("faculties.store"));
};
</script>

<template>

    <Head title="Create Faculty" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="sm" @click="$inertia.visit(route('faculties.index'))">
                    <ArrowLeft class="h-4 w-4 mr-2" />
                    Back
                </Button>
                <div class="flex items-center gap-2">
                    <Building2 class="h-6 w-6 text-blue-600" />
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Create New Faculty
                    </h2>
                </div>
            </div>
        </template>

        <div class="max-w-2xl mx-auto">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Building2 class="h-5 w-5" />
                        Faculty Information
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-6">
                        <div class="space-y-2">
                            <Label for="name">Faculty Name *</Label>
                            <Input id="name" v-model="form.name" placeholder="Enter faculty name"
                                :class="form.errors.name ? 'border-destructive' : ''" autofocus />
                            <p v-if="form.errors.name" class="text-sm text-destructive">
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <div class="flex items-center justify-end space-x-2 pt-4">
                            <Button type="button" variant="outline" @click="$inertia.visit(route('faculties.index'))">
                                Cancel
                            </Button>
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? "Creating..." : "Create Faculty" }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
