<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select'
import { Label } from '@/Components/ui/label'
import { Badge } from '@/Components/ui/badge'
import { Checkbox } from '@/Components/ui/checkbox'
import { Plus, Trash2, Users, Building } from 'lucide-vue-next'

interface StudyProgram {
    id: number
    name: string
}

export interface Faculty {
    id: number
    name: string
    study_programs: StudyProgram[]
}

interface AccessControl {
    permission: string
    study_program_id: number
    temp_id: string
}

interface Props {
    modelValue: AccessControl[]
    permissions: string[]
    faculties: Faculty[]
    errors: Record<string, string>
}

const props = defineProps<Props>()
const emit = defineEmits<{
    'update:modelValue': [value: AccessControl[]]
}>()

const accessControls = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
})

const selectedFacultyId = ref<number | null>(null)
const selectedPermissions = ref<string[]>([])
const selectedStudyProgramIds = ref<number[]>([])

const studyPrograms = computed(() => {
    if (!selectedFacultyId.value) return []
    const faculty = props.faculties.find((f) => f.id === selectedFacultyId.value)
    return faculty?.study_programs || []
})

const generateTempId = () => `temp_${Date.now()}_${Math.random()}`

const addAccessControl = () => {
    accessControls.value.push({
        permission: '',
        study_program_id: 0,
        temp_id: generateTempId(),
    })
}

const removeAccessControl = (index: number) => {
    accessControls.value.splice(index, 1)
}

const bulkAddAccessControls = () => {
    selectedPermissions.value.forEach((permission) => {
        selectedStudyProgramIds.value.forEach((studyProgramId) => {
            const exists = accessControls.value.some(
                (ac) => ac.permission === permission && ac.study_program_id === studyProgramId
            )

            if (!exists) {
                accessControls.value.push({
                    permission,
                    study_program_id: studyProgramId,
                    temp_id: generateTempId(),
                })
            }
        })
    })

    selectedPermissions.value = []
    selectedStudyProgramIds.value = []
    selectedFacultyId.value = null
}

const togglePermission = (permission: string, checked?: boolean | 'indeterminate'): void => {
    const isCurrentlySelected = selectedPermissions.value.includes(permission)

    const shouldBeChecked =
        checked === 'indeterminate'
            ? true
            : typeof checked === 'boolean'
              ? checked
              : !isCurrentlySelected

    if (shouldBeChecked) {
        if (!isCurrentlySelected) {
            selectedPermissions.value.push(permission)
        }
    } else {
        selectedPermissions.value = selectedPermissions.value.filter((p) => p !== permission)
    }
}

const toggleStudyProgram = (studyProgramId: number, checked?: boolean | 'indeterminate'): void => {
    const isCurrentlySelected = selectedStudyProgramIds.value.includes(studyProgramId)

    const shouldBeChecked =
        checked === 'indeterminate'
            ? true
            : typeof checked === 'boolean'
              ? checked
              : !isCurrentlySelected

    if (shouldBeChecked) {
        if (!isCurrentlySelected) {
            selectedStudyProgramIds.value.push(studyProgramId)
        }
    } else {
        selectedStudyProgramIds.value = selectedStudyProgramIds.value.filter(
            (id) => id !== studyProgramId
        )
    }
}

const selectAllPermissions = () => {
    if (selectedPermissions.value.length === props.permissions.length) {
        selectedPermissions.value = []
    } else {
        selectedPermissions.value = [...props.permissions]
    }
}

const selectAllStudyPrograms = () => {
    if (selectedStudyProgramIds.value.length === studyPrograms.value.length) {
        selectedStudyProgramIds.value = []
    } else {
        selectedStudyProgramIds.value = studyPrograms.value.map((sp) => sp.id)
    }
}

const getStudyProgramInfo = (studyProgramId: number) => {
    for (const faculty of props.faculties) {
        const studyProgram = faculty.study_programs.find((sp) => sp.id === studyProgramId)
        if (studyProgram) {
            return {
                name: studyProgram.name,
                faculty: faculty.name,
            }
        }
    }
    return { name: 'Unknown', faculty: 'Unknown' }
}

watch(selectedFacultyId, () => {
    selectedStudyProgramIds.value = []
})
</script>

