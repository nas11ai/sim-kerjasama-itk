<script setup lang="ts">
import {
    type TextControlEmits,
    type TextControlProps,
    useTextModel,
} from '@/Components/ui/useTextModel'
import { cn } from '@/lib/utils'

const props = defineProps<TextControlProps>()
const emits = defineEmits<TextControlEmits>()
const modelValue = useTextModel(props, emits)
</script>

<template>
    <textarea
        v-model="modelValue"
        :class="
            cn(
                'flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50',
                props.class
            )
        "
    />
</template>
