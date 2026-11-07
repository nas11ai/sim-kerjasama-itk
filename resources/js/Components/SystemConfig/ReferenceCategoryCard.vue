<script setup lang="ts">
import { Pencil } from 'lucide-vue-next'

import SectionCard from '@/Components/app/SectionCard.vue'
import { Button } from '@/Components/ui/button'
import type { ReferenceCategoryData } from '@/types/system-config'

defineProps<{ title: string; categories: ReferenceCategoryData[] }>()
const emit = defineEmits<{ manage: [category: ReferenceCategoryData] }>()
</script>

<template>
    <SectionCard :title="title">
        <ul class="divide-y">
            <li
                v-for="category in categories"
                :key="category.key"
                class="flex items-center justify-between gap-3 py-2 first:pt-0 last:pb-0"
            >
                <div class="min-w-0">
                    <p class="text-sm font-medium">{{ category.label }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ category.items.length }} item · dipakai oleh {{ category.usedBy }}
                    </p>
                </div>
                <Button size="sm" variant="outline" @click="emit('manage', category)"
                    ><Pencil class="size-4" />Kelola</Button
                >
            </li>
        </ul>
    </SectionCard>
</template>
