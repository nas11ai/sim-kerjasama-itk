<script setup lang="ts">
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select'
import type { RegistrableFaculty } from '@/types/auth'

defineProps<{ id: string; faculties: RegistrableFaculty[]; describedBy?: string }>()

const model = defineModel<number | null>({ required: true })

const select = (value: unknown) => {
    model.value = typeof value === 'number' ? value : null
}
</script>

<template>
    <Select :model-value="model ?? undefined" @update:model-value="select">
        <SelectTrigger :id="id" class="w-full" :aria-describedby="describedBy">
            <SelectValue placeholder="Pilih fakultas → program studi" />
        </SelectTrigger>
        <SelectContent>
            <SelectGroup v-for="faculty in faculties" :key="faculty.id">
                <SelectLabel>{{ faculty.name }}</SelectLabel>
                <SelectItem
                    v-for="studyProgram in faculty.study_programs"
                    :key="studyProgram.id"
                    :value="studyProgram.id"
                >
                    {{ studyProgram.name }}
                </SelectItem>
            </SelectGroup>
        </SelectContent>
    </Select>
</template>
