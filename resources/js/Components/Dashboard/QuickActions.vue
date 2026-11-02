<script setup lang="ts">
// Frame: SHARED-02-01..04
import { Link } from '@inertiajs/vue3'

import SectionCard from '@/Components/app/SectionCard.vue'
import { Button } from '@/Components/ui/button'
import type { QuickAction } from '@/lib/dashboardActions'

defineProps<{ actions: QuickAction[] }>()
</script>

<template>
    <SectionCard title="Aksi Cepat">
        <div class="flex flex-wrap gap-2">
            <template v-for="action in actions" :key="action.label">
                <!-- Aksi tertahan gerbang backend tetap terlihat, nonaktif, dengan alasannya. -->
                <Button
                    v-if="action.disabledReason"
                    :variant="action.primary ? 'default' : 'outline'"
                    disabled
                    :title="action.disabledReason"
                    :aria-description="action.disabledReason"
                >
                    <component :is="action.icon" class="size-4" />
                    {{ action.label }}
                </Button>
                <Button v-else :variant="action.primary ? 'default' : 'outline'" as-child>
                    <Link :href="route(action.routeName, action.params)">
                        <component :is="action.icon" class="size-4" />
                        {{ action.label }}
                    </Link>
                </Button>
            </template>
        </div>
    </SectionCard>
</template>
