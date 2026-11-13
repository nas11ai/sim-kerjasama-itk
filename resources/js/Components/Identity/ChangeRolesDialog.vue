<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { computed, watch } from 'vue'

import ActionDialog from '@/Components/app/ActionDialog.vue'
import { Checkbox } from '@/Components/ui/checkbox'
import { Label } from '@/Components/ui/label'
import type { Option } from '@/types/identity'

// W57 — hanya peran ASSIGNABLE yang bisa dicentang; peran reviewer dan legacy tetap seperti adanya.
const props = defineProps<{ userId: number; roles: Option[]; assignableRoles: Option[] }>()
const open = defineModel<boolean>('open', { required: true })

const assignable = computed(() => props.assignableRoles.map((role) => role.value))
const current = () => props.roles.map((r) => r.value).filter((r) => assignable.value.includes(r))
const untouched = computed(() =>
    props.roles.filter((role) => !assignable.value.includes(role.value)).map((r) => r.label)
)

const form = useForm<{ roles: string[] }>({ roles: current() })
watch(open, (isOpen) => {
    if (!isOpen) return
    form.roles = current()
    form.clearErrors()
})

const toggle = (role: string, checked: boolean | 'indeterminate') => {
    form.roles = checked === true ? [...form.roles, role] : form.roles.filter((r) => r !== role)
}

const send = () =>
    form.put(route('admin.users.roles.update', props.userId), {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    })
</script>

<template>
    <ActionDialog
        v-model:open="open"
        title="Ubah Peran"
        description="Peran penilai diberikan lewat penunjukan penilai."
        submit-label="Simpan Peran"
        :processing="form.processing"
        @submit="send"
    >
        <div class="flex flex-col gap-2">
            <div v-for="role in assignableRoles" :key="role.value" class="flex items-center gap-2">
                <Checkbox
                    :id="`assign-role-${role.value}`"
                    :model-value="form.roles.includes(role.value)"
                    @update:model-value="(checked) => toggle(role.value, checked)"
                />
                <Label :for="`assign-role-${role.value}`" class="font-normal">{{
                    role.label
                }}</Label>
            </div>
        </div>
        <p v-if="untouched.length > 0" class="text-xs text-muted-foreground">
            Tidak diubah di sini: {{ untouched.join(', ') }}
        </p>
        <p v-if="form.errors.roles" class="text-xs text-destructive">{{ form.errors.roles }}</p>
    </ActionDialog>
</template>
