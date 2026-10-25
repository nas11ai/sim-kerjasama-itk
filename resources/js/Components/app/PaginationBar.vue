<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'

import { Button } from '@/Components/ui/button'
import type { Paginated } from '@/types/pagination'

defineProps<{ page: Paginated<unknown> }>()
</script>

<template>
    <div
        v-if="page.last_page > 1"
        class="mt-4 flex flex-wrap items-center justify-between gap-2 text-sm text-muted-foreground"
    >
        <span>Menampilkan {{ page.from }}–{{ page.to }} dari {{ page.total }}</span>
        <div class="flex gap-2">
            <Button v-if="page.prev_page_url" as-child variant="outline" size="sm">
                <Link :href="page.prev_page_url" preserve-state
                    ><ChevronLeft class="size-4" />Sebelumnya</Link
                >
            </Button>
            <Button v-if="page.next_page_url" as-child variant="outline" size="sm">
                <Link :href="page.next_page_url" preserve-state
                    >Berikutnya<ChevronRight class="size-4"
                /></Link>
            </Button>
        </div>
    </div>
</template>
