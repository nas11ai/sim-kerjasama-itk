import { useVModel } from '@vueuse/core'
import type { HTMLAttributes } from 'vue'

// Kontrak bersama UiInput dan UiTextarea (shadcn-vue): v-model pasif dengan defaultValue.
export interface TextControlProps {
    defaultValue?: string | number
    modelValue?: string | number
    class?: HTMLAttributes['class']
}

export type TextControlEmits = {
    'update:modelValue': [payload: string | number]
}

export const useTextModel = (
    props: TextControlProps,
    emit: (event: 'update:modelValue', payload: string | number) => void
) =>
    useVModel(props, 'modelValue', emit, {
        passive: true,
        defaultValue: props.defaultValue,
    })
