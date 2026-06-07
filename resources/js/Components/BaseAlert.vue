// resources/js/Components/Alert.vue
<script setup lang="ts">
import { computed } from 'vue'
import { CheckCircle, AlertCircle, X } from 'lucide-vue-next'
import { Button } from '@/Components/ui/button'

interface Props {
    type: 'success' | 'error'
    title?: string
    message: string
    dismissible?: boolean
}

interface Emits {
    (e: 'dismiss'): void
}

const props = withDefaults(defineProps<Props>(), {
    dismissible: true,
    title: ''
})

const emit = defineEmits<Emits>()

const alertStyles = computed(() => {
    return props.type === 'success'
        ? 'bg-green-50 border-green-200 text-green-800'
        : 'bg-red-50 border-red-200 text-red-800'
})

const iconStyles = computed(() => {
    return props.type === 'success' ? 'text-green-600' : 'text-red-600'
})

const handleDismiss = () => {
    emit('dismiss')
}
</script>

<template>
    <div :class="['flex items-start gap-3 p-4 border rounded-lg shadow-xs', alertStyles]">
        <!-- Icon -->
        <div class="shrink-0 mt-0.5">
            <CheckCircle v-if="type === 'success'" class="h-5 w-5" :class="iconStyles" />
            <AlertCircle v-else class="h-5 w-5" :class="iconStyles" />
        </div>

        <!-- Content -->
        <div class="flex-1 min-w-0">
            <h4 v-if="title" class="font-medium mb-1">
                {{ title }}
            </h4>
            <p class="text-sm">
                {{ message }}
            </p>
        </div>

        <!-- Dismiss button -->
        <Button
v-if="dismissible" variant="ghost" size="sm" :class="[
            'shrink-0 p-1 h-auto hover:bg-transparent',
            type === 'success'
                ? 'text-green-600 hover:text-green-700'
                : 'text-red-600 hover:text-red-700',
        ]" @click="handleDismiss">
            <X class="h-4 w-4" />
            <span class="sr-only">Dismiss</span>
        </Button>
    </div>
</template>
