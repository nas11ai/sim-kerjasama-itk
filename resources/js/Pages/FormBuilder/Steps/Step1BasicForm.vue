<script setup lang="ts">
import { computed } from 'vue';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Switch } from '@/Components/ui/switch';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Badge } from '@/Components/ui/badge';
import { Plus, Trash2, GripVertical } from 'lucide-vue-next';
import draggable from 'vuedraggable';

interface FieldOption {
    label: string;
    temp_id?: string;
}

interface FormField {
    field_type_id: number | null;
    label: string;
    is_required: boolean;
    options: FieldOption[];
    temp_id: string;
}

interface FormData {
    title: string;
    description: string;
    form_type_id: number | null;
    is_active: boolean;
    fields: FormField[];
}

interface Props {
    modelValue: FormData;
    formTypes: any[];
    fieldTypes: any[];
    errors: Record<string, string>;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:modelValue': [value: FormData];
}>();

const formData = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const fieldTypesWithOptions = ['select', 'radio', 'checkbox'];

const generateTempId = () => `temp_${Date.now()}_${Math.random()}`;

const addField = () => {
    formData.value.fields.push({
        field_type_id: null,
        label: '',
        is_required: false,
        options: [],
        temp_id: generateTempId(),
    });
};

const removeField = (index: number) => {
    formData.value.fields.splice(index, 1);
};

const addOption = (fieldIndex: number) => {
    formData.value.fields[fieldIndex].options.push({
        label: '',
        temp_id: generateTempId(),
    });
};

const removeOption = (fieldIndex: number, optionIndex: number) => {
    formData.value.fields[fieldIndex].options.splice(optionIndex, 1);
};

const getFieldTypeName = (fieldTypeId: number | null): string => {
    if (!fieldTypeId) return '';
    const fieldType = props.fieldTypes.find((ft) => ft.id === fieldTypeId);
    return fieldType?.name || '';
};

const fieldTypeRequiresOptions = (fieldTypeId: number | null): boolean => {
    if (!fieldTypeId) return false;
    const fieldTypeName = getFieldTypeName(fieldTypeId);
    return fieldTypesWithOptions.includes(fieldTypeName);
};
</script>

