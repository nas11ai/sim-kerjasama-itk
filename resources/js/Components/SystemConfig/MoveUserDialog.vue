<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

import ActionDialog from '@/Components/app/ActionDialog.vue'
import AppBanner from '@/Components/app/AppBanner.vue'
import EmptyState from '@/Components/app/EmptyState.vue'
import FormField from '@/Components/app/FormField.vue'
import OptionSelect from '@/Components/app/OptionSelect.vue'
import { Input } from '@/Components/ui/input'
import { usePartialSearch } from '@/composables/usePartialSearch'
import type { FilterOption } from '@/types/submission-page'
import type { MemberCandidate } from '@/types/system-config'

// BR-IAM-18 — prasyarat deaktivasi BR-IAM-14 (W120). Kandidat = anggota subtree unit terpilih;
// `unit` ikut dari URL halaman, jadi partial reload cukup membawa kata cariannya.
const props = defineProps<{ candidates?: MemberCandidate[]; parentOptions: FilterOption[] }>()
const open = defineModel<boolean>('open', { required: true })

const form = useForm<{ user_id: number | null }>({ user_id: null })
const target = ref<string | null>(null)
const { search, load } = usePartialSearch('memberCandidates', 'member_search')
watch(open, (isOpen) => isOpen && load(), { immediate: true })

const people = computed(() => props.candidates ?? [])
const chosen = computed(() => people.value.find((c) => Number(c.value) === form.user_id) ?? null)
// Unit asal user bukan tujuan; MoveUserToOrganization juga menolak tujuan nonaktif.
const targets = computed(() =>
    props.parentOptions.filter((o) => o.value !== String(chosen.value?.organizationId))
)
const errors = computed(() => Object.values(form.errors))

const pick = (candidate: MemberCandidate) => {
    form.user_id = Number(candidate.value)
    if (target.value === String(candidate.organizationId)) target.value = null
}

const send = () => {
    if (target.value === null) return
    form.post(route('admin.organizations.move-user', target.value), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false
            target.value = null
            form.reset()
        },
        // Respons galat penuh tak membawa prop opsional memberCandidates; muat ulang daftarnya.
        onError: load,
    })
}
</script>

<template>
    <ActionDialog
        v-model:open="open"
        title="Pindahkan Pengguna"
        description="Riwayat pengajuan pengguna tetap terbaca setelah ia pindah unit."
        submit-label="Pindahkan"
        :processing="form.processing"
        :submit-disabled="form.user_id === null || target === null"
        @submit="send"
    >
        <FormField id="move-user-search" label="Pengguna">
            <Input
                id="move-user-search"
                v-model="search"
                type="search"
                placeholder="Cari nama / email pengguna..."
            />
        </FormField>
        <EmptyState v-if="people.length === 0" title="Tidak ada anggota yang cocok." />
        <div
            v-else
            role="listbox"
            aria-label="Anggota unit"
            class="flex max-h-60 flex-col gap-1 overflow-y-auto"
        >
            <button
                v-for="person in people"
                :key="person.value"
                type="button"
                role="option"
                :aria-selected="form.user_id === Number(person.value)"
                class="flex flex-col rounded-md border px-3 py-2 text-left text-sm hover:bg-accent aria-selected:border-primary aria-selected:bg-accent"
                @click="pick(person)"
            >
                <span class="font-medium">{{ person.label }}</span>
                <span class="text-xs text-muted-foreground">{{ person.hint }}</span>
            </button>
        </div>
        <FormField id="move-user-target" label="Unit tujuan" required>
            <template #default="{ describedBy }">
                <OptionSelect
                    id="move-user-target"
                    v-model="target"
                    :options="targets"
                    :described-by="describedBy"
                />
            </template>
        </FormField>
        <AppBanner v-for="message in errors" :key="message" tone="denial">{{ message }}</AppBanner>
    </ActionDialog>
</template>
