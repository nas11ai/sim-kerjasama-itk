<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import { Plus } from 'lucide-vue-next'
import { computed, onBeforeUnmount, ref, watch } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import FormField from '@/Components/app/FormField.vue'
import OptionSelect from '@/Components/app/OptionSelect.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import type { FilterOption } from '@/types/submission-page'
import type { MemberCandidate } from '@/types/submission-wizard'

const SEARCH_DEBOUNCE_MS = 300

// Kandidat dimuat lewat partial reload `memberCandidates`, bukan endpoint JSON.
const props = defineProps<{
    submissionId: number
    roleOptions: FilterOption[]
    candidates: MemberCandidate[] | undefined
    disabled: boolean
}>()

const page = usePage()
const search = ref('')
const role = ref<string | null>(props.roleOptions[0]?.value ?? null)
const rejection = computed(() => page.props.errors?.user_id ?? page.props.errors?.role)
let timer: ReturnType<typeof setTimeout> | undefined

watch(search, (value) => {
    clearTimeout(timer)
    timer = setTimeout(
        () =>
            router.reload({
                only: ['memberCandidates'],
                data: { member_search: value },
            }),
        SEARCH_DEBOUNCE_MS
    )
})
onBeforeUnmount(() => clearTimeout(timer))

const add = (candidate: MemberCandidate) =>
    router.post(
        route('user.submissions.members.store', String(props.submissionId)),
        { user_id: candidate.id, role: role.value },
        { preserveScroll: true, onSuccess: () => (search.value = '') }
    )
</script>

<template>
    <SectionCard title="Cari & Tambah Anggota">
        <div class="grid gap-3 sm:grid-cols-[1fr_14rem]">
            <FormField id="member-search" label="Cari dosen">
                <Input
                    id="member-search"
                    v-model="search"
                    type="search"
                    placeholder="Cari nama atau NIDN..."
                    :disabled="disabled"
                />
            </FormField>
            <FormField id="member-role" label="Peran">
                <OptionSelect id="member-role" v-model="role" :options="roleOptions" />
            </FormField>
        </div>
        <AppBanner v-if="rejection" tone="denial">{{ rejection }}</AppBanner>
        <ul v-if="candidates?.length" class="flex flex-col divide-y rounded-md border">
            <li v-for="candidate in candidates" :key="candidate.id" class="flex flex-col gap-2 p-3">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm">
                        {{ candidate.name }}
                        <span class="text-muted-foreground">{{ candidate.nidn ?? '-' }}</span>
                    </span>
                    <Button
                        size="sm"
                        data-action="add-member"
                        :disabled="disabled || role === null || candidate.blockedReason !== null"
                        @click="add(candidate)"
                    >
                        <Plus class="size-4" /> Tambah
                    </Button>
                </div>
                <!-- BR-SM-08, BR-REV-02 -->
                <AppBanner v-if="candidate.blockedReason" tone="denial">
                    {{ candidate.blockedReason }}
                </AppBanner>
            </li>
        </ul>
        <p v-else-if="candidates && search.length >= 2" class="text-sm text-muted-foreground">
            Tidak ada dosen yang cocok.
        </p>
    </SectionCard>
</template>
