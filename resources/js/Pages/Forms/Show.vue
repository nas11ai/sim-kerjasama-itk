<!-- resources/js/Pages/Forms/Show.vue -->
<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import { Badge } from "@/Components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Separator } from "@/Components/ui/separator";
import { Label } from "@/Components/ui/label";
import {
    ArrowLeft,
    Edit,
    FileText,
    Calendar,
    CheckCircle,
    XCircle,
    Type,
    List,
    Hash,
} from "lucide-vue-next";

interface FormType {
    id: number;
    name: string;
}

interface FieldType {
    id: number;
    name: string;
}

interface FormFieldOption {
    id: number;
    label: string;
    order: number;
}

interface FormField {
    id: number;
    label: string;
    is_required: boolean;
    order: number;
    field_type: FieldType;
    form_field_options: FormFieldOption[];
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
    form: Form;
}

const props = defineProps<Props>();

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString("en-US", {
        year: "numeric",
        month: "long",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
};

const sortedFields = props.form.form_fields.sort((a, b) => a.order - b.order);
</script>

<template>
  <Head title="Detail Formulir" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
          <Link :href="route('admin.forms.index')">
            <Button
              variant="ghost"
              size="sm"
            >
              <ArrowLeft class="h-4 w-4 mr-2" />
              Kembali
            </Button>
          </Link>
          <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Detail Formulir
          </h2>
        </div>
        <Link :href="route('admin.forms.edit', props.form.id)">
          <Button class=" text-white">
            <Edit class="h-4 w-4 mr-2" />
            Edit Formulir
          </Button>
        </Link>
      </div>
    </template>

    <div class="max-w-4xl mx-auto space-y-6">
      <!-- Form Information -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <FileText class="h-5 w-5" />
            Informasi Formulir
          </CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid gap-6 md:grid-cols-2">
            <div>
              <h3 class="font-medium text-sm text-muted-foreground mb-1">
                Judul
              </h3>
              <p class="text-lg font-medium">
                {{ props.form.title }}
              </p>
            </div>
            <div>
              <h3 class="font-medium text-sm text-muted-foreground mb-1">
                Tipe Formulir
              </h3>
              <Badge
                variant="outline"
                class="text-sm"
              >
                {{ props.form.form_type.name }}
              </Badge>
            </div>
          </div>

          <div v-if="props.form.description">
            <h3 class="font-medium text-sm text-muted-foreground mb-1">
              Deskripsi
            </h3>
            <p class="text-gray-700">
              {{ props.form.description }}
            </p>
          </div>

          <div>
            <h3 class="font-medium text-sm text-muted-foreground mb-1">
              Status
            </h3>
            <Badge
              :variant="props.form.is_active ? 'default' : 'secondary'"
              class="flex items-center gap-1 w-fit"
            >
              <CheckCircle
                v-if="props.form.is_active"
                class="h-3 w-3"
              />
              <XCircle
                v-else
                class="h-3 w-3"
              />
              {{ props.form.is_active ? "Aktif" : "Tidak Aktif" }}
            </Badge>
          </div>

          <Separator />

          <div class="grid gap-6 md:grid-cols-2 text-sm">
            <div class="flex items-center gap-2">
              <Calendar class="h-4 w-4 text-muted-foreground" />
              <span class="text-muted-foreground">Dibuat:</span>
              <span>{{ formatDate(props.form.created_at) }}</span>
            </div>
            <div class="flex items-center gap-2">
              <Calendar class="h-4 w-4 text-muted-foreground" />
              <span class="text-muted-foreground">Diperbarui:</span>
              <span>{{ formatDate(props.form.updated_at) }}</span>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Form Fields -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <List class="h-5 w-5" />
            Isian Formulir ({{ sortedFields.length }})
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div
            v-if="sortedFields.length === 0"
            class="text-center py-8 text-muted-foreground"
          >
            <List class="h-12 w-12 mx-auto mb-4 opacity-50" />
            <p>Belum ada isian yang ditambahkan ke formulir ini.</p>
          </div>

          <div
            v-else
            class="space-y-4"
          >
            <div
              v-for="(field, index) in sortedFields"
              :key="field.id"
              class="border rounded-lg p-4 bg-card"
            >
              <div class="flex items-start justify-between mb-3">
                <div class="flex items-center gap-2">
                  <Badge
                    variant="outline"
                    class="text-xs"
                  >
                    Isian {{ field.order }}
                  </Badge>
                  <Badge
                    v-if="field.is_required"
                    variant="destructive"
                    class="text-xs"
                  >
                    Wajib
                  </Badge>
                  <Badge
                    variant="secondary"
                    class="text-xs"
                  >
                    {{ field.field_type.name }}
                  </Badge>
                </div>
              </div>

              <div class="space-y-3">
                <!-- field label -->
                <div>
                  <div class="flex items-center gap-2 text-sm font-medium mb-1">
                    <Type class="h-4 w-4 text-muted-foreground" />
                    Label Isian
                  </div>
                  <p class="text-gray-700 pl-6">
                    {{ field.label }}
                  </p>
                </div>

                <!-- field options (if any) -->
                <div v-if="field.form_field_options.length > 0">
                  <div class="flex items-center gap-2 text-sm font-medium mb-2">
                    <Hash class="h-4 w-4 text-muted-foreground" />
                    Opsi
                  </div>
                  <div class="pl-6 space-y-1">
                    <div
                      v-for="option in field.form_field_options.sort((a, b) => a.order - b.order)"
                      :key="option.id"
                      class="flex items-center gap-2 text-sm text-muted-foreground"
                    >
                      <span class="w-2 h-2 bg-muted-foreground rounded-full" />
                      {{ option.label }}
                    </div>
                  </div>
                </div>

                <!-- Field Preview -->
                <div class="mt-4 p-3 bg-muted rounded-lg">
                  <Label class="text-sm text-muted-foreground mb-2 block">Pratinjau:</Label>
                  <div class="space-y-2">
                    <Label>
                      {{ field.label }}
                      <Badge
                        v-if="field.is_required"
                        variant="destructive"
                        class="ml-2 text-xs"
                      >
                        Wajib
                      </Badge>
                    </Label>

                    <!-- Preview based on field type -->
                    <div v-if="field.field_type.name === 'textarea'">
                      <textarea
                        class="w-full p-2 border rounded-md bg-background"
                        placeholder="Ini adalah pratinjau"
                        disabled
                        rows="3"
                      />
                    </div>
                    <div v-else-if="['select', 'radio', 'checkbox'].includes(field.field_type.name) && field.form_field_options.length > 0">
                      <div class="space-y-1">
                        <div
                          v-for="option in field.form_field_options.sort((a, b) => a.order - b.order)"
                          :key="option.id"
                          class="flex items-center space-x-2"
                        >
                          <input
                            :type="field.field_type.name === 'checkbox' ? 'checkbox' : 'radio'"
                            disabled
                            class="h-4 w-4"
                          >
                          <span class="text-sm">{{ option.label }}</span>
                        </div>
                      </div>
                    </div>
                    <div v-else>
                      <input
                        :type="field.field_type.name"
                        class="w-full p-2 border rounded-md bg-background"
                        placeholder="Ini adalah pratinjau"
                        disabled
                      >
                    </div>
                  </div>
                </div>
              </div>

              <!-- Progress indicator for steps -->
              <div
                v-if="index < sortedFields.length - 1"
                class="flex justify-center mt-4"
              >
                <div class="w-px h-6 bg-border" />
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Summary Statistics -->
      <div class="grid gap-4 md:grid-cols-3">
        <Card>
          <CardContent class="p-6">
            <div class="flex items-center gap-2">
              <List class="h-6 w-6 text-blue-500" />
              <div>
                <p class="text-xl font-bold">
                  {{ sortedFields.length }}
                </p>
                <p class="text-sm text-muted-foreground">
                  Total Isian
                </p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent class="p-6">
            <div class="flex items-center gap-2">
              <CheckCircle class="h-6 w-6 text-red-500" />
              <div>
                <p class="text-xl font-bold">
                  {{ sortedFields.filter(f => f.is_required).length }}
                </p>
                <p class="text-sm text-muted-foreground">
                  Isian Wajib
                </p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent class="p-6">
            <div class="flex items-center gap-2">
              <Hash class="h-6 w-6 text-green-500" />
              <div>
                <p class="text-xl font-bold">
                  {{ sortedFields.reduce((total, field) => total + field.form_field_options.length, 0) }}
                </p>
                <p class="text-sm text-muted-foreground">
                  Total Opsi
                </p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
