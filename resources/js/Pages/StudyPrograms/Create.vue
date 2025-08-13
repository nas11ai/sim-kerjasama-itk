<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
import { ArrowLeft, GraduationCap } from "lucide-vue-next";

interface Faculty {
    id: number;
    name: string;
}

interface Props {
    faculties: Faculty[];
}

const props = defineProps<Props>();

const form = useForm({
    name: "",
    faculty_id: "",
});

const submit = () => {
    form.post(route("faculties.study-programs.store"));
};
</script>

<template>

    <Head title="Create Study Program" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="sm" @click="$inertia.visit(route('faculties.study-programs'))">
                    <ArrowLeft class="h-4 w-4 mr-2" />
                    Back
                </Button>
                <div class="flex items-center gap-2">
                    <GraduationCap class="h-6 w-6 text-green-600" />
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Create New Study Program
                    </h2>
                </div>
            </div>
        </template>

        <div class="max-w-2xl mx-auto">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <GraduationCap class="h-5 w-5" />
                        Study Program Information
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-6">
                        <div class="space-y-2">
                            <Label for="faculty_id">Faculty *</Label>
                            <Select v-model="form.faculty_id" required>
                                <SelectTrigger :class="form.errors.faculty_id ? 'border-destructive' : ''">
                                    <SelectValue placeholder="Select faculty" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="faculty in faculties" :key="faculty.id"
                                        :value="faculty.id.toString()">
                                        {{ faculty.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.faculty_id" class="text-sm text-destructive">
                                {{ form.errors.faculty_id }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="name">Study Program Name *</Label>
                            <Input id="name" v-model="form.name" placeholder="Enter study program name"
                                :class="form.errors.name ? 'border-destructive' : ''" />
                            <p v-if="form.errors.name" class="text-sm text-destructive">
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <div class="flex items-center justify-end space-x-2 pt-4">
                            <Button type="button" variant="outline"
                                @click="$inertia.visit(route('faculties.study-programs'))">
                                Cancel
                            </Button>
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? "Creating..." : "Create Study Program" }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
