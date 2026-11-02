<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Plus } from 'lucide-vue-next'

import FormField from '@/Components/app/FormField.vue'
import OptionSelect from '@/Components/app/OptionSelect.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import type { InvitationRowData, Option } from '@/types/identity'

// BR-IAM-16, W51 — hak akses dipilih dari preset, bukan daftar permission bebas.
const props = defineProps<{ organizationOptions: Option[]; presetOptions: Option[] }>()

const defaultPreset = () => props.presetOptions[0]?.value ?? null

type InvitationFields = {
    organization_id: string | null
    preset: string | null
    max_uses: string
    expires_at: string
}

const form = useForm<InvitationFields>({
    organization_id: null,
    preset: defaultPreset(),
    max_uses: '',
    expires_at: '',
})

const send = () =>
    form.post(route('admin.invitations.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    })

// "Buat Ulang" — organisasi dan preset baris lama; kuota dan tanggal diisi ulang Operator.
const refill = (row: InvitationRowData) => {
    form.organization_id = row.organizationId === null ? null : String(row.organizationId)
    form.preset = row.preset?.value ?? defaultPreset()
}

defineExpose({ refill })
</script>

<template>
    <SectionCard title="Buat Undangan Baru">
        <form class="flex flex-col gap-4" @submit.prevent="send">
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField
                    id="invitation-organization"
                    label="Organisasi Tujuan"
                    required
                    :error="form.errors.organization_id"
                >
                    <template #default="{ describedBy }">
                        <OptionSelect
                            id="invitation-organization"
                            v-model="form.organization_id"
                            :options="organizationOptions"
                            placeholder="Pilih unit organisasi..."
                            :described-by="describedBy"
                        />
                    </template>
                </FormField>
                <FormField
                    id="invitation-preset"
                    label="Hak Akses"
                    required
                    :error="form.errors.preset"
                >
                    <template #default="{ describedBy }">
                        <OptionSelect
                            id="invitation-preset"
                            v-model="form.preset"
                            :options="presetOptions"
                            :described-by="describedBy"
                        />
                    </template>
                </FormField>
                <FormField
                    id="invitation-max-uses"
                    label="Maks. Pemakaian"
                    required
                    :error="form.errors.max_uses"
                >
                    <template #default="{ describedBy }">
                        <Input
                            id="invitation-max-uses"
                            v-model="form.max_uses"
                            type="number"
                            min="1"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>
                <FormField
                    id="invitation-expires-at"
                    label="Berlaku Sampai"
                    required
                    :error="form.errors.expires_at"
                >
                    <template #default="{ describedBy }">
                        <Input
                            id="invitation-expires-at"
                            v-model="form.expires_at"
                            type="date"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>
            </div>
            <div>
                <Button type="submit" :disabled="form.processing"
                    ><Plus class="size-4" />Buat Undangan</Button
                >
            </div>
        </form>
    </SectionCard>
</template>
