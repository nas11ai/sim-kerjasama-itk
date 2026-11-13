<script setup lang="ts">
import FormField from '@/Components/app/FormField.vue'
import { Input } from '@/Components/ui/input'

// Password + konfirmasinya; Laravel melaporkan ketidakcocokan di field password.
withDefaults(
    defineProps<{ error?: string; label?: string; confirmationLabel?: string; hint?: string }>(),
    {
        error: undefined,
        hint: undefined,
        label: 'Kata Sandi',
        confirmationLabel: 'Konfirmasi Kata Sandi',
    }
)

const password = defineModel<string>('password', { required: true })
const confirmation = defineModel<string>('confirmation', { required: true })
</script>

<template>
    <FormField id="password" :label="label" :hint="hint" :error="error" required>
        <template #default="{ describedBy }">
            <Input
                id="password"
                v-model="password"
                type="password"
                autocomplete="new-password"
                :aria-describedby="describedBy"
                required
            />
        </template>
    </FormField>
    <FormField id="password_confirmation" :label="confirmationLabel" required>
        <Input
            id="password_confirmation"
            v-model="confirmation"
            type="password"
            autocomplete="new-password"
            required
        />
    </FormField>
</template>