<template>
    <div class="space-y-6">
        <!-- Form Basic Info -->
        <div class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2">
                <Label for="title">Judul Formulir *</Label>
                <Input id="title" v-model="formData.title" placeholder="Masukkan Judul Formulir"
                    :class="errors['form.title'] ? 'border-destructive' : ''" />
                <p v-if="errors['form.title']" class="text-sm text-destructive">
                    {{ errors['form.title'] }}
                </p>
            </div>

            <div class="space-y-2">
                <Label for="form_type">Tipe Form *</Label>
                <Select v-model="formData.form_type_id">
                    <SelectTrigger id="form_type">
                        <SelectValue placeholder="Pilih tipe form" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="formType in formTypes" :key="formType.id" :value="formType.id">
                            {{ formType.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p v-if="errors['form.form_type_id']" class="text-sm text-destructive">
                    {{ errors['form.form_type_id'] }}
                </p>
            </div>
        </div>

        <div class="space-y-2">
            <Label for="description">Deskripsi</Label>
            <Textarea id="description" v-model="formData.description" placeholder="Masukkan deskripsi form (opsional)"
                rows="3" />
        </div>

        <div class="flex items-center space-x-2">
            <Switch v-model="formData.is_active" id="is_active" />
            <Label for="is_active">Aktif</Label>
        </div>

        <!-- Form Fields -->
        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <CardTitle>Isian Formulir</CardTitle>
                    <Button type="button" @click="addField" size="sm" variant="outline">
                        <Plus class="h-4 w-4 mr-2" />
                        Tambah Isian
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                <div v-if="formData.fields.length === 0" class="text-center py-8 text-muted-foreground">
                    <p>Belum ada isian yang ditambahkan. Klik "Tambah Isian" untuk memulai.</p>
                </div>

                <div v-else class="space-y-4">
                    <draggable v-model="formData.fields" item-key="temp_id" handle=".drag-handle" class="space-y-4"
                        :animation="200">
                        <template #item="{ element: field, index }">
                            <Card class="border-2 border-dashed">
                                <CardContent class="pt-6">
                                    <div class="flex items-start gap-4">
                                        <div class="drag-handle cursor-move p-1 hover:bg-muted rounded">
                                            <GripVertical class="h-4 w-4 text-muted-foreground" />
                                        </div>

                                        <div class="flex-1 space-y-4">
                                            <div class="grid gap-4 md:grid-cols-2">
                                                <div class="space-y-2">
                                                    <Label>Tipe Isian *</Label>
                                                    <Select v-model="field.field_type_id">
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="Pilih tipe isian" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem v-for="fieldType in fieldTypes"
                                                                :key="fieldType.id" :value="fieldType.id">
                                                                {{ fieldType.name }}
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>

                                                <div class="space-y-2">
                                                    <Label>Label Isian *</Label>
                                                    <Input v-model="field.label" placeholder="Masukkan label isian" />
                                                </div>
                                            </div>

                                            <div class="flex items-center space-x-2">
                                                <Switch v-model="field.is_required" :id="`required_${index}`" />
                                                <Label :for="`required_${index}`">Isian wajib diisi</Label>
                                            </div>

                                            <!-- Field Options -->
                                            <div v-if="fieldTypeRequiresOptions(field.field_type_id)" class="space-y-3">
                                                <div class="flex items-center justify-between">
                                                    <Label class="text-sm font-medium">Opsi</Label>
                                                    <Button type="button" size="sm" variant="outline"
                                                        @click="addOption(index)">
                                                        <Plus class="h-3 w-3 mr-1" />
                                                        Tambah Opsi
                                                    </Button>
                                                </div>

                                                <div v-if="field.options.length === 0"
                                                    class="text-sm text-muted-foreground">
                                                    Belum ada opsi yang ditambahkan.
                                                </div>

                                                <div v-else class="space-y-2">
                                                    <div v-for="(option, optionIndex) in field.options"
                                                        :key="option.temp_id || optionIndex"
                                                        class="flex items-center gap-2">
                                                        <Input v-model="option.label" placeholder="Label opsi"
                                                            class="flex-1" />
                                                        <Button type="button" variant="ghost" size="sm"
                                                            @click="removeOption(index, optionIndex)">
                                                            <Trash2 class="h-4 w-4 text-destructive" />
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Field Preview -->
                                            <div v-if="field.field_type_id && field.label"
                                                class="mt-4 p-3 bg-muted rounded-lg">
                                                <Label class="text-sm text-muted-foreground mb-2 block">Pratinjau:</Label>
                                                <div class="space-y-2">
                                                    <Label>
                                                        {{ field.label }}
                                                        <Badge v-if="field.is_required" variant="destructive"
                                                            class="ml-2 text-xs">
                                                            Wajib
                                                        </Badge>
                                                    </Label>

                                                    <div v-if="getFieldTypeName(field.field_type_id) === 'textarea'">
                                                        <Textarea placeholder="Ini adalah pratinjau" disabled />
                                                    </div>
                                                    <div v-else-if="
                                                        fieldTypeRequiresOptions(field.field_type_id) &&
                                                        field.options.length > 0
                                                    ">
                                                        <div class="space-y-1">
                                                            <div v-for="option in field.options" :key="option.temp_id"
                                                                class="flex items-center space-x-2">
                                                                <input :type="getFieldTypeName(field.field_type_id) === 'checkbox'
                                                                    ? 'checkbox'
                                                                    : 'radio'
                                                                    " disabled class="h-4 w-4" />
                                                                <span class="text-sm">{{ option.label }}</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div v-else>
                                                        <Input :type="getFieldTypeName(field.field_type_id)"
                                                            placeholder="Ini adalah pratinjau" disabled />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <Button type="button" variant="ghost" size="sm" @click="removeField(index)"
                                            class="text-destructive hover:text-destructive">
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </template>
                    </draggable>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
