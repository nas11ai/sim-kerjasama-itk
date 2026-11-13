<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { UserCheck } from 'lucide-vue-next'
import { ref } from 'vue'

import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import { Button } from '@/Components/ui/button'
import type { UserDetailData } from '@/types/identity'

// BR-IAM-20 — yang pulih hanya kemampuan login; kepemilikan yang dialihkan dan penugasan
// yang sudah diganti tetap di tempatnya.
const props = defineProps<{ user: UserDetailData }>()

const confirming = ref(false)
const processing = ref(false)

const reactivate = () => {
    processing.value = true
    router.post(
        route('admin.users.reactivate', props.user.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => (confirming.value = false),
            onFinish: () => (processing.value = false),
        }
    )
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <Button variant="outline" @click="confirming = true"
            ><UserCheck class="size-4" /> Aktifkan</Button
        >
        <ConfirmDialog
            v-model:open="confirming"
            title="Aktifkan kembali akun?"
            :description="`${user.name} bisa masuk lagi. Kepemilikan yang sudah dialihkan dan penugasan yang sudah diganti tidak dikembalikan.`"
            confirm-label="Aktifkan"
            :processing="processing"
            @confirm="reactivate"
        />
    </div>
</template>
