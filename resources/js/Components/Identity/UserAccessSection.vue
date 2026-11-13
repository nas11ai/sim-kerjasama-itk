<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { X } from 'lucide-vue-next'
import { ref } from 'vue'

import OptionSelect from '@/Components/app/OptionSelect.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import PermissionHint from '@/Components/Identity/PermissionHint.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import type { UserAccessData } from '@/types/identity'

// BR-IAM-09 — hak akses langsung berdiri sendiri: yang dicabut di sini hanya yang langsung,
// sedangkan daftar efektif (gabungan, IAM-PERM-03) hanya dibaca.
const props = defineProps<{ userId: number; access: UserAccessData; canManage: boolean }>()

const picked = ref<string | null>(null)
const processing = ref(false)
const visit = { preserveScroll: true, onFinish: () => (processing.value = false) }

const grant = () => {
    if (picked.value === null) {
        return
    }
    processing.value = true
    router.post(
        route('admin.users.permissions.grant', props.userId),
        { permission: picked.value },
        { ...visit, onSuccess: () => (picked.value = null) }
    )
}

const revoke = (permission: string) => {
    processing.value = true
    router.delete(route('admin.users.permissions.revoke', props.userId), {
        ...visit,
        data: { permission },
    })
}
</script>

<template>
    <SectionCard title="Role & Hak Akses">
        <template #actions><slot name="actions" /></template>
        <div class="flex flex-wrap gap-2">
            <Badge v-for="role in access.roles" :key="role.value" variant="outline">{{
                role.label
            }}</Badge>
            <span v-if="access.roles.length === 0" class="text-sm text-muted-foreground"
                >Tanpa role.</span
            >
        </div>
        <div>
            <h3 class="mb-2 text-xs text-muted-foreground">Hak Akses Tambahan</h3>
            <ul class="flex flex-col gap-1 text-sm">
                <li
                    v-for="permission in access.directPermissions"
                    :key="permission.value"
                    class="flex items-center justify-between gap-2"
                >
                    <PermissionHint
                        :label="permission.label"
                        :description="permission.description"
                        :code="permission.value"
                    />
                    <Button
                        v-if="canManage"
                        variant="ghost"
                        size="sm"
                        :disabled="processing"
                        :aria-label="`Cabut ${permission.label}`"
                        @click="revoke(permission.value)"
                        >Cabut <X class="size-4"
                    /></Button>
                </li>
                <li v-if="access.directPermissions.length === 0" class="text-muted-foreground">
                    Tidak ada.
                </li>
            </ul>
            <div v-if="canManage && access.grantablePermissions.length > 0" class="mt-2 flex gap-2">
                <OptionSelect
                    v-model="picked"
                    :options="access.grantablePermissions"
                    placeholder="Pilih hak akses…"
                />
                <Button variant="outline" :disabled="processing || picked === null" @click="grant"
                    >Tambah</Button
                >
            </div>
        </div>
        <div>
            <h3 class="mb-2 text-xs text-muted-foreground">Hak Akses Efektif</h3>
            <ul class="flex flex-col gap-1 text-sm">
                <li v-for="permission in access.effectivePermissions" :key="permission.value">
                    <PermissionHint
                        :label="permission.label"
                        :description="permission.description"
                        :code="permission.value"
                    />
                </li>
            </ul>
        </div>
        <p class="text-xs text-muted-foreground">
            Hak akses dari role dan hak akses langsung berdiri sendiri — mencabut salah satunya
            tidak mencabut yang lain. Perubahan role dan hak akses hanya dapat dilakukan oleh Admin.
        </p>
    </SectionCard>
</template>
