<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

import { Button } from '@/Components/ui/button'
import type { ActionItem } from '@/types/action'

defineProps<{ actions: ActionItem[] }>()
</script>

<template>
    <div class="flex flex-col gap-4">
        <div v-for="action in actions" :key="action.key" class="flex flex-col gap-1">
            <Button
                v-if="action.available && action.href"
                as-child
                variant="outline"
                class="w-full"
            >
                <Link :href="action.href">
                    <component :is="action.icon" v-if="action.icon" class="size-4" />
                    {{ action.label }}
                </Link>
            </Button>
            <Button
                v-else
                variant="outline"
                class="w-full"
                :disabled="!action.available"
                @click="action.onSelect?.()"
            >
                <component :is="action.icon" v-if="action.icon" class="size-4" />
                {{ action.label }}
            </Button>
            <p class="text-xs text-muted-foreground">{{ action.hint }}</p>
        </div>
    </div>
</template>
