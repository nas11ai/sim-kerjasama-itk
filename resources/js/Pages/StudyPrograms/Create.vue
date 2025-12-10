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
    form.post(route("admin.faculties.study-programs.store"));
};
</script>

<template>

    <Head title="Buat Program Studi" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="sm" @click="$inertia.visit(route('admin.faculties.study-programs'))">
                    <ArrowLeft class="h-4 w-4 mr-2" />
                    Kembali
                </Button>
                <div class="flex items-center gap-2">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Buat Program Studi
                    </h2>
                </div>
            </div>
        </template>

        <div class="max-w-2xl mx-auto">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <GraduationCap class="h-5 w-5" />
                        Informasi Program Studi
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-6">
                        <div class="space-y-2">
                            <Label for="faculty_id">Fakultas *</Label>
                            <Select v-model="form.faculty_id" required>
                                <SelectTrigger :class="form.errors.faculty_id ? 'border-destructive' : ''">
                                    <SelectValue placeholder="Pilih fakultas" />
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
                            <Label for="name">Nama Program Studi *</Label>
                            <Input id="name" v-model="form.name" placeholder="Masukkan nama program studi"
                                :class="form.errors.name ? 'border-destructive' : ''" />
                            <p v-if="form.errors.name" class="text-sm text-destructive">
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <div class="flex items-center justify-end space-x-2 pt-4">
                            <Button type="button" variant="outline"
                                @click="$inertia.visit(route('admin.faculties.study-programs'))">
                                Cancel
                            </Button>
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? "Membuat..." : "Buat Program Studi" }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
