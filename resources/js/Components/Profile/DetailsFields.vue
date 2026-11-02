<script setup lang="ts">
import FormField from '@/Components/app/FormField.vue'
import { Input } from '@/Components/ui/input'

// BR-IAM-03 — dua data tambahan berbentuk teks; dipakai Profil Saya dan Lengkapi Profil.
// Bidang Keahlian berupa teks bebas: DDD tidak punya katalognya (W62).
defineProps<{ errors: { phone?: string; expertise?: string } }>()
const phone = defineModel<string>('phone', { required: true })
const expertise = defineModel<string>('expertise', { required: true })
</script>

<template>
    <FormField id="profile-phone" label="Nomor Telepon" required :error="errors.phone">
        <template #default="{ describedBy }">
            <Input
                id="profile-phone"
                v-model="phone"
                type="tel"
                inputmode="tel"
                autocomplete="tel"
                placeholder="08xx-xxxx-xxxx"
                :aria-describedby="describedBy"
                :aria-invalid="errors.phone ? true : undefined"
            />
        </template>
    </FormField>
    <FormField id="profile-expertise" label="Bidang Keahlian" required :error="errors.expertise">
        <template #default="{ describedBy }">
            <Input
                id="profile-expertise"
                v-model="expertise"
                placeholder="Mis. Konversi Energi"
                :aria-describedby="describedBy"
                :aria-invalid="errors.expertise ? true : undefined"
            />
        </template>
    </FormField>
</template>
