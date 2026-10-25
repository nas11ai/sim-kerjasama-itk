<script setup lang="ts">
import { Label } from '@/Components/ui/label'

// Label + kontrol + pesan. Error backend menggantikan hint supaya hanya satu pesan terbaca.
const props = defineProps<{
    id: string
    label: string
    required?: boolean
    hint?: string
    error?: string
}>()

const messageId = `${props.id}-message`
</script>

<template>
    <div class="space-y-1.5">
        <Label :for="id">
            {{ label }}
            <span v-if="required" class="text-destructive" aria-hidden="true">*</span>
        </Label>
        <slot :described-by="error || hint ? messageId : undefined" />
        <p v-if="error" :id="messageId" class="text-xs text-destructive">{{ error }}</p>
        <p v-else-if="hint" :id="messageId" class="text-xs text-muted-foreground">{{ hint }}</p>
    </div>
</template>
