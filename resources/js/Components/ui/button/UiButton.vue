<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { Primitive, type PrimitiveProps } from 'reka-ui'
import { cn } from '@/lib/utils'
import { type ButtonVariants, buttonVariants } from '.'

interface Props extends PrimitiveProps {
    variant?: ButtonVariants['variant']
    size?: ButtonVariants['size']
    class?: HTMLAttributes['class']
    disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    variant: 'default',
    size: 'default',
    class: '',
    as: 'button',
    disabled: false,
})
</script>

<template>
    <Primitive
        :as="as"
        :as-child="asChild"
        :disabled="disabled"
        :class="
            cn(
                buttonVariants({ variant, size }),
                disabled ? 'opacity-50 cursor-not-allowed pointer-events-none' : '',
                props.class
            )
        "
    >
        <slot />
    </Primitive>
</template>
