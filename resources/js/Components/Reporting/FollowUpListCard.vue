<script setup lang="ts">
// W152 — satu daftar tindakan berikutnya: jumlah, maks lima terlama, tautan ke tempat aksinya.
import { Link } from '@inertiajs/vue3'
import { ArrowRight } from 'lucide-vue-next'

import SectionCard from '@/Components/app/SectionCard.vue'
import { Button } from '@/Components/ui/button'
import { formatSinceSubmit } from '@/lib/statistics'
import type { FollowUpList } from '@/types/reporting'

defineProps<{ list: FollowUpList }>()
</script>

<template>
    <SectionCard :title="list.title" :description="list.hint">
        <template #actions>
            <span class="text-2xl font-semibold tabular-nums">{{ list.count }}</span>
        </template>
        <p v-if="list.count === 0" class="text-sm text-muted-foreground">
            Tidak ada yang menunggu.
        </p>
        <ul v-else-if="list.items.length > 0" class="flex flex-col">
            <li v-for="item in list.items" :key="item.id">
                <Link
                    :href="item.href"
                    class="flex flex-col rounded-md px-2 py-1.5 text-sm hover:bg-accent/40 focus-visible:outline-2"
                >
                    <span class="truncate font-medium">{{ item.title }}</span>
                    <span class="text-muted-foreground">
                        {{
                            [item.leader, item.scheme, formatSinceSubmit(item.ageDays)]
                                .filter(Boolean)
                                .join(' · ')
                        }}
                    </span>
                </Link>
            </li>
        </ul>
        <Button v-if="list.href" as-child variant="ghost" size="sm" class="self-start">
            <Link :href="list.href">Lihat semua <ArrowRight class="size-4" /></Link>
        </Button>
    </SectionCard>
</template>
