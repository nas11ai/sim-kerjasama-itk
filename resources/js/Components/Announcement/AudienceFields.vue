<script setup lang="ts">
import { watch } from 'vue'

import { Checkbox } from '@/Components/ui/checkbox'
import { Label } from '@/Components/ui/label'
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group'
import type { FilterOption } from '@/types/submission-page'

// fe-v2-design §4.7 — publik tanpa target; privat boleh dipersempit per peran.
defineProps<{ roleOptions: FilterOption[]; errors: Partial<Record<string, string>> }>()

const type = defineModel<string>('type', { required: true })
const targetRoles = defineModel<string[]>('targetRoles', { required: true })

// Publik tidak boleh bertarget; dikosongkan apa pun sumber perubahan jenisnya.
watch(type, (value) => {
    if (value === 'public') {
        targetRoles.value = []
    }
})

function changeType(value: unknown) {
    type.value = String(value)
}

function toggleRole(role: string, checked: boolean | 'indeterminate') {
    const others = targetRoles.value.filter((name) => name !== role)
    targetRoles.value = checked === true ? [...others, role] : others
}
</script>

<template>
    <fieldset class="space-y-3">
        <legend class="text-sm font-medium">Sasaran</legend>
        <RadioGroup :model-value="type" class="flex gap-6" @update:model-value="changeType">
            <label class="flex items-center gap-2 text-sm">
                <RadioGroupItem value="public" /> Publik
            </label>
            <label class="flex items-center gap-2 text-sm">
                <RadioGroupItem value="private" /> Privat
            </label>
        </RadioGroup>
        <p v-if="errors.type" class="text-xs text-destructive">{{ errors.type }}</p>
        <div v-if="type === 'private'" class="space-y-2 rounded-md border p-3">
            <p class="text-xs text-muted-foreground">
                Kosongkan untuk semua pengguna yang sudah masuk.
            </p>
            <div class="flex flex-wrap gap-x-6 gap-y-2">
                <div
                    v-for="option in roleOptions"
                    :key="option.value"
                    class="flex items-center gap-2"
                >
                    <Checkbox
                        :id="`role-${option.value}`"
                        :model-value="targetRoles.includes(option.value)"
                        @update:model-value="(checked) => toggleRole(option.value, checked)"
                    />
                    <Label :for="`role-${option.value}`" class="font-normal">{{
                        option.label
                    }}</Label>
                </div>
            </div>
        </div>
        <p v-if="errors.target_roles" class="text-xs text-destructive">
            {{ errors.target_roles }}
        </p>
    </fieldset>
</template>
