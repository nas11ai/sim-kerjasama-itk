<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3'
import { ArrowRight, Plus, ShieldCheck, Trash2 } from 'lucide-vue-next'
import { computed, ref } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import DataTable from '@/Components/app/DataTable.vue'
import EmptyState from '@/Components/app/EmptyState.vue'
import FormField from '@/Components/app/FormField.vue'
import OptionSelect from '@/Components/app/OptionSelect.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import { Button } from '@/Components/ui/button'
import type { FormAccessRuleData, FormHeaderData } from '@/types/form-engine'
import type { FilterOption } from '@/types/submission-page'

const props = defineProps<{
    form: FormHeaderData
    rules: FormAccessRuleData[]
    permissionOptions: FilterOption[]
    organizationOptions: FilterOption[]
}>()

const columns = [
    { key: 'permission', label: 'Permission' },
    { key: 'organization', label: 'Organisasi Diizinkan' },
    { key: 'action', label: '' },
]

const grant = useForm<{ permission: string | null; organization_id: string | null }>({
    permission: props.permissionOptions[0]?.value ?? null,
    organization_id: null,
})

const add = () =>
    grant.post(route('admin.forms.access.store', props.form.id), {
        preserveScroll: true,
        onSuccess: () => grant.reset('organization_id'),
    })

// `duplicate` bukan kolom isian (BR-FE-02), jadi dibaca dari seluruh galat.
const duplicate = computed(
    () => Object.entries(grant.errors).find(([key]) => key === 'duplicate')?.[1]
)

const target = ref<FormAccessRuleData | null>(null)
const confirming = ref(false)
const removing = ref(false)
const removeError = ref('')

const request = (rule: FormAccessRuleData) => {
    target.value = rule
    removeError.value = ''
    confirming.value = true
}

// BR-FE-20 — kelayakan hapus dari backend; penolakan balik lewat galat `id`.
const remove = () => {
    if (!target.value) return
    router.delete(
        route('admin.forms.access.destroy', {
            form: props.form.id,
            formAccessControl: target.value.id,
        }),
        {
            preserveScroll: true,
            onStart: () => (removing.value = true),
            onSuccess: () => (confirming.value = false),
            onError: (errors) => {
                removeError.value = errors.id ?? ''
                confirming.value = false
            },
            onFinish: () => (removing.value = false),
        }
    )
}
</script>

<template>
    <div class="max-w-3xl space-y-6">
        <form @submit.prevent="add">
            <SectionCard title="Tambah Aturan Akses">
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="access-permission"
                        label="Permission"
                        required
                        :error="grant.errors.permission"
                    >
                        <OptionSelect
                            id="access-permission"
                            v-model="grant.permission"
                            :options="permissionOptions"
                        />
                    </FormField>
                    <FormField
                        id="access-organization"
                        label="Organisasi"
                        required
                        :error="grant.errors.organization_id"
                    >
                        <OptionSelect
                            id="access-organization"
                            v-model="grant.organization_id"
                            :options="organizationOptions"
                            placeholder="Pilih unit organisasi..."
                        />
                    </FormField>
                </div>
                <AppBanner v-if="duplicate" tone="denial">{{ duplicate }}</AppBanner>
                <div>
                    <Button type="submit" :disabled="grant.processing"
                        ><Plus class="size-4" />Tambah Aturan</Button
                    >
                </div>
            </SectionCard>
        </form>
        <AppBanner v-if="removeError" tone="denial">{{ removeError }}</AppBanner>
        <DataTable :rows="rules" :columns="columns">
            <template #cell-permission="{ row }">
                <span class="font-medium">{{ row.permissionLabel }}</span>
                <code class="block text-xs text-muted-foreground">{{ row.permission }}</code>
            </template>
            <template #cell-organization="{ row }">{{ row.organizationLabel }}</template>
            <template #cell-action="{ row }">
                <div class="flex items-center justify-end gap-2">
                    <!-- BR-FE-20 — aturan yang dipakai detail tahap tidak bisa dihapus -->
                    <span v-if="row.phaseDetailCount > 0" class="text-xs text-muted-foreground"
                        >Dipakai {{ row.phaseDetailCount }} detail tahap</span
                    >
                    <Button
                        size="sm"
                        variant="ghost"
                        type="button"
                        :disabled="row.phaseDetailCount > 0"
                        @click="request(row)"
                        ><Trash2 class="size-4" />Hapus</Button
                    >
                </div>
            </template>
            <template #empty>
                <EmptyState :icon="ShieldCheck" title="Belum ada aturan akses" />
            </template>
        </DataTable>
        <p class="text-xs text-muted-foreground">
            Form ini hanya dapat dipakai oleh peneliti dari organisasi yang terdaftar di sini.
            Menghapus aturan memerlukan konfirmasi.
        </p>
        <Button as-child variant="outline">
            <Link :href="route('admin.phases.index', { form: form.id })"
                >Lanjut: Phases<ArrowRight class="size-4"
            /></Link>
        </Button>
        <ConfirmDialog
            v-model:open="confirming"
            title="Hapus aturan akses?"
            description="Peneliti yang hanya terjangkau lewat aturan ini tidak lagi melihat form ini."
            confirm-label="Hapus Aturan"
            destructive
            :processing="removing"
            @confirm="remove"
        />
    </div>
</template>
