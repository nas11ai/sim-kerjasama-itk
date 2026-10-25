<script setup lang="ts">
import SectionCard from '@/Components/app/SectionCard.vue'
import { Badge } from '@/Components/ui/badge'
import type { SubmissionMember } from '@/types/submission-page'

defineProps<{ members: SubmissionMember[] }>()
</script>

<template>
    <SectionCard title="Anggota Penelitian">
        <ul class="divide-y rounded-md border text-sm">
            <li
                v-for="(member, index) in members"
                :key="`${index}-${member.name}`"
                class="flex items-center justify-between gap-2 p-3"
            >
                <span class="font-medium">{{ member.name }}</span>
                <Badge variant="outline">{{ member.roleLabel }}</Badge>
            </li>
        </ul>
    </SectionCard>
</template>
