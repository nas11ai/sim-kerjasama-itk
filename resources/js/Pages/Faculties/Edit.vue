<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { ArrowLeft, Building2 } from "lucide-vue-next";

interface Faculty {
    id: number;
    name: string;
    created_at: string;
    updated_at: string;
}

interface FormData {
    name: string;
    [key: string]: any;
}

interface FormErrors {
    name?: string;
    [key: string]: string | undefined;
}

interface Props {
    faculty: Faculty;
}

const props = defineProps<Props>();

// Create the form with proper typing
const form = useForm<FormData>({
    name: props.faculty.name,
});

const submit = () => {
    form.put(route("admin.faculties.update", props.faculty.id));
};
</script>

<template>
  <Head title="Edit Fakultas" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <Button
          variant="ghost"
          size="sm"
          @click="$inertia.visit(route('admin.faculties.index'))"
        >
          <ArrowLeft class="h-4 w-4 mr-2" />
          Kembali
        </Button>
        <div class="flex items-center gap-2">
          <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Edit Fakultas: {{ faculty.name }}
          </h2>
        </div>
      </div>
    </template>

    <div class="max-w-2xl mx-auto space-y-6">
      <!-- Faculty Info Card -->
      <Card class="border-blue-200 bg-blue-50">
        <CardHeader>
          <CardTitle class="text-blue-900 flex items-center gap-2">
            Informasi Fakultas Saat Ini
          </CardTitle>
        </CardHeader>
        <CardContent class="space-y-2">
          <div>
            <p class="text-sm font-medium text-blue-800">
              Nama Fakultas
            </p>
            <p class="text-blue-700">
              {{ faculty.name }}
            </p>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-sm font-medium text-blue-800">
                Dibuat
              </p>
              <p class="text-sm text-blue-600">
                {{ new Date(faculty.created_at).toLocaleDateString() }}
              </p>
            </div>
            <div>
              <p class="text-sm font-medium text-blue-800">
                Terakhir Diperbarui
              </p>
              <p class="text-sm text-blue-600">
                {{ new Date(faculty.updated_at).toLocaleDateString() }}
              </p>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Edit Form Card -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Building2 class="h-5 w-5" />
            Edit Informasi Fakultas
          </CardTitle>
        </CardHeader>
        <CardContent>
          <form
            class="space-y-6"
            @submit.prevent="submit"
          >
            <div class="space-y-2">
              <Label for="name">Nama Fakultas *</Label>
              <Input
                id="name"
                v-model="form.name"
                placeholder="Masukkan nama fakultas"
                :class="(form.errors as FormErrors).name ? 'border-destructive' : ''"
                autofocus
              />
              <p
                v-if="(form.errors as FormErrors).name"
                class="text-sm text-destructive"
              >
                {{ (form.errors as FormErrors).name }}
              </p>
            </div>

            <div class="flex items-center justify-end space-x-2 pt-4">
              <Button
                type="button"
                variant="outline"
                @click="$inertia.visit(route('admin.faculties.index'))"
              >
                Batal
              </Button>
              <Button
                type="submit"
                :disabled="form.processing || form.name === faculty.name"
              >
                {{ form.processing ? "Memperbarui..." : "Perbarui Fakultas" }}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  </AuthenticatedLayout>
</template>
