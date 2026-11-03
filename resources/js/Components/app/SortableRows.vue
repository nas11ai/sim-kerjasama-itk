<script setup lang="ts" generic="T extends { key: string }">
import { ArrowDown, ArrowUp, GripVertical } from 'lucide-vue-next'
import draggable from 'vuedraggable'

import { Button } from '@/Components/ui/button'
import { moveItem } from '@/lib/formDefinition'

const props = defineProps<{ labelOf: (item: T) => string }>()
const items = defineModel<T[]>({ required: true })

defineSlots<{
    row(scope: { item: T; index: number }): unknown
    actions(scope: { item: T; index: number }): unknown
}>()

const shift = (index: number, by: number) =>
    (items.value = moveItem(items.value, index, index + by))
</script>

<template>
    <draggable v-model="items" tag="ul" item-key="key" handle=".drag-handle" class="divide-y">
        <template #item="{ element, index }: { element: T; index: number }">
            <li class="flex flex-wrap items-center justify-between gap-3 py-2">
                <div class="flex items-center gap-2">
                    <GripVertical
                        class="drag-handle size-4 cursor-grab text-muted-foreground"
                        aria-hidden="true"
                    />
                    <div>
                        <slot name="row" :item="element" :index="index" />
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        :disabled="index === 0"
                        :aria-label="`Naik: ${props.labelOf(element)}`"
                        @click="shift(index, -1)"
                        ><ArrowUp class="size-4"
                    /></Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        :disabled="index === items.length - 1"
                        :aria-label="`Turun: ${props.labelOf(element)}`"
                        @click="shift(index, 1)"
                        ><ArrowDown class="size-4"
                    /></Button>
                    <slot name="actions" :item="element" :index="index" />
                </div>
            </li>
        </template>
    </draggable>
</template>
