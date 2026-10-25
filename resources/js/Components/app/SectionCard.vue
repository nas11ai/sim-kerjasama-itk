<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card'

defineProps<{ title: string; description?: string }>()
</script>

<template>
    <Card>
        <CardHeader class="flex flex-row items-start justify-between gap-4">
            <div class="min-w-0">
                <CardTitle class="text-base">{{ title }}</CardTitle>
                <CardDescription v-if="description">{{ description }}</CardDescription>
            </div>
            <slot name="actions" />
        </CardHeader>
        <CardContent class="flex flex-col gap-4">
            <slot />
        </CardContent>
    </Card>
</template>
