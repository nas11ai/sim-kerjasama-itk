<script setup lang="ts">
import { Plus, X } from 'lucide-vue-next'

import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import type { EditableOption } from '@/lib/formDefinition'

defineProps<{ error?: string }>()
const options = defineModel<EditableOption[]>({ required: true })

const add = () => (options.value = [...options.value, { id: null, label: '' }])
const remove = (index: number) => (options.value = options.value.filter((_, i) => i !== index))
</script>

<template>
    <fieldset class="flex flex-col gap-2">
        <legend class="mb-1.5 text-sm font-medium">Opsi</legend>
        <div v-for="(option, index) in options" :key="index" class="flex items-center gap-2">
            <Input v-model="option.label" :aria-label="`Opsi ${index + 1}`" maxlength="255" />
            <Button
                type="button"
                variant="ghost"
                size="icon"
                :aria-label="`Buang opsi ${index + 1}`"
                @click="remove(index)"
            >
                <X class="size-4" />
            </Button>
        </div>
        <p v-if="error" class="text-xs text-destructive">{{ error }}</p>
        <div>
            <Button type="button" variant="outline" size="sm" @click="add">
                <Plus class="size-4" />Tambah Opsi
            </Button>
        </div>
        <!-- BR-FE-24 / FE-CHANGE-11, 12 — membuang opsi selalu soft delete (W84) -->
        <p class="text-xs text-muted-foreground">
            Opsi yang dibuang berhenti ditawarkan untuk jawaban baru; jawaban lama yang memakainya
            tetap terbaca.
        </p>
    </fieldset>
</template>
