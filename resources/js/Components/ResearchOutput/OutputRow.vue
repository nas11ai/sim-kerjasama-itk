<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import type { OutputRow } from '@/types/research-output'

// Tanpa konteks peneliti/Operator: dipakai daftar luaran dan tab Luaran Operator.
defineProps<{ output: OutputRow }>()
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-md border p-3">
        <div class="flex min-w-0 items-start gap-3">
            <Badge variant="outline" class="shrink-0">{{ output.typeName }}</Badge>
            <div class="min-w-0">
                <p class="font-medium">{{ output.headline }}</p>
                <p v-if="output.meta" class="text-sm text-muted-foreground">{{ output.meta }}</p>
            </div>
        </div>
        <Button variant="outline" size="sm" as-child>
            <Link :href="route('research-outputs.show', output.id)">Detail</Link>
        </Button>
    </div>
</template>