<template>
    <div class="space-y-6">
        <!-- Bulk Add Section -->
        <Card class="border-blue-200 bg-blue-50">
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-blue-900">
                    <Plus class="h-5 w-5" />
                    Tambah Kontrol Akses Massal
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <!-- Faculty Selection -->
                <div class="space-y-2">
                    <Label>Pilih Fakultas</Label>
                    <Select v-model="selectedFacultyId">
                        <SelectTrigger>
                            <SelectValue placeholder="Pilih fakultas" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="faculty in faculties"
                                :key="faculty.id"
                                :value="faculty.id"
                            >
                                {{ faculty.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Study Programs Multi-select -->
                <div v-if="selectedFacultyId" class="space-y-2">
                    <div class="flex items-center justify-between">
                        <Label>Program Studi *</Label>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="selectAllStudyPrograms"
                        >
                            {{
                                selectedStudyProgramIds.length === studyPrograms.length
                                    ? 'Batal Pilih Semua'
                                    : 'Pilih Semua'
                            }}
                        </Button>
                    </div>
                    <div
                        class="grid gap-2 md:grid-cols-2 max-h-48 overflow-y-auto p-2 border rounded bg-white"
                    >
                        <div
                            v-for="studyProgram in studyPrograms"
                            :key="studyProgram.id"
                            class="flex items-center space-x-2"
                        >
                            <Checkbox
                                :model-value="selectedStudyProgramIds.includes(studyProgram.id)"
                                @update:model-value="
                                    (val) => toggleStudyProgram(studyProgram.id, val)
                                "
                            />
                            <Label
                                class="text-sm cursor-pointer"
                                @click="toggleStudyProgram(studyProgram.id)"
                            >
                                {{ studyProgram.name }}
                            </Label>
                        </div>
                    </div>
                </div>

                <!-- Permissions Multi-select -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <Label>Permission *</Label>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="selectAllPermissions"
                        >
                            {{
                                selectedPermissions.length === permissions.length
                                    ? 'Batal Pilih Semua'
                                    : 'Pilih Semua'
                            }}
                        </Button>
                    </div>
                    <div class="grid gap-2 md:grid-cols-2 p-2 border rounded bg-white">
                        <div
                            v-for="permission in permissions"
                            :key="permission"
                            class="flex items-center space-x-2"
                        >
                            <Checkbox
                                :model-value="selectedPermissions.includes(permission)"
                                @update:model-value="(val) => togglePermission(permission, val)"
                            />
                            <Label class="cursor-pointer" @click="togglePermission(permission)">
                                {{ permission }}
                            </Label>
                        </div>
                    </div>
                </div>

                <Button
                    type="button"
                    :disabled="
                        selectedPermissions.length === 0 || selectedStudyProgramIds.length === 0
                    "
                    class="w-full"
                    @click="bulkAddAccessControls"
                >
                    <Plus class="h-4 w-4 mr-2" />
                    Tambah {{ selectedPermissions.length }} Permission ×
                    {{ selectedStudyProgramIds.length }} Program Studi
                </Button>
            </CardContent>
        </Card>

        <!-- Current Access Controls -->
        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <CardTitle class="flex items-center gap-2">
                        Kontrol Akses
                        <Badge variant="secondary">
                            {{ accessControls.length }}
                        </Badge>
                    </CardTitle>
                    <Button type="button" size="sm" variant="outline" @click="addAccessControl">
                        <Plus class="h-4 w-4 mr-2" />
                        Tambah Satu
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                <div v-if="accessControls.length === 0" class="text-center py-12">
                    <Users class="h-12 w-12 mx-auto text-muted-foreground mb-4 opacity-50" />
                    <h3 class="text-lg font-medium mb-2">Tidak Ada Kontrol Akses</h3>
                    <p class="text-muted-foreground mb-4">
                        Tambahkan kontrol akses untuk menentukan siapa yang dapat mengakses formulir
                        ini.
                    </p>
                </div>

                <div v-else class="space-y-3">
                    <Card
                        v-for="(control, index) in accessControls"
                        :key="control.temp_id"
                        class="border"
                    >
                        <CardContent class="p-4">
                            <div class="flex items-center gap-4">
                                <div class="flex-1 grid gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label>Permission *</Label>
                                        <Select v-model="control.permission">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Pilih permission" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="permission in permissions"
                                                    :key="permission"
                                                    :value="permission"
                                                >
                                                    {{ permission }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div class="space-y-2">
                                        <Label>Program Studi *</Label>
                                        <Select v-model="control.study_program_id">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Pilih program studi" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <template
                                                    v-for="faculty in faculties"
                                                    :key="faculty.id"
                                                >
                                                    <div
                                                        class="px-2 py-1.5 text-sm font-semibold text-muted-foreground bg-muted"
                                                    >
                                                        {{ faculty.name }}
                                                    </div>
                                                    <SelectItem
                                                        v-for="sp in faculty.study_programs"
                                                        :key="sp.id"
                                                        :value="sp.id"
                                                        class="pl-6"
                                                    >
                                                        {{ sp.name }}
                                                    </SelectItem>
                                                </template>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="text-destructive"
                                    @click="removeAccessControl(index)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>

                            <!-- Preview -->
                            <div
                                v-if="control.permission && control.study_program_id"
                                class="mt-3 pt-3 border-t"
                            >
                                <div class="flex items-center gap-2 text-sm">
                                    <Users class="h-4 w-4 text-muted-foreground" />
                                    <span class="font-medium">{{ control.permission }}</span>
                                    <span class="text-muted-foreground">dapat mengakses dari</span>
                                    <Building class="h-4 w-4 text-muted-foreground" />
                                    <span class="font-medium">{{
                                        getStudyProgramInfo(control.study_program_id).name
                                    }}</span>
                                    <span class="text-xs text-muted-foreground">
                                        ({{
                                            getStudyProgramInfo(control.study_program_id).faculty
                                        }})
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <p v-if="errors.access_controls" class="text-sm text-destructive mt-2">
                    {{ errors.access_controls }}
                </p>
            </CardContent>
        </Card>
    </div>
</template>
