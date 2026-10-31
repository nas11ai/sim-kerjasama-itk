<script setup lang="ts">
import { CircleAlert, CircleCheck, Info, Lock, TriangleAlert } from 'lucide-vue-next'
import { computed } from 'vue'

import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert'

// `lock` = banner gembok wireframe: tindakan sengaja ditutup aturan, bukan galat.
const props = defineProps<{
    tone: 'info' | 'warning' | 'denial' | 'success' | 'lock'
    title?: string
}>()

const icons = {
    info: Info,
    warning: TriangleAlert,
    denial: CircleAlert,
    success: CircleCheck,
    lock: Lock,
}
const icon = computed(() => icons[props.tone])
</script>

<template>
    <Alert
        :variant="tone === 'denial' ? 'destructive' : 'default'"
        :role="tone === 'denial' ? 'alert' : 'status'"
    >
        <component :is="icon" class="size-4" />
        <AlertTitle v-if="title">{{ title }}</AlertTitle>
        <AlertDescription><slot /></AlertDescription>
    </Alert>
</template>
