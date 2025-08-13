<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
import { Badge } from "@/Components/ui/badge";
import { ArrowLeft, GraduationCap, Building2 } from "lucide-vue-next";

interface Faculty {
    id: number;
    name: string;
}

interface StudyProgram {
    id: number;
    name: string;
    faculty_id: number;
    faculty: Faculty;
    created_at: string;
    updated_at: string;
}

interface FormData {
    name: string;
    faculty_id: string;
    [key: string]: any; // Required by Inertia's FormDataType constraint
}

interface FormErrors {
    name?: string;
    faculty_id?: string;
    [key: string]: string | undefined;
}

interface Props {
    studyProgram: StudyProgram;
    faculties: Faculty[];
}

const props = defineProps<Props>();

const form = useForm<FormData>({
    name: props.studyProgram.name,
    faculty_id: props.studyProgram.faculty_id.toString(),
});

const submit = () => {
    form.put(route("faculties.study-programs.update", props.studyProgram.id));
};

// Check if form has changes
const hasChanges = () => {
    return form.name !== props.studyProgram.name ||
        form.faculty_id !== props.studyProgram.faculty_id.toString();
};
</script>

<template>

    <Head title="Edit Study Program" />

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
                        Edit Study Program: {{ studyProgram.name }}
                    </h2>
                </div>
            </div>
        </template>

        <div class="max-w-2xl mx-auto space-y-6">
            <!-- Current Study Program Info Card -->
            <Card class="border-green-200 bg-green-50">
                <CardHeader>
                    <CardTitle class="text-green-900 flex items-center gap-2">
                        <GraduationCap class="h-5 w-5" />
                        Current Study Program Information
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div>
                        <p class="text-sm font-medium text-green-800">Study Program Name</p>
                        <p class="text-green-700 font-medium">{{ studyProgram.name }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-green-800">Faculty</p>
                        <Badge variant="outline" class="bg-blue-50 text-blue-700 border-blue-200">
                            <Building2 class="h-3 w-3 mr-1" />
                            {{ studyProgram.faculty.name }}
                        </Badge>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm font-medium text-green-800">Created</p>
                            <p class="text-sm text-green-600">{{ new Date(studyProgram.created_at).toLocaleDateString()
                            }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-green-800">Last Updated</p>
                            <p class="text-sm text-green-600">{{ new Date(studyProgram.updated_at).toLocaleDateString()
                            }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Edit Form Card -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <GraduationCap class="h-5 w-5" />
                        Edit Study Program Information
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-6">
                        <div class="space-y-2">
                            <Label for="faculty_id">Faculty *</Label>
                            <Select v-model="form.faculty_id" required>
                                <SelectTrigger
                                    :class="(form.errors as FormErrors).faculty_id ? 'border-destructive' : ''">
                                    <SelectValue placeholder="Select faculty" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="faculty in faculties" :key="faculty.id"
                                        :value="faculty.id.toString()">
                                        {{ faculty.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="(form.errors as FormErrors).faculty_id" class="text-sm text-destructive">
                                {{ (form.errors as FormErrors).faculty_id }}
                            </p>
                            <p v-if="form.faculty_id !== studyProgram.faculty_id.toString()"
                                class="text-sm text-amber-600">
                                <strong>Note:</strong> Changing faculty may affect related data.
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="name">Study Program Name *</Label>
                            <Input id="name" v-model="form.name" placeholder="Enter study program name"
                                :class="(form.errors as FormErrors).name ? 'border-destructive' : ''" autofocus />
                            <p v-if="(form.errors as FormErrors).name" class="text-sm text-destructive">
                                {{ (form.errors as FormErrors).name }}
                            </p>
                        </div>

                        <!-- Changes Preview -->
                        <div v-if="hasChanges()" class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <h4 class="font-medium text-yellow-800 mb-2">Pending Changes:</h4>
                            <div class="space-y-1 text-sm">
                                <div v-if="form.name !== studyProgram.name" class="text-yellow-700">
                                    <strong>Name:</strong> {{ studyProgram.name }} → {{ form.name }}
                                </div>
                                <div v-if="form.faculty_id !== studyProgram.faculty_id.toString()"
                                    class="text-yellow-700">
                                    <strong>Faculty:</strong> {{ studyProgram.faculty.name }} →
                                    {{faculties.find(f => f.id.toString() === form.faculty_id)?.name}}
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end space-x-2 pt-4">
                            <Button type="button" variant="outline"
                                @click="$inertia.visit(route('faculties.study-programs'))">
                                Cancel
                            </Button>
                            <Button type="submit" :disabled="form.processing || !hasChanges()">
                                {{ form.processing ? "Updating..." : "Update Study Program" }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
