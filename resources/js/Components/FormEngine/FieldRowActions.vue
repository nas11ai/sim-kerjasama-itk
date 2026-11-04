<script setup lang="ts">
import { Pencil, Trash2 } from 'lucide-vue-next'

import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'

// Aksi baris field (form maupun form evaluasi): badge wajib, Edit, Hapus.
defineProps<{ label: string; isRequired: boolean; removeDisabled?: boolean }>()
const emit = defineEmits<{ edit: []; remove: [] }>()
</script>

<template>
    <Badge :variant="isRequired ? 'default' : 'outline'">{{
        isRequired ? 'Wajib' : 'Opsional'
    }}</Badge>
    <Button
        type="button"
        variant="ghost"
        size="sm"
        :aria-label="`Edit ${label}`"
        @click="emit('edit')"
        ><Pencil class="size-4" />Edit</Button
    >
    <Button
        type="button"
        variant="ghost"
        size="sm"
        :disabled="removeDisabled"
        :aria-label="`Hapus ${label}`"
        @click="emit('remove')"
        ><Trash2 class="size-4" />Hapus</Button
    >
</template>
